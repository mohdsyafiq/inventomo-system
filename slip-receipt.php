<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receipt</title>
  <meta name="description" content="Simple Receipt Design" />
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 20px;
    }

    .download-btn {
      background: white;
      color: #667eea;
      border: none;
      padding: 12px 24px;
      border-radius: 25px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
      margin-bottom: 20px;
      font-size: 14px;
    }

    .download-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .receipt {
      width: 320px;
      background: white;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.1);
      position: relative;
      overflow: hidden;
    }

    .receipt::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .logo {
      text-align: center;
      margin-bottom: 16px;
    }

    .logo h1 {
      color: #667eea;
      font-size: 24px;
      font-weight: 700;
      letter-spacing: -0.5px;
    }

    .receipt-id {
      text-align: center;
      color: #666;
      font-size: 12px;
      margin-bottom: 8px;
    }

    .datetime {
      text-align: center;
      color: #888;
      font-size: 11px;
      margin-bottom: 20px;
    }

    .divider {
      height: 1px;
      background: #eee;
      margin: 16px 0;
    }

    .billing-info {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 20px;
      font-size: 11px;
    }

    .bill-to, .bill-from {
      line-height: 1.4;
    }

    .bill-to strong, .bill-from strong {
      color: #333;
      font-size: 12px;
      display: block;
      margin-bottom: 4px;
    }

    .bill-to div, .bill-from div {
      color: #666;
    }

    .items-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 16px;
      font-size: 12px;
    }

    .items-table th {
      background: #f8f9fa;
      padding: 8px 6px;
      text-align: left;
      color: #555;
      font-weight: 600;
      border-bottom: 2px solid #eee;
    }

    .items-table td {
      padding: 8px 6px;
      border-bottom: 1px solid #f0f0f0;
    }

    .items-table tr:last-child td {
      border-bottom: none;
    }

    .qty, .price, .amount {
      text-align: right;
    }

    .totals {
      border-top: 2px solid #eee;
      padding-top: 12px;
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
      font-size: 12px;
    }

    .total-row.final {
      font-weight: 700;
      font-size: 14px;
      color: #333;
      border-top: 1px solid #ddd;
      padding-top: 8px;
      margin-top: 8px;
    }

    .total-row span:first-child {
      color: #666;
    }

    .total-row.final span:first-child {
      color: #333;
    }

    @media print {
      body {
        background: white;
        padding: 0;
      }
      .download-btn {
        display: none;
      }
      .receipt {
        box-shadow: none;
        border: 1px solid #ddd;
      }
    }
  </style>
</head>
<body>
  <button class="download-btn" onclick="downloadPDF()">📄 Download PDF</button>

  <div class="receipt" id="receipt">
    <div class="logo">
      <h1>inventomo</h1>
    </div>
    
    <div class="receipt-id">Receipt #NV-2025-00123</div>
    <div class="datetime" id="datetime"></div>
    
    <div class="divider"></div>
    
    <div class="billing-info">
      <div class="bill-to">
        <strong>Bill To:</strong>
        <div>Aminah Binti Ali</div>
        <div>123 Taman Indah</div>
        <div>Jalan Hancur</div>
        <div>Shah Alam, Selangor</div>
        <div>aminah@gmail.com</div>
      </div>
      <div class="bill-from">
        <strong>From:</strong>
        <div>inventomo Inc.</div>
        <div>456 Hup Seng</div>
        <div>Silicon Valley</div>
        <div>Subang Jaya, Selangor</div>
        <div>support@inventomo.com</div>
      </div>
    </div>

    <table class="items-table">
      <thead>
        <tr>
          <th>Item</th>
          <th class="qty">Qty</th>
          <th class="price">Price</th>
          <th class="amount">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Mouse Wireless</td>
          <td class="qty">2</td>
          <td class="price">RM50.00</td>
          <td class="amount">RM100.00</td>
        </tr>
        <tr>
          <td>Cable HDMI</td>
          <td class="qty">1</td>
          <td class="price">RM75.00</td>
          <td class="amount">RM75.00</td>
        </tr>
      </tbody>
    </table>

    <div class="totals">
      <div class="total-row">
        <span>Subtotal:</span>
        <span>RM175.00</span>
      </div>
      <div class="total-row">
        <span>Tax (10%):</span>
        <span>RM17.50</span>
      </div>
      <div class="total-row final">
        <span>Total:</span>
        <span>RM192.50</span>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script>
    // Set current date and time
    const now = new Date();
    const options = {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    };
    document.getElementById('datetime').textContent = now.toLocaleString('en-GB', options);

    function downloadPDF() {
      const element = document.getElementById('receipt');
      const opt = {
        margin: 5,
        filename: 'receipt-inventomo.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
          scale: 3,
          useCORS: true,
          letterRendering: true
        },
        jsPDF: { 
          unit: 'mm', 
          format: [80, 120], 
          orientation: 'portrait'
        }
      };
      
      html2pdf().set(opt).from(element).save();
    }
  </script>
</body>
</html>
