<?php
// ============================================================
// GENERATE PDF INVOICE
// ============================================================
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fpdf.php';

// Get invoice ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Invalid invoice ID.');
}

// Fetch invoice data
$query = "SELECT * FROM invoices WHERE id = $id";
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    die('Invoice not found.');
}
$invoice = mysqli_fetch_assoc($result);

// Decode items
$items = json_decode($invoice['items'], true);
if (!$items) $items = [];

// ============================================================
// CREATE PDF - Custom Class
// ============================================================
class PDF extends FPDF
{
    function Header()
    {
        // Restaurant Logo - ඔබගේ logo path එක දෙන්න
        // $this->Image(__DIR__ . '/../assets/logo.png', 10, 6, 30);
        $this->SetFont('Arial', 'B', 22);
        $this->SetTextColor(212, 175, 55);
        $this->Cell(0, 10, 'GOURMET', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(136, 136, 136);
        $this->Cell(0, 5, 'Fine Dining Restaurant', 0, 1, 'C');
        $this->Ln(8);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(136, 136, 136);
        $this->Cell(0, 10, 'Thank you for dining with us!', 0, 0, 'C');
    }
}

// Create PDF object
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// ============================================================
// INVOICE HEADER
// ============================================================
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'INVOICE', 0, 1, 'C');
$pdf->Ln(3);

// Invoice Details
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell(40, 6, 'Invoice #:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(80, 6, $invoice['invoice_number'], 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell(30, 6, 'Date:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(40, 6, date('d M Y', strtotime($invoice['invoice_date'])), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell(40, 6, 'Status:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(80, 6, $invoice['payment_status'], 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell(30, 6, 'Payment:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(40, 6, $invoice['payment_method'] ?? 'N/A', 0, 1);

$pdf->Ln(5);

// ============================================================
// BILL TO
// ============================================================
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell(0, 8, 'Bill To:', 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, $invoice['customer_name'], 0, 1);
$pdf->Cell(0, 6, $invoice['customer_email'], 0, 1);
if (!empty($invoice['customer_phone'])) {
    $pdf->Cell(0, 6, 'Phone: ' . $invoice['customer_phone'], 0, 1);
}
$pdf->Ln(5);

// ============================================================
// TABLE HEADER
// ============================================================
$pdf->SetFillColor(212, 175, 55);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 11);

$pdf->Cell(100, 8, 'Description', 1, 0, 'L', true);
$pdf->Cell(30, 8, 'Qty', 1, 0, 'C', true);
$pdf->Cell(60, 8, 'Amount (LKR)', 1, 1, 'R', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetFillColor(245, 245, 245);

// ============================================================
// TABLE BODY
// ============================================================
$subtotal = 0;
if (!empty($items)) {
    foreach ($items as $item) {
        $desc = $item['description'] ?? 'Item';
        $qty = $item['qty'] ?? 1;
        $price = $item['price'] ?? 0;
        $line_total = $qty * $price;
        $subtotal += $line_total;
        
        $pdf->Cell(100, 7, $desc, 1, 0, 'L');
        $pdf->Cell(30, 7, $qty, 1, 0, 'C');
        $pdf->Cell(60, 7, number_format($line_total, 2), 1, 1, 'R');
    }
} else {
    $pdf->Cell(190, 7, 'No items found', 1, 1, 'C');
}

$pdf->Ln(3);

// ============================================================
// TOTALS
// ============================================================
$tax = $invoice['tax'] ?? 0;
$grand_total = $invoice['total'] ?? $subtotal;

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(50, 50, 50);

$pdf->Cell(130, 7, '', 0, 0);
$pdf->Cell(30, 7, 'Subtotal:', 0, 0, 'R');
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(30, 7, 'LKR ' . number_format($subtotal, 2), 0, 1, 'R');

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell(130, 7, '', 0, 0);
$pdf->Cell(30, 7, 'Tax:', 0, 0, 'R');
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(30, 7, 'LKR ' . number_format($tax, 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(212, 175, 55);
$pdf->Cell(130, 10, '', 0, 0);
$pdf->Cell(30, 10, 'TOTAL:', 0, 0, 'R');
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(212, 175, 55);
$pdf->Cell(30, 10, 'LKR ' . number_format($grand_total, 2), 0, 1, 'R');

// ============================================================
// FOOTER MESSAGE
// ============================================================
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 10);
$pdf->SetTextColor(136, 136, 136);
$pdf->Cell(0, 6, 'Thank you for your business!', 0, 1, 'C');
$pdf->Cell(0, 6, 'Please keep this invoice for your records.', 0, 1, 'C');

// ============================================================
// OUTPUT PDF
// ============================================================
$pdf->Output('I', 'Invoice_' . $invoice['invoice_number'] . '.pdf');
exit();