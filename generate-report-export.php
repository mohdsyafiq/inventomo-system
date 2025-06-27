<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Database connection details
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';

// Attempt to establish database connection
$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    // Log error and exit if database connection fails
    error_log("Database connection failed for export: " . mysqli_connect_error());
    die("Database connection failed."); // Display a generic error to the user
}

mysqli_set_charset($conn, "utf8");

// Get filter parameters from the GET request
// These parameters will be sent from report.php when the export button is clicked.
$from_date = isset($_GET['from_date']) ? mysqli_real_escape_string($conn, $_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? mysqli_real_escape_string($conn, $_GET['to_date']) : '';
$item_id_filter = isset($_GET['item_id_filter']) ? mysqli_real_escape_string($conn, $_GET['item_id_filter']) : '';
$category_filter = isset($_GET['category_filter']) ? mysqli_real_escape_string($conn, $_GET['category_filter']) : '';
$transaction_type_filter = isset($_GET['transaction_type_filter']) ? mysqli_real_escape_string($conn, $_GET['transaction_type_filter']) : 'all';
$export_format = isset($_GET['export_format']) ? mysqli_real_escape_string($conn, $_GET['export_format']) : 'csv'; // Default to CSV

// --- Build SQL Queries (Reusing logic from report.php) ---

$sales_query_part = "
    SELECT
        soh.transaction_date AS date,
        soh.product_id AS item_id,
        ii.product_name AS description,
        ii.type_product AS category,
        soh.quantity_deducted AS quantity,
        ii.price AS unit_price,
        (soh.quantity_deducted * ii.price) AS total,
        'Stock Out (Sales)' AS type_display
    FROM stock_out_history AS soh
    JOIN inventory_item AS ii ON soh.product_id = ii.itemID
    WHERE 1=1
";

$purchases_query_part = "
    SELECT
        sih.transaction_date AS date,
        sih.product_id AS item_id,
        ii.product_name AS description,
        ii.type_product AS category,
        sih.quantity_added AS quantity,
        ii.price AS unit_price,
        (sih.quantity_added * ii.price) AS total,
        'Stock In (Purchases)' AS type_display
    FROM stock_in_history AS sih
    JOIN inventory_item AS ii ON sih.product_id = ii.itemID
    WHERE 1=1
";

$conditions_for_subqueries_sales = ""; // Conditions specific to sales subquery
$conditions_for_subqueries_purchases = ""; // Conditions specific to purchases subquery

// Add item ID filter to relevant subquery conditions
if (!empty($item_id_filter)) {
    $conditions_for_subqueries_sales .= " AND soh.product_id = '$item_id_filter'";
    $conditions_for_subqueries_purchases .= " AND sih.product_id = '$item_id_filter'";
}

// Add category filter to relevant subquery conditions
if (!empty($category_filter)) {
    $conditions_for_subqueries_sales .= " AND ii.type_product = '$category_filter'";
    $conditions_for_subqueries_purchases .= " AND ii.type_product = '$category_filter'";
}


// Construct the final UNION ALL query based on the transaction type filter
$final_query_parts = [];
if ($transaction_type_filter == 'all' || $transaction_type_filter == 'stock_out') {
    $final_query_parts[] = "($sales_query_part" . $conditions_for_subqueries_sales . ")";
}
if ($transaction_type_filter == 'all' || $transaction_type_filter == 'stock_in') {
    $final_query_parts[] = "($purchases_query_part" . $conditions_for_subqueries_purchases . ")";
}

// Check if any query parts were added
if (empty($final_query_parts)) {
    // If no specific transaction type is selected (or invalid type), default to all
    $base_union_query = "($sales_query_part) UNION ALL ($purchases_query_part)";
} else {
    $base_union_query = implode(" UNION ALL ", $final_query_parts);
}

// Apply date filter to the combined set
$combined_date_condition = "";
if (!empty($from_date) && !empty($to_date)) {
    $combined_date_condition .= " AND combined.date BETWEEN '$from_date' AND '$to_date'";
}

// Final query with an outer SELECT to apply ordering and combined date filter
$final_query = "SELECT * FROM ($base_union_query) AS combined WHERE 1=1 " . $combined_date_condition . " ORDER BY date DESC";

$report_data = [];
$report_result = mysqli_query($conn, $final_query);

if ($report_result) {
    $report_data = mysqli_fetch_all($report_result, MYSQLI_ASSOC);
} else {
    error_log("Combined report query failed for export: " . mysqli_error($conn));
}

// --- Generate Report based on Format ---

$filename = "inventory_report_" . date('Ymd_His');
$mime_type = '';
$extension = '';

switch ($export_format) {
    case 'csv':
        $mime_type = 'text/csv';
        $extension = 'csv';
        break;
    case 'excel':
        // For simple Excel, use CSV MIME type and .xls extension. Excel often handles this.
        $mime_type = 'application/vnd.ms-excel'; // For .xls
        $extension = 'xls';
        break;
    case 'pdf':
        // For very basic "PDF" containing CSV data. Not a true PDF.
        $mime_type = 'text/plain'; // Or application/octet-stream for forced download
        $extension = 'pdf';
        break;
    default:
        http_response_code(400); // Bad Request
        echo "Invalid export format specified.";
        mysqli_close($conn);
        exit;
}

// Set headers for download
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $filename . '.' . $extension . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Headers for the CSV content (used for all formats)
fputcsv($output, ['No.', 'Date', 'Item ID', 'Item Name', 'Category', 'Quantity', 'Unit Price / Cost (RM)', 'Total (RM)', 'Transaction Type']);

// Data rows
foreach ($report_data as $index => $row) {
    fputcsv($output, [
        $index + 1,
        date('d-m-Y', strtotime($row['date'])),
        $row['item_id'],
        $row['description'],
        $row['category'],
        $row['quantity'],
        number_format($row['unit_price'], 2),
        number_format($row['total'], 2),
        $row['type_display']
    ]);
}
fclose($output);

// Close connection
mysqli_close($conn);
exit; // Terminate script after sending file
?>
