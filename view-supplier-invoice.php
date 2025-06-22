<?php
// --- SETUP AND DATABASE CONNECTION ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'inventory_system');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// --- GET THE BILL ID ---
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Check if loaded for modal display
$is_modal = isset($_GET['modal']) && $_GET['modal'] === 'true';

if ($bill_id === 0) {
    if ($is_modal) {
        echo "<p class='text-danger'>Error: No Supplier Bill ID provided.</p>";
        exit;
    } else {
        die("No Supplier Bill ID provided.");
    }
}

// --- FETCH DATA FROM DATABASE ---
$bill_data = null;
$supplier_data = null;

// Main Bill Query
$sql_bill = "
    SELECT
        sb.*,
        s.name as supplier_name,
        s.contact_person,
        s.email,
        s.phone
    FROM supplier_bills sb
    JOIN suppliers s ON sb.supplier_id = s.id
    WHERE sb.id = ?
";
$stmt_bill = $conn->prepare($sql_bill);
$stmt_bill->bind_param("i", $bill_id);
$stmt_bill->execute();
$result_bill = $stmt_bill->get_result();
if ($result_bill->num_rows > 0) {
    $bill_data = $result_bill->fetch_assoc();
}
$stmt_bill->close();

if (!$bill_data) {
    if ($is_modal) {
        echo "<p class='text-danger'>Error: Supplier Bill not found.</p>";
        exit;
    } else {
        die("Supplier Bill not found.");
    }
}

// --- HELPER FUNCTIONS ---
function format_rm($amount) {
    return 'RM ' . number_format((float)$amount, 2, '.', ',');
}

// If modal request, only output the relevant content
if ($is_modal) {
    ob_start(); // Start output buffering
?>
    <div class="invoice-card" style="box-shadow:none; padding:0;">
        <div class="invoice-header">
            <div>
                <h1 class="invoice-title">SUPPLIER BILL</h1>
                <p class="text-muted mb-0">Bill from: <strong><?php echo htmlspecialchars($bill_data['supplier_name']); ?></strong></p>
            </div>
            <div class="text-end">
                <img width="200" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
            </div>
        </div>

        <div class="party-details">
            <h5>Supplier Details</h5>
            <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($bill_data['contact_person']); ?></p>
            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($bill_data['email']); ?></p>
            <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($bill_data['phone']); ?></p>
        </div>

        <h5>Bill Information</h5>
        <div class="details-grid">
            <div>
                <p class="text-muted mb-1">Bill Number:</p>
                <strong><?php echo htmlspecialchars($bill_data['bill_number']); ?></strong>
            </div>
            <div>
                <p class="text-muted mb-1">Date Received:</p>
                <strong><?php echo date('F j, Y', strtotime($bill_data['date_received'])); ?></strong>
            </div>
            <div>
                <p class="text-muted mb-1">Due Date:</p>
                <strong><?php echo date('F j, Y', strtotime($bill_data['due_date'])); ?></strong>
            </div>
            <div>
                <p class="text-muted mb-1">Status:</p>
                <span class="badge bg-label-danger"><?php echo htmlspecialchars($bill_data['status']); ?></span>
            </div>
        </div>

         <hr class="my-4">

        <div class="row">
            <div class="col-md-6">
                <h6>Notes:</h6>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($bill_data['notes'])); ?></p>
            </div>
            <div class="col-md-6 text-end">
                <p class="text-muted mb-2">Total Amount Due</p>
                <h3 class="mb-0"><?php echo format_rm($bill_data['total_due']); ?></h3>
            </div>
        </div>
    </div>

    <div class="text-center mt-4">
        <button class="btn btn-primary btn-print" onclick="window.print()"><i class="bx bx-printer me-1"></i> Print</button>
        <a href="edit-supplier-invoice.php?id=<?php echo $bill_id; ?>" class="btn btn-warning btn-edit"><i class="bx bx-edit me-1"></i> Edit</a>
    </div>
<?php
    $conn->close();
    ob_end_flush(); // Send the buffered output
    exit; // Stop further execution for modal requests
}
// If not a modal request, continue to render the full page below
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
    <meta charset="utf-8" />
    <title>View Supplier Invoice - Inventomo</title>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" />
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="assets/vendor/css/core.css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="assets/css/demo.css" />
    <style>
        .invoice-card { padding: 2rem; }
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; }
        .invoice-title { font-size: 2.5rem; font-weight: bold; color: #566a7f; }
        .party-details { border-bottom: 1px solid #d9dee3; padding-bottom: 1rem; margin-bottom: 2rem; }
        .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .details-grid p { margin-bottom: 0.5rem; }
        @media print {
            .layout-navbar, .layout-menu, .footer, .btn-print, .btn-edit { display: none !important; }
            .content-wrapper { padding: 0 !important; }
            .invoice-card { box-shadow: none !important; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <div class="layout-page">
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="card invoice-card">
                            <div class="invoice-header">
                                <div>
                                    <h1 class="invoice-title">SUPPLIER BILL</h1>
                                    <p class="text-muted mb-0">Bill from: <strong><?php echo htmlspecialchars($bill_data['supplier_name']); ?></strong></p>
                                </div>
                                <div class="text-end">
                                    <img width="200" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
                                </div>
                            </div>

                            <div class="party-details">
                                <h5>Supplier Details</h5>
                                <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($bill_data['contact_person']); ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($bill_data['email']); ?></p>
                                <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($bill_data['phone']); ?></p>
                            </div>

                            <h5>Bill Information</h5>
                            <div class="details-grid">
                                <div>
                                    <p class="text-muted mb-1">Bill Number:</p>
                                    <strong><?php echo htmlspecialchars($bill_data['bill_number']); ?></strong>
                                </div>
                                <div>
                                    <p class="text-muted mb-1">Date Received:</p>
                                    <strong><?php echo date('F j, Y', strtotime($bill_data['date_received'])); ?></strong>
                                </div>
                                <div>
                                    <p class="text-muted mb-1">Due Date:</p>
                                    <strong><?php echo date('F j, Y', strtotime($bill_data['due_date'])); ?></strong>
                                </div>
                                <div>
                                    <p class="text-muted mb-1">Status:</p>
                                    <span class="badge bg-label-danger"><?php echo htmlspecialchars($bill_data['status']); ?></span>
                                </div>
                            </div>

                             <hr class="my-4">

                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Notes:</h6>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($bill_data['notes'])); ?></p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <p class="text-muted mb-2">Total Amount Due</p>
                                    <h3 class="mb-0"><?php echo format_rm($bill_data['total_due']); ?></h3>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button class="btn btn-primary btn-print" onclick="window.print()"><i class="bx bx-printer me-1"></i> Print</button>
                            <a href="edit-supplier-invoice.php?id=<?php echo $bill_id; ?>" class="btn btn-warning btn-edit"><i class="bx bx-edit me-1"></i> Edit</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>