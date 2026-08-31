<?php
// ============================================================
// VIEW INVOICE - Admin
// ============================================================
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: invoice.php");
    exit();
}

$query = "SELECT * FROM invoices WHERE id = $id";
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: invoice.php");
    exit();
}
$invoice = mysqli_fetch_assoc($result);
$items = json_decode($invoice['items'], true);

function safeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo safeHtml($invoice['invoice_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family:'Poppins',sans-serif; background:#050e0c; color:#fff; padding:40px 20px; }
        .invoice-box { max-width:800px; margin:0 auto; background:#0f241c; border:1px solid #1e4538; border-radius:16px; padding:40px; box-shadow:0 15px 40px rgba(0,0,0,0.6); }
        .header { text-align:center; border-bottom:2px solid #1e4538; padding-bottom:20px; margin-bottom:20px; }
        .header h1 { font-family:'Playfair Display',serif; font-size:36px; color:#D4AF37; }
        .header p { color:#888; }
        .info { display:grid; grid-template-columns:1fr 1fr; gap:10px 30px; margin-bottom:20px; }
        .info .label { color:#888; font-size:13px; }
        .info .value { color:#fff; font-weight:500; }
        table { width:100%; border-collapse:collapse; margin:20px 0; }
        table th { background:#1e4538; color:#D4AF37; padding:10px 14px; text-align:left; font-size:13px; text-transform:uppercase; }
        table td { padding:10px 14px; border-bottom:1px solid rgba(30,69,56,0.3); color:#b0c4b1; }
        .totals { text-align:right; margin-top:20px; }
        .totals .line { display:flex; justify-content:flex-end; gap:30px; padding:4px 0; }
        .totals .line .label { color:#888; }
        .totals .line .amount { color:#fff; font-weight:500; }
        .totals .grand { font-size:22px; border-top:2px solid #D4AF37; padding-top:10px; margin-top:10px; }
        .totals .grand .label { color:#D4AF37; font-weight:700; }
        .totals .grand .amount { color:#D4AF37; font-weight:700; }
        .footer { text-align:center; margin-top:30px; padding-top:20px; border-top:1px solid #1e4538; color:#555; font-size:13px; }
        .btn-back { display:inline-block; padding:10px 25px; background:linear-gradient(135deg,#D4AF37,#b8962e); color:#0a1914; border:none; border-radius:50px; font-weight:600; text-decoration:none; margin-bottom:20px; transition:0.3s; }
        .btn-back:hover { background:#fff; transform:translateY(-2px); }
        .btn-pdf { display:inline-block; padding:10px 25px; background:linear-gradient(135deg,#3498db,#2980b9); color:#fff; border:none; border-radius:50px; font-weight:600; text-decoration:none; margin-bottom:20px; transition:0.3s; margin-left:10px; }
        .btn-pdf:hover { background:#fff; color:#2980b9; transform:translateY(-2px); }
        .status { display:inline-block; padding:4px 16px; border-radius:20px; font-size:13px; font-weight:600; }
        .status-paid { background:rgba(46,204,113,0.15); color:#2ecc71; border:1px solid rgba(46,204,113,0.3); }
        .status-pending { background:rgba(241,196,15,0.15); color:#f1c40f; border:1px solid rgba(241,196,15,0.3); }
        .status-cancelled { background:rgba(231,76,60,0.15); color:#e74c3c; border:1px solid rgba(231,76,60,0.3); }
        @media (max-width:600px) {
            .invoice-box { padding:20px; }
            .info { grid-template-columns:1fr; }
            .totals .line { flex-direction:column; align-items:flex-end; gap:2px; }
        }
    </style>
</head>
<body>

    <div style="max-width:800px; margin:0 auto; text-align:center; margin-bottom:20px;">
        <a href="invoice.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Invoices</a>
        <a href="invoice_generate.php?id=<?php echo $invoice['id']; ?>" class="btn-pdf" target="_blank"><i class="fas fa-download"></i> Download PDF</a>
    </div>

    <div class="invoice-box">

        <div class="header">
            <h1>✨ GOURMET</h1>
            <p>Fine Dining Restaurant</p>
            <p style="margin-top:5px; font-size:13px; color:#666;">123 Main Street, Colombo | +94 77 123 4567 | info@gourmet.com</p>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
            <h2 style="color:#fff; font-family:'Playfair Display',serif;">Invoice #<?php echo safeHtml($invoice['invoice_number']); ?></h2>
            <span class="status <?php echo strtolower($invoice['payment_status']) == 'paid' ? 'status-paid' : (strtolower($invoice['payment_status']) == 'cancelled' ? 'status-cancelled' : 'status-pending'); ?>">
                <?php echo safeHtml($invoice['payment_status']); ?>
            </span>
        </div>

        <div class="info">
            <div>
                <div class="label">Invoice Date</div>
                <div class="value"><?php echo date('d M Y, h:i A', strtotime($invoice['invoice_date'])); ?></div>
            </div>
            <div>
                <div class="label">Payment Method</div>
                <div class="value"><?php echo safeHtml($invoice['payment_method'] ?? 'N/A'); ?></div>
            </div>
            <div>
                <div class="label">Bill To</div>
                <div class="value"><?php echo safeHtml($invoice['customer_name']); ?></div>
                <div style="color:#888; font-size:13px;"><?php echo safeHtml($invoice['customer_email']); ?></div>
                <?php if (!empty($invoice['customer_phone'])): ?>
                    <div style="color:#888; font-size:13px;"><?php echo safeHtml($invoice['customer_phone']); ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div class="label">Booking ID</div>
                <div class="value">#<?php echo safeHtml($invoice['booking_id']); ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Amount (LKR)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo safeHtml($item['description'] ?? 'Item'); ?></td>
                            <td style="text-align:center;"><?php echo (int)($item['qty'] ?? 1); ?></td>
                            <td style="text-align:right;"><?php echo number_format(($item['price'] ?? 0) * ($item['qty'] ?? 1), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align:center; color:#666;">No items</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="line">
                <span class="label">Subtotal</span>
                <span class="amount">LKR <?php echo number_format($invoice['subtotal'], 2); ?></span>
            </div>
            <div class="line">
                <span class="label">Tax</span>
                <span class="amount">LKR <?php echo number_format($invoice['tax'], 2); ?></span>
            </div>
            <div class="line grand">
                <span class="label">TOTAL</span>
                <span class="amount">LKR <?php echo number_format($invoice['total'], 2); ?></span>
            </div>
        </div>

        <div class="footer">
            <p><i class="fas fa-crown" style="color:#D4AF37;"></i> Thank you for dining with us!</p>
            <p style="margin-top:5px; font-size:11px;">This is a system generated invoice.</p>
        </div>

    </div>

</body>
</html>