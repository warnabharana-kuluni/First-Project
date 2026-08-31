<?php
// ============================================================
// INVOICE FUNCTIONS - Generate & Manage Invoices
// ============================================================

function generateInvoiceNumber($reservation_id) {
    $year = date('Y');
    $month = date('m');
    return 'INV-' . $year . '-' . $month . '-' . str_pad($reservation_id, 4, '0', STR_PAD_LEFT);
}

function getInvoiceData($conn, $reservation_id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM reservations WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $reservation_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $data;
}

function getCustomerInvoices($conn, $email) {
    $query = "SELECT * FROM reservations WHERE email = '$email' AND invoice_generated = 1 ORDER BY id DESC";
    $result = mysqli_query($conn, $query);
    $invoices = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $invoices[] = $row;
    }
    return $invoices;
}

function markInvoiceGenerated($conn, $reservation_id) {
    $invoice_num = generateInvoiceNumber($reservation_id);
    $update = "UPDATE reservations SET invoice_number = '$invoice_num', invoice_generated = 1 WHERE id = $reservation_id";
    return mysqli_query($conn, $update);
}