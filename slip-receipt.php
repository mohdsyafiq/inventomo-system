<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Get invoice number from URL parameter
$invoice_no = isset($_GET['invoice']) ? $_GET['invoice'] : '';

// Comment out invoice validation for testing
// if (empty($invoice_no)) {
//     die('Invoice number is required');
// }

// Use default invoice if none provided
if (empty($invoice_no)) {
    $invoice_no = 'INV-2025-00120'; // Default invoice for testing
}

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';

// Sample receipt data - replace with actual database queries
$receipt_data = [
    'INV-2025-00120' => [
        'id' => 'INV-2025-00120',
        'invoice' => '1234567',
        'customer' => 'Aminah Binti Ali',
        'email' => 'aminah@gmail.com',
        'address' => '123 Taman Indah, Jalan Harmoni, Shah Alam, Selangor 40000',
        'phone' => '+60123456789',
        'date' => '2024-03-20',
        'items' => [
            ['desc' => 'Wireless Mouse', 'qty' => 2, 'price' => 50.00, 'amount' => 100.00],
            ['desc' => 'HDMI Cable 2m', 'qty' => 1, 'price' => 75.00, 'amount' => 75.00]
        ],
        'subtotal' => 175.00,
        'tax' => 10.50,
        'shipping' => 5.00,
        'total' => 190.50,
        'payment_method' => 'Credit Card (**** 5678)',
        'transaction_id' => 'TXN-20240320-001'
    ],
    'INV-2025-00121' => [
        'id' => 'INV-2025-00121',
        'invoice' => '1234568',
        'customer' => 'Ahmad Rahman',
        'email' => 'ahmad@gmail.com',
        'address' => '456 Taman Sentosa, Jalan Makmur, Kuala Lumpur 50000',
        'phone' => '+60129876543',
        'date' => '2024-03-21',
        'items' => [
            ['desc' => 'Bluetooth Speaker', 'qty' => 1, 'price' => 120.00, 'amount' => 120.00]
        ],
        'subtotal' => 120.00,
        'tax' => 7.20,
        'shipping' => 8.00,
        'total' => 135.20,
        'payment_method' => 'Online Banking',
        'transaction_id' => 'TXN-20240321-001'
    ],
    'INV-2025-00122' => [
        'id' => 'INV-2025-00122',
        'invoice' => '1234569',
        'customer' => 'Siti Nurhaliza',
        'email' => 'siti@gmail.com',
        'address' => '789 Taman Bahagia, Jalan Sejahtera, Seremban, Negeri Sembilan 70000',
        'phone' => '+60135551234',
        'date' => '2024-03-22',
        'items' => [
            ['desc' => 'Gaming Keyboard RGB', 'qty' => 1, 'price' => 180.00, 'amount' => 180.00],
            ['desc' => 'Mouse Pad XL', 'qty' => 1, 'price' => 25.00, 'amount' => 25.00]
        ],
        'subtotal' => 205.00,
        'tax' => 12.30,
        'shipping' => 6.00,
        'total' => 223.30,
        'payment_method' => 'Credit Card (**** 9012)',
        'transaction_id' => 'TXN-20240322-001'
    ],
    'INV-2025-00123' => [
        'id' => 'INV-2025-00123',
        'invoice' => '1234570',
        'customer' => 'Lee Wei Ming',
        'email' => 'wei.ming@gmail.com',
        'address' => '123 Taman Harmoni, Jalan Sejahtera, Petaling Jaya, Selangor 47300',
        'phone' => '+60123334567',
        'date' => '2024-03-23',
        'items' => [
            ['desc' => 'USB Drive 32GB', 'qty' => 2, 'price' => 45.00, 'amount' => 90.00],
            ['desc' => 'Power Bank 10000mAh', 'qty' => 1, 'price' => 89.00, 'amount' => 89.00],
            ['desc' => 'Phone Case Premium', 'qty' => 1, 'price' => 35.00, 'amount' => 35.00]
        ],
        'subtotal' => 214.00,
        'tax' => 12.84,
        'shipping' => 8.00,
        'total' => 234.84,
        'payment_method' => 'Credit Card (**** 1234)',
        'transaction_id' => 'TXN-20240323-001'
    ]
];

// Get receipt data
$receipt = isset($receipt_data[$invoice_no]) ? $receipt_data[$invoice_no] : null;

if (!$receipt) {
    die('Receipt not found');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Receipt - <?php echo htmlspecialchars($receipt['id']); ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Public Sans', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #696cff;
            padding-bottom: 20px;
        }

        .company-logo {
            font-size: 32px;
            font-weight: 700;
            color: #696cff;
            margin-bottom: 5px;
        }

        .company-tagline {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .company-info {
            font-size: 12px;
            color: #888;
            line-height: 1.4;
        }

        .receipt-title {
            text-align: center;
            margin: 30px 0;
        }

        .receipt-number {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .receipt-date {
            font-size: 14px;
            color: #666;
        }

        .receipt-details {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            gap: 40px;
        }

        .detail-section {
            flex: 1;
        }

        .detail-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .detail-content {
            font-size: 14px;
            line-height: 1.6;
            color: #555;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            font-size: 14px;
        }

        .items-table th,
        .items-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .items-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #696cff;
        }

        .items-table .text-right {
            text-align: right;
        }

        .items-table .text-center {
            text-align: center;
        }

        .totals-section {
            background-color: #f8f9fa;
        }

        .totals-section td {
            font-weight: 500;
            border-bottom: 1px solid #ddd;
        }

        .grand-total {
            background-color: #696cff !important;
            color: white !important;
            font-weight: 700;
            font-size: 16px;
        }

        .payment-info {
            margin: 30px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #696cff;
        }

        .payment-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .payment-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 14px;
        }

        .payment-item {
            display: flex;
            justify-content: space-between;
        }

        .payment-label {
            font-weight: 500;
            color: #555;
        }

        .payment-value {
            color: #333;
        }

        .terms-section {
            margin: 30px 0;
            font-size: 12px;
            color: #666;
        }

        .terms-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .terms-list {
            line-height: 1.6;
        }

        .receipt-footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .thank-you {
            font-size: 18px;
            font-weight: 600;
            color: #696cff;
            margin-bottom: 10px;
        }

        .no-print {
            text-align: center;
            margin: 20px 0;
        }

        .print-btn {
            background-color: #696cff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            margin-right: 10px;
            transition: background-color 0.3s;
        }

        .print-btn:hover {
            background-color: #5f63f2;
        }

        .close-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .close-btn:hover {
            background-color: #5a6268;
        }

        /* Print Styles */
        @media print {
            body {
                background-color: white;
                padding: 0;
                font-size: 12px;
            }

            .receipt-container {
                box-shadow: none;
                margin: 0;
                padding: 20px;
                border-radius: 0;
            }

            .no-print {
                display: none !important;
            }

            .receipt-header {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }

            .company-logo {
                font-size: 28px;
            }

            .receipt-number {
                font-size: 20px;
            }

            .items-table th,
            .items-table td {
                padding: 8px 6px;
            }

            .payment-info {
                margin: 20px 0;
                padding: 15px;
            }

            .terms-section {
                margin: 20px 0;
            }

            .receipt-footer {
                margin-top: 30px;
                padding-top: 15px;
            }

            /* Ensure page breaks appropriately */
            .receipt-container {
                page-break-inside: avoid;
            }

            /* Remove any background colors that don't print well */
            .totals-section {
                background-color: #f5f5f5 !important;
            }

            .grand-total {
                background-color: #333 !important;
                color: white !important;
            }
        }

        /* Responsive design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .receipt-container {
                padding: 20px;
            }

            .receipt-details {
                flex-direction: column;
                gap: 20px;
            }

            .payment-details {
                grid-template-columns: 1fr;
            }

            .items-table {
                font-size: 12px;
            }

            .items-table th,
            .items-table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>

<body>
    <div class="receipt-container">
        <!-- Print/Close Buttons (hidden when printing) -->
        <div class="no-print">
            <button class="print-btn" onclick="printReceipt()">🖨️ Print Receipt</button>
            <button class="close-btn" onclick="window.close()">✕ Close Window</button>
        </div>

        <!-- Receipt Header -->
        <div class="receipt-header">
            <div class="company-logo">INVENTOMO</div>
            <div class="company-tagline">Inventory Management System</div>
            <div class="company-info">
                456 Business Park, Technology Avenue<br>
                Cyberjaya, Selangor 63000, Malaysia<br>
                Tel: +603-8888-9999 | Email: support@inventomo.com<br>
                Website: www.inventomo.com
            </div>
        </div>

        <!-- Receipt Title -->
        <div class="receipt-title">
            <div class="receipt-number">Receipt #<?php echo htmlspecialchars($receipt['id']); ?></div>
            <div class="receipt-date">Date: <?php echo date('F j, Y', strtotime($receipt['date'])); ?></div>
        </div>

        <!-- Receipt Details -->
        <div class="receipt-details">
            <div class="detail-section">
                <div class="detail-title">Billed To:</div>
                <div class="detail-content">
                    <strong><?php echo htmlspecialchars($receipt['customer']); ?></strong><br>
                    <?php echo htmlspecialchars($receipt['address']); ?><br>
                    Email: <?php echo htmlspecialchars($receipt['email']); ?><br>
                    <?php if (!empty($receipt['phone'])): ?>
                    Phone: <?php echo htmlspecialchars($receipt['phone']); ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-section">
                <div class="detail-title">Invoice Details:</div>
                <div class="detail-content">
                    <strong>Invoice #:</strong> <?php echo htmlspecialchars($receipt['invoice']); ?><br>
                    <strong>Receipt ID:</strong> <?php echo htmlspecialchars($receipt['id']); ?><br>
                    <strong>Issue Date:</strong> <?php echo date('F j, Y', strtotime($receipt['date'])); ?><br>
                    <strong>Due Date:</strong> <?php echo date('F j, Y', strtotime($receipt['date'])); ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Description</th>
                    <th style="width: 10%;" class="text-center">Qty</th>
                    <th style="width: 20%;" class="text-right">Unit Price</th>
                    <th style="width: 20%;" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receipt['items'] as $index => $item): ?>
                <tr>
                    <td class="text-center"><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($item['desc']); ?></td>
                    <td class="text-center"><?php echo $item['qty']; ?></td>
                    <td class="text-right">RM <?php echo number_format($item['price'], 2); ?></td>
                    <td class="text-right">RM <?php echo number_format($item['amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Totals Section -->
                <tr class="totals-section">
                    <td colspan="4" class="text-right"><strong>Subtotal:</strong></td>
                    <td class="text-right">RM <?php echo number_format($receipt['subtotal'], 2); ?></td>
                </tr>
                <tr class="totals-section">
                    <td colspan="4" class="text-right"><strong>Tax (6% SST):</strong></td>
                    <td class="text-right">RM <?php echo number_format($receipt['tax'], 2); ?></td>
                </tr>
                <tr class="totals-section">
                    <td colspan="4" class="text-right"><strong>Shipping:</strong></td>
                    <td class="text-right">RM <?php echo number_format($receipt['shipping'], 2); ?></td>
                </tr>
                <tr class="grand-total">
                    <td colspan="4" class="text-right"><strong>TOTAL:</strong></td>
                    <td class="text-right"><strong>RM <?php echo number_format($receipt['total'], 2); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <!-- Payment Information -->
        <div class="payment-info">
            <div class="payment-title">Payment Information</div>
            <div class="payment-details">
                <div class="payment-item">
                    <span class="payment-label">Payment Method:</span>
                    <span class="payment-value"><?php echo htmlspecialchars($receipt['payment_method']); ?></span>
                </div>
                <div class="payment-item">
                    <span class="payment-label">Transaction ID:</span>
                    <span class="payment-value"><?php echo htmlspecialchars($receipt['transaction_id']); ?></span>
                </div>
                <div class="payment-item">
                    <span class="payment-label">Payment Status:</span>
                    <span class="payment-value" style="color: #28a745; font-weight: 600;">Completed</span>
                </div>
                <div class="payment-item">
                    <span class="payment-label">Payment Date:</span>
                    <span class="payment-value"><?php echo date('F j, Y', strtotime($receipt['date'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Terms and Conditions -->
        <div class="terms-section">
            <div class="terms-title">Terms & Conditions:</div>
            <div class="terms-list">
                1. All sales are final unless defective upon receipt.<br>
                2. Returns must be initiated within 14 days of purchase with original receipt.<br>
                3. Warranty terms apply as per manufacturer specifications.<br>
                4. For support inquiries, contact us at support@inventomo.com or +603-8888-9999.<br>
                5. This receipt serves as proof of purchase and warranty document.
            </div>
        </div>

        <!-- Footer -->
        <div class="receipt-footer">
            <div class="thank-you">Thank you for your business!</div>
            <p>This is a computer-generated receipt and does not require a signature.</p>
            <p>For questions about this receipt, email support@inventomo.com or call +603-8888-9999</p>
            <p style="margin-top: 10px; font-weight: 500;">
                Generated on <?php echo date('F j, Y \a\t g:i A'); ?>
            </p>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional - uncomment if you want auto-print)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 1000);
        // };

        // Print function
        function printReceipt() {
            window.print();
        }

        // Handle print dialog events
        window.addEventListener('beforeprint', function() {
            document.title = 'Receipt - <?php echo htmlspecialchars($receipt['id']); ?>';
        });

        window.addEventListener('afterprint', function() {
            // Optional: Ask if user wants to close the window after printing
            if (confirm('Receipt printed successfully. Do you want to close this window?')) {
                window.close();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+P or Cmd+P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printReceipt();
            }
            
            // Escape to close window
            if (e.key === 'Escape') {
                window.close();
            }
        });

        // Prevent accidental page refresh
        window.addEventListener('beforeunload', function(e) {
            // Only show confirmation if user is navigating away, not printing
            if (event.clientY < 0) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
    </script>
</body>
</html>