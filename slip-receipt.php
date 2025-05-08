<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receipt</title>

<meta name="description" content="" />

<!-- Favicon --> 
<link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />

  <style>
    body {
      margin: 0;
      background-color: #f0f0f0;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      height: 100vh;
      font-family: Arial, sans-serif;
      gap: 20px;
    }

    .a6-paper {
      width: 105mm;
      height: 148mm;
      background-color: #fff;
      padding: 15px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
      box-sizing: border-box;
      overflow: auto;
    }

    .header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header-row h2 {
      margin: 0;
      font-size: 18px;
    }

    .download-button {
      padding: 8px 18px;
      font-size: 14px;
      border: 1px solid #007bff;
      background-color: transparent;
      color: #007bff;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .download-button:hover {
      background-color: #007bff;
      color: white;
    }

    .center {
      text-align: center;
    }

    .row {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
    }

    .section {
      margin-bottom: 10px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      margin-top: 10px;
    }

    th, td {
      border: 1px solid #ccc;
      padding: 6px;
    }

    th {
      text-align: left;
    }

    td:nth-child(3),
    td:nth-child(4),
    td:nth-child(5),
    .totals-row td {
      text-align: left;
    }

    .totals-row td:first-child {
      text-align: right;
    }

    .bold {
      font-weight: bold;
    }

    #datetime {
      font-size: 12px;
      color: #555;
      margin-top: 4px;
      text-align: center;
    }
  </style>
</head>
<body>
  <!-- Download button moved outside the receipt -->
  <button class="download-button" onclick="downloadPDF()">Download Receipt PDF</button>

<div class="header-row">
  
  <div class="a6-paper" id="receipt">
  <div style="text-align: center;">
  <img
    src="assets/img/icons/brands/inventomo-slip.png"
    width="130"
    alt="Inventomo Logo"
  >
</div>
<p class="center" style="font-size: 0.7rem;">Receipt ID: #NV-2025-00123</p>

    <p id="datetime"></p>
    <hr style="border: none; border-top: 1px solid #ccc; margin: 10px 0;">
    <div class="section row">
      <div>
        <strong>Billed To:</strong><br>
        Aminah Binti Ali<br>
        123 Taman Indah<br>
        Jalan Hancur<br>
        Shah Alam, Selangor<br>
        aminah@gmail.com
      </div>
      <div>
        <strong>iventomo Inc.</strong><br>
        456 Hup Seng<br>
        Silicon Valley<br>
        Subang Jaya, Selangor<br>
        support@iventomo.com
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Description</th>
          <th>Qty</th>
          <th>Unit Price</th>
          <th>Amount</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>1</td>
          <td>Mouse Wireless</td>
          <td>2</td>
          <td>RM50.00</td>
          <td>RM100.00</td>
        </tr>
        <tr>
          <td>2</td>
          <td>Cable HDMI</td>
          <td>1</td>
          <td>RM75.00</td>
          <td>RM75.00</td>
        </tr>
        <tr class="totals-row">
          <td colspan="4" class="bold">Subtotal:</td>
          <td>RM175.00</td>
        </tr>
        <tr class="totals-row">
          <td colspan="4" class="bold">Tax (10%):</td>
          <td>RM17.50</td>
        </tr>
        <tr class="totals-row">
          <td colspan="4" class="bold">Total:</td>
          <td class="bold">RM192.50</td>
        </tr>
      </tbody>
    </table>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script>
    const now = new Date();
    const options = {
      year: 'numeric', month: 'long', day: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit'
    };
    document.getElementById('datetime').textContent = now.toLocaleString('en-GB', options);

    function downloadPDF() {
      const element = document.getElementById('receipt');
      const opt = {
        margin: 0,
        filename: 'receipt.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'mm', format: 'a6', orientation: 'portrait' }
      };
      html2pdf().set(opt).from(element).save();
    }
  </script>

</body>
</html>
