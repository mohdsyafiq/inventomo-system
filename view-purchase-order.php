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
    $sql_supplier = "SELECT * FROM suppliers WHERE id = ?";
    $stmt_supplier = $conn->prepare($sql_supplier);
    $stmt_supplier->bind_param("i", $supplier_id);
    $stmt_supplier->execute();
    $result_supplier = $stmt_supplier->get_result();
    if ($result_supplier->num_rows > 0) {
        $supplier_data = $result_supplier->fetch_assoc();
    } else {
        $supplier_data = ['name' => 'N/A', 'contact_person' => 'N/A', 'email' => 'N/A', 'phone' => 'N/A'];
    }
    $stmt_supplier->close();

    // Purchase Order Items Query
    $sql_items = "
        SELECT poi.*, p.product_name
        FROM purchase_order_items poi
        JOIN products p ON poi.product_id = p.id
        WHERE poi.purchase_order_id = ?
    ";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $po_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while ($row = $result_items->fetch_assoc()) {
        $po_items[] = $row;
        $total_amount += ($row['quantity'] * $row['cost_price']);
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

// If modal request, only output the relevant content
if ($is_modal) {
    ob_start(); // Start output buffering
?>
    <div class="card invoice-card" id="invoice-content" style="box-shadow:none; padding:0;">
        <div class="invoice-header">
            <div>
                <h1 class="invoice-title">PURCHASE ORDER</h1>
                <div class="invoice-details text-muted">
                    <p class="mb-1"><strong>PO Number:</strong> <?php echo htmlspecialchars($po_data['po_number']); ?></p>
                    <p class="mb-1"><strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($po_data['order_date'])); ?></p>
                    <p class="mb-1"><strong>Status:</strong> <span class="badge bg-label-warning"><?php echo htmlspecialchars($po_data['status']); ?></span></p>
                </div>
            </div>
            <div class="text-end">
                <div class="app-brand-logo demo">
                    <img width="200" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
                </div>
                <div class="company-details text-muted mt-2">
                    <p class="mb-0">123 Inventomo St.</p>
                    <p class="mb-0">Kuala Lumpur, 50000</p>
                    <p class="mb-0">Malaysia</p>
                </div>
            </div>
        </div>

        <div class="party-details">
            <div class="col-6">
                <h5>Vendor</h5>
                <p class="mb-1"><strong><?php echo htmlspecialchars($supplier_data['name']); ?></strong></p>
                <p class="mb-1 text-muted"><?php echo htmlspecialchars($supplier_data['contact_person']); ?></p>
                <p class="mb-1 text-muted"><?php echo htmlspecialchars($supplier_data['email']); ?></p>
                <p class="mb-0 text-muted"><?php echo htmlspecialchars($supplier_data['phone']); ?></p>
            </div>
            <div class="col-6 text-end">
                <h5>Ship To</h5>
                <p class="mb-1"><strong>Inventomo Sdn. Bhd.</strong></p>
                 <p class="mb-1 text-muted">123 Inventomo St.</p>
                <p class="mb-1 text-muted">Kuala Lumpur, 50000, Malaysia</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Cost Price</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($po_items as $item): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo format_rm($item['cost_price']); ?></td>
                        <td><?php echo format_rm($item['quantity'] * $item['cost_price']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <h6>Notes:</h6>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($po_data['notes'])); ?></p>
            </div>
            <div class="col-md-6">
                <div class="totals-section">
                    <div class="d-flex"><span>Subtotal</span> <span><?php echo format_rm($total_amount); ?></span></div>
                    <div class="d-flex"><span>Tax (0%)</span> <span><?php echo format_rm(0); ?></span></div>
                    <div class="d-flex grand-total"><span>Total</span> <span><?php echo format_rm($total_amount); ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-4">
        <button class="btn btn-primary btn-print" onclick="window.print()"><i class="bx bx-printer me-1"></i> Print</button>
        <a href="edit-purchase-order.php?id=<?php echo $po_id; ?>" class="btn btn-warning btn-edit"><i class="bx bx-edit me-1"></i> Edit</a>
    </div>
<?php
    $conn->close();
    ob_end_flush(); // Send the buffered output
    exit; // Stop further execution for modal requests
}
// If not a modal request, continue to render the full page below
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>View Purchase Order - Inventomo</title>

    <meta name="description" content="View Purchase Order Details" />

    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />

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
            justify-content: space-between;
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
            justify-content: space-between;
            margin-bottom: 2rem;
            gap: 2rem;
        }
        .party-details h5 {
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
                            <div class="invoice-header">
                                <div>
                                    <h1 class="invoice-title">PURCHASE ORDER</h1>
                                    <div class="invoice-details text-muted">
                                        <p class="mb-1"><strong>PO Number:</strong> <?php echo htmlspecialchars($po_data['po_number']); ?></p>
                                        <p class="mb-1"><strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($po_data['order_date'])); ?></p>
                                        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-label-warning"><?php echo htmlspecialchars($po_data['status']); ?></span></p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="app-brand-logo demo">
                                        <img width="200" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
                                    </div>
                                    <div class="company-details text-muted mt-2">
                                        <p class="mb-0">123 Inventomo St.</p>
                                        <p class="mb-0">Kuala Lumpur, 50000</p>
                                        <p class="mb-0">Malaysia</p>
                                    </div>
                                </div>
                            </div>

                            <div class="party-details">
                                <div class="col-6">
                                    <h5>Vendor</h5>
                                    <p class="mb-1"><strong><?php echo htmlspecialchars($supplier_data['name']); ?></strong></p>
                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($supplier_data['contact_person']); ?></p>
                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($supplier_data['email']); ?></p>
                                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($supplier_data['phone']); ?></p>
                                </div>
                                <div class="col-6 text-end">
                                    <h5>Ship To</h5>
                                    <p class="mb-1"><strong>Inventomo Sdn. Bhd.</strong></p>
                                     <p class="mb-1 text-muted">123 Inventomo St.</p>
                                    <p class="mb-1 text-muted">Kuala Lumpur, 50000, Malaysia</p>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Item</th>
                                            <th>Quantity</th>
                                            <th>Cost Price</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; foreach ($po_items as $item): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td><?php echo format_rm($item['cost_price']); ?></td>
                                            <td><?php echo format_rm($item['quantity'] * $item['cost_price']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h6>Notes:</h6>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($po_data['notes'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <div class="totals-section">
                                        <div class="d-flex"><span>Subtotal</span> <span><?php echo format_rm($total_amount); ?></span></div>
                                        <div class="d-flex"><span>Tax (0%)</span> <span><?php echo format_rm(0); ?></span></div>
                                        <div class="d-flex grand-total"><span>Total</span> <span><?php echo format_rm($total_amount); ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button class="btn btn-primary btn-print" onclick="window.print()"><i class="bx bx-printer me-1"></i> Print</button>
                            <a href="edit-purchase-order.php?id=<?php echo $po_id; ?>" class="btn btn-warning btn-edit"><i class="bx bx-edit me-1"></i> Edit</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>