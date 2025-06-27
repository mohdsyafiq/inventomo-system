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

// --- GET THE PURCHASE ORDER ID ---
$po_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if loaded for modal display
$is_modal = isset($_GET['modal']) && $_GET['modal'] === 'true';

if ($po_id === 0) {
    if ($is_modal) {
        echo "<p class='text-danger'>Error: No Purchase Order ID provided.</p>";
        exit;
    } else {
        die("No Purchase Order ID provided.");
    }
}

// --- FETCH DATA FROM DATABASE ---
$po_data = null;
$po_items = [];
$supplier_data = null;
$total_amount = 0;

// Main Purchase Order Query
$sql_po = "SELECT * FROM purchase_orders WHERE id = ?";
$stmt_po = $conn->prepare($sql_po);
$stmt_po->bind_param("i", $po_id);
$stmt_po->execute();
$result_po = $stmt_po->get_result();
if ($result_po->num_rows > 0) {
    $po_data = $result_po->fetch_assoc();
    $supplier_id = $po_data['supplier_id'];

    // Supplier Details Query
    $sql_supplier = "SELECT * FROM customer_supplier WHERE id = ?";
    $stmt_supplier = $conn->prepare($sql_supplier);
    $stmt_supplier->bind_param("i", $supplier_id);
    $stmt_supplier->execute();
    $result_supplier = $stmt_supplier->get_result();
    if ($result_supplier->num_rows > 0) {
        $supplier_data = $result_supplier->fetch_assoc();
    } else {
        // Ensure all expected keys are present even if supplier is not found
        $supplier_data = [
            'companyName' => 'N/A',
            'firstName' => 'N/A',
            'lastName' => 'N/A',
            'email' => 'N/A',
            'phone' => 'N/A'
        ];
    }
    $stmt_supplier->close();

    // Purchase Order Items Query - MODIFIED WITH LEFT JOIN AND COALESCE
    $sql_items = "
        SELECT
            po.id, po.po_number, po.date_ordered, po.total_amount, po.status,
            cs.companyName AS supplier_name,
            poi.quantity, poi.cost_price, poi.line_total,
            COALESCE(ii.product_name, 'Unknown Item') AS product_name, -- Use COALESCE here
            COALESCE(ii.price, 0.00) AS item_selling_price
        FROM
            purchase_orders po
        JOIN
            customer_supplier cs ON po.supplier_id = cs.id
        LEFT JOIN -- Changed to LEFT JOIN to ensure all purchase_order_items are included
            purchase_order_items poi ON po.id = poi.purchase_order_id
        LEFT JOIN -- Changed to LEFT JOIN for inventory_item as well
            inventory_item ii ON poi.item_id = ii.itemID
        WHERE
            po.id = ?
    ";

    // Now, use the $sql_items variable with your prepared statement
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $po_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();

    $po_items = [];
    $total_amount = 0; // Initialize total_amount before summing

    while ($row = $result_items->fetch_assoc()) {
        $po_items[] = $row;
        // Calculate total_amount based on fetched items (line_total is better if available and correct)
        // If line_total in DB is correct, use that, otherwise re-calculate as you did:
        $total_amount += (($row['quantity'] ?? 0) * ($row['cost_price'] ?? 0));
    }
    $stmt_items->close();

} else {
    if ($is_modal) {
        echo "<p class='text-danger'>Error: Purchase Order not found.</p>";
        exit;
    } else {
        die("Purchase Order not found.");
    }
}
$stmt_po->close();

function format_rm($amount) {
    return 'RM ' . number_format((float)$amount, 2, '.', ',');
}

// --- MODAL CONTENT RENDERING (FRAGMENT ONLY) ---
// If modal request, only output the relevant content (no full HTML document)
if ($is_modal) {
    ob_start(); // Start output buffering
?>
    <div class="card invoice-card" id="invoice-content" style="box-shadow:none; padding:0;">
        <div class="invoice-header d-flex justify-content-between align-items-start">
            <div>
                <div class="app-brand-logo demo">
                    <img width="180" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
                </div>
                <div class="company-details text-muted mt-2">
                    <p class="mb-0">Inventomo Sdn Bhd</p>
                    <p class="mb-0">988223-U</p>
                    <p class="mb-0">"Friendly Inventory Management System"</p>
                </div>
            </div>
            <div class="text-end">
                <h1 class="invoice-title">PURCHASE ORDER</h1>
                <div class="invoice-details text-muted">
                    <p class="mb-1"><strong>PO Number:</strong> <?php echo htmlspecialchars($po_data['po_number'] ?? 'N/A'); ?></p>
                    <p class="mb-1"><strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($po_data['date_ordered'] ?? 'now')); ?></p>
                </div>
            </div>
        </div>

        <div class="party-details d-flex justify-content-start flex-wrap">
            <div class="col-6 pe-4">
                <h4>Vendor</h4>
                <p class="mb-1"><strong>Company Name:</strong> <?php echo htmlspecialchars($supplier_data['companyName'] ?? 'N/A'); ?></p>
                <p class="mb-1"><strong>Contact Person:</strong> <?php echo htmlspecialchars(($supplier_data['firstName'] ?? 'N/A') . ' ' . ($supplier_data['lastName'] ?? 'N/A')); ?></p>
                <p class="mb-1 text-muted"><strong>Email:</strong> <?php echo htmlspecialchars($supplier_data['email'] ?? 'N/A'); ?></p>
                <p class="mb-0 text-muted"><strong>Phone:</strong> <?php echo htmlspecialchars($supplier_data['phone'] ?? 'N/A'); ?></p>
            </div>
            <div class="col-6 ps-4">
                <h5>Ship to</h4>
                <p class="mb-1"><strong>Company Name: Inventomo Sdn. Bhd.</strong></p>
                <p class="mb-1"><strong>Contact Person : Admin . 019-251 5512</strong></p>
                <p class="mb-1 text-muted">Address : Lot515, Jalan Mahawangsa, Wangsa Maju, Kuala Lumpur, 50000, Malaysia</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 45%;">Item</th>
                        <th style="width: 15%; text-align: center;">Quantity</th>
                        <th style="width: 15%; text-align: right;">Cost Price</th>
                        <th style="width: 20%; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($po_items as $item): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?></td>
                        <td style="text-align: center;"><?php echo htmlspecialchars($item['quantity'] ?? '0'); ?></td>
                        <td style="text-align: right;"><?php echo format_rm($item['cost_price'] ?? 0); ?></td>
                        <td style="text-align: right;"><?php echo format_rm(($item['quantity'] ?? 0) * ($item['cost_price'] ?? 0)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-4 d-flex justify-content-between">
            <div class="col-md-6">
                <h6>Notes:</h6>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($po_data['notes'] ?? 'N/A')); ?></p>
            </div>
            <div class="col-md-6 d-flex justify-content-end">
                <div class="totals-section">
                    <div class="d-flex"><span>Subtotal</span> <span><?php echo format_rm($total_amount); ?></span></div>
                    <div class="d-flex"><span>Tax (0%)</span> <span><?php echo format_rm(0); ?></span></div>
                    <div class="d-flex grand-total"><span>Total</span> <span><?php echo format_rm($total_amount); ?></span></div>
                </div>
            </div>
        </div>
    </div>

<?php
    $conn->close();
    ob_end_flush(); // Send the buffered output
    exit; // Stop further execution for modal requests
}
// If not a modal request, continue to render the full page below
?>

---

## Full Page HTML & CSS (Print Button Behavior)

Here's the updated HTML, CSS, and the crucial JavaScript to manage the button visibility.

```html
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>View Purchase Order - Inventomo</title>

    <meta name="description" content="View Purchase Order Details" />

    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />

    <link rel="preconnect" href="[https://fonts.googleapis.com](https://fonts.googleapis.com)" />
    <link rel="preconnect" href="[https://fonts.gstatic.com](https://fonts.gstatic.com)" crossorigin />
    <link href="[https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap](https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap)" rel="stylesheet" />

    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <style>
        body {
            background-color: #f5f5f9;
        }
        .invoice-card {
            background-color: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
            padding: 3rem;
            margin-top: 2rem;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between; /* To push logo/company info left and PO details right */
            align-items: flex-start;
            margin-bottom: 2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }
        .invoice-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #566a7f;
            margin-bottom: 0.5rem;
        }
        .invoice-details p {
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        .company-details p {
            margin-bottom: 0.25rem;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        .party-details {
            display: flex;
            justify-content: flex-start; /* Aligns both Vendor and Ship To to the left */
            margin-bottom: 2rem;
            gap: 2rem; /* Creates space between Vendor and Ship To columns */
        }
        .party-details h4, .party-details h5 { /* Applied to both h4 and h5 for consistency */
            font-size: 1.1rem;
            font-weight: 600;
            color: #696cff; /* Primary color for headers */
            margin-bottom: 0.75rem;
        }
        .party-details p {
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        .table {
            margin-bottom: 2rem;
        }
        .table th {
            background-color: #f8f9fa;
            color: #566a7f;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td {
            font-size: 0.9rem;
            color: #566a7f;
        }
        .totals-section {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            width: 100%;
            max-width: 300px;
            margin-left: auto;
        }
        .totals-section .d-flex {
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 1rem;
            color: #566a7f;
        }
        .totals-section .grand-total {
            font-size: 1.25rem;
            font-weight: 700;
            color: #696cff;
            border-top: 1px dashed #d9dee3;
            padding-top: 1rem;
            margin-top: 1rem;
        }
        .action-buttons { /* New class for the button container */
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }
        .btn-print, .btn-edit {
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-size: 1rem;
            margin: 0 0.5rem;
        }
        .btn-print {
            background-color: #696cff;
            color: white;
            border: none;
        }
        .btn-print:hover {
            background-color: #5f63f2;
            color: white;
        }
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
            border: none;
        }
        .btn-edit:hover {
            background-color: #e0a800;
            color: #212529;
        }
        .badge {
            font-size: 0.8em;
            padding: 0.4em 0.6em;
        }

        /* --- STYLES TO MOVE MAIN CONTENT LEFT --- */
        /* This targets the main content container and removes its centering */
        .content-wrapper > .container-xxl {
            max-width: none !important;  /* Allow it to expand beyond default max-width */
            margin-left: 0 !important;    /* Push content to the very left edge */
            margin-right: 0 !important;  /* Remove any auto right margin */
            padding-left: 1.5rem; /* Add desired left padding for visual comfort */
            padding-right: 1.5rem; /* Add desired right padding */
        }

        /* --- PRINT Specific Styles for A4 Alignment --- */
        @media print {
            /* Set paper size to A4 and define print margins */
            @page {
                size: A4;
                margin: 2cm; /* Typical A4 margins (top, right, bottom, left) */
            }

            /* Hide non-essential layout elements */
            .layout-wrapper .layout-menu-toggle,
            .layout-wrapper .layout-overlay,
            #layout-menu,
            #layout-navbar,
            .content-header,
            .fw-bold.py-3.mb-4,
            .content-footer,
            .action-buttons /* Hide the button container on print */
            {
                display: none !important;
            }

            /* Adjust main content for full width within print margins */
            .layout-page,
            .content-wrapper,
            .container-xxl,
            .container-p-y {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Ensure invoice card takes full width, no shadow/background, and adjusted padding for print */
            .invoice-card {
                box-shadow: none !important;
                background-color: transparent !important;
                border-radius: 0 !important;
                margin-top: 0 !important;
                padding: 0 !important; /* Remove card padding, let @page margin handle it */
            }

            /* Ensure text is dark and clear for printing */
            body, p, span, h1, h4, h5, h6, table, td, th {
                color: #000 !important;
                font-size: 10pt; /* Adjust base font size for print readability */
            }
            .invoice-title {
                font-size: 24pt !important; /* Larger for header */
            }
            .party-details h4, .party-details h5 {
                font-size: 12pt !important; /* Slightly larger for section headers */
                color: #000 !important; /* Ensure black for print */
            }
            .table th, .table td {
                font-size: 9pt !important; /* Smaller for table content */
                border-color: #dee2e6 !important; /* Ensure table borders are visible */
            }
            .table thead th {
                background-color: #f2f2f2 !important; /* Light grey header */
                -webkit-print-color-adjust: exact;
                color: #000 !important;
            }
            .totals-section {
                background-color: #f8f8f8 !important; /* Light background for totals */
                -webkit-print-color-adjust: exact;
                border: 1px solid #eee;
            }
            .totals-section .grand-total {
                color: #000 !important; /* Ensure black for print */
            }

            /* Layout adjustments for print */
            .invoice-header, .party-details {
                display: flex;
                flex-direction: row; /* Ensure elements stay in a row */
                justify-content: space-between;
                align-items: flex-start;
            }

            .party-details .col-6 {
                flex: 0 0 48%; /* Adjust width to fit two columns side-by-side with a small gap */
                max-width: 48%;
                box-sizing: border-box;
            }
            .party-details .col-6.pe-4 { /* Remove specific padding for print */
                padding-right: 0 !important;
            }
            .party-details .col-6.ps-4 { /* Remove specific padding for print */
                padding-left: 0 !important;
            }

            /* Table column widths for A4 */
            .table th:nth-child(1), .table td:nth-child(1) { width: 5%; }   /* # */
            .table th:nth-child(2), .table td:nth-child(2) { width: 45%; }  /* Item */
            .table th:nth-child(3), .table td:nth-child(3) { width: 15%; text-align: center;} /* Quantity */
            .table th:nth-child(4), .table td:nth-child(4) { width: 15%; text-align: right;} /* Cost Price */
            .table th:nth-child(5), .table td:nth-child(5) { width: 20%; text-align: right;} /* Amount */

            /* Ensure page breaks don't cut content awkwardly */
            .table, .invoice-header, .party-details, .totals-section {
                page-break-inside: avoid;
            }
            .table tbody tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            /* Remove padding from body */
            body {
                padding: 0 !important;
                margin: 0 !important; /* Let @page margin handle it */
            }
        }
    </style>

    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="index.php" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <img width="160" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
                        </span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item"><a href="index.php" class="menu-link"><i class="menu-icon tf-icons bx bx-home-circle"></i><div>Dashboard</div></a></li>
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Pages</span></li>
                    <li class="menu-item"><a href="inventory.php" class="menu-link"><i class="menu-icon tf-icons bx bx-card"></i><div>Inventory</div></a></li>
                    <li class="menu-item"><a href="stock-management.php" class="menu-link"><i class="menu-icon tf-icons bx bx-list-plus"></i><div>Stock Management</div></a></li>
                    <li class="menu-item"><a href="customer-supplier.php" class="menu-link"><i class="menu-icon tf-icons bx bxs-user-detail"></i><div>Supplier & Customer</div></a></li>
                    <li class="menu-item active"><a href="order-billing.php" class="menu-link"><i class="menu-icon tf-icons bx bx-cart"></i><div>Order & Billing</div></a></li>
                    <li class="menu-item"><a href="report.php" class="menu-link"><i class="menu-icon tf-icons bx bxs-report"></i><div>Report</div></a></li>
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Account</span></li>
                    <li class="menu-item"><a href="user.php" class="menu-link"><i class="menu-icon tf-icons bx bx-user"></i><div>User Management</div></a></li>
                </ul>
            </aside>
            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="user-profile.php"><i class="bx bx-user me-2"></i><span class="align-middle">My Profile</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Settings</span></a></li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="bx bx-power-off me-2"></i><span class="align-middle">Log Out</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Orders & Billing /</span> View Purchase Order</h4>

                        <div class="card invoice-card" id="invoice-content">
                            <div class="invoice-header d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="app-brand-logo demo">
                                        <img width="180" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
                                    </div>
                                    <div class="company-details text-muted mt-2">
                                        <p class="mb-0">Inventomo Sdn Bhd</p><p class="mb-0">988223-U</p><p class="mb-0">"Friendly Inventory Management System"</p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <h1 class="invoice-title">PURCHASE ORDER</h1>
                                    <div class="invoice-details text-muted">
                                        <p class="mb-1"><strong>PO Number:</strong> <?php echo htmlspecialchars($po_data['po_number'] ?? 'N/A'); ?></p>
                                        <p class="mb-1"><strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($po_data['date_ordered'] ?? 'now')); ?></p>
                                        <p class="mb-1">
                                            <strong>Status:</strong>
                                            <span class="badge
                                                <?php
                                                // Dynamic badge class based on status
                                                $current_status = strtolower(htmlspecialchars($po_data['status'] ?? 'N/A'));
                                                switch ($current_status) {
                                                    case 'pending': echo 'bg-label-warning'; break;
                                                    case 'approved': echo 'bg-label-success'; break;
                                                    case 'cancelled': echo 'bg-label-danger'; break;
                                                    case 'completed': echo 'bg-label-info'; break;
                                                    default: echo 'bg-label-secondary'; break;
                                                }
                                                ?>">
                                                <?php echo htmlspecialchars($po_data['status'] ?? 'N/A'); ?>
                                            </span>
                                            <?php if ($current_status !== 'approved'): // Only show button if not already approved ?>
                                                <a href="update-purchase-order-status.php?id=<?php echo htmlspecialchars($po_id); ?>&status=Approved"
                                                   class="btn btn-sm btn-success ms-2">
                                                   Mark as Approved
                                                </a>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="party-details d-flex justify-content-start flex-wrap">
                                <div class="col-6 pe-4">
                                    <h4>Vendor</h4>
                                    <p class="mb-1"><strong>Company Name:</strong> <?php echo htmlspecialchars($supplier_data['companyName'] ?? 'N/A'); ?></p>
                                    <p class="mb-1"><strong>Contact Person:</strong> <?php echo htmlspecialchars(($supplier_data['firstName'] ?? 'N/A') . ' ' . ($supplier_data['lastName'] ?? 'N/A')); ?></p>
                                    <p class="mb-1 text-muted"><strong>Email:</strong> <?php echo htmlspecialchars($supplier_data['email'] ?? 'N/A'); ?></p>
                                    <p class="mb-1 text-muted"><strong>Phone:</strong> <?php echo htmlspecialchars($supplier_data['phone'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="col-6 ps-4">
                                    <h5>Ship To</h4>
                                    <p class="mb-1"><strong>Company Name: Inventomo Sdn. Bhd.</strong></p>
                                    <p class="mb-1"><strong>Contact Person: Admin - 019 251 2254</strong></p>
                                    <p class="mb-1 text-muted">Address : Lot 515 , Jalan Mahawangsa, Wangsa Maju,Kuala Lumpur, 50000, Malaysia</p>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 45%;">Item</th>
                                            <th style="width: 15%; text-align: center;">Quantity</th>
                                            <th style="width: 15%; text-align: right;">Cost Price</th>
                                            <th style="width: 20%; text-align: right;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; foreach ($po_items as $item): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?></td>
                                            <td style="text-align: center;"><?php echo htmlspecialchars($item['quantity'] ?? '0'); ?></td>
                                            <td style="text-align: right;"><?php echo format_rm($item['cost_price'] ?? 0); ?></td>
                                            <td style="text-align: right;"><?php echo format_rm(($item['quantity'] ?? 0) * ($item['cost_price'] ?? 0)); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="row mt-4 d-flex justify-content-between">
                                <div class="col-md-6">
                                    <h6>Notes:</h6>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($po_data['notes'] ?? 'N/A')); ?></p>
                                </div>
                                <div class="col-md-6 d-flex justify-content-end">
                                    <div class="totals-section">
                                        <div class="d-flex"><span>Subtotal</span> <span><?php echo format_rm($total_amount); ?></span></div>
                                        <div class="d-flex"><span>Tax (0%)</span> <span><?php echo format_rm(0); ?></span></div>
                                        <div class="d-flex grand-total"><span>Total</span> <span><?php echo format_rm($total_amount); ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function handlePrint() {
            // Get the button container element
            const actionButtons = document.querySelector('.action-buttons');

            // Hide the button container before printing
            if (actionButtons) {
                actionButtons.style.display = 'none';
            }

            // Trigger the print dialog
            window.print();

            // Use media query listener to detect when print dialog closes
            // This is a more reliable way to bring buttons back than setTimeout
            const mediaQueryList = window.matchMedia('print');
            mediaQueryList.addListener(function(mql) {
                if (!mql.matches) {
                    // If not matching print (i.e., print dialog is closed)
                    if (actionButtons) {
                        actionButtons.style.display = 'flex'; // Show buttons again
                    }
                }
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
