<?php
/**
 * RG PMS - PDF generation for quotations and invoices (FPDF)
 */
require_once __DIR__ . '/functions.php';

/**
 * Render a quotation (or invoice) PDF and send it to the browser for download.
 * @param array  $rows   line-item rows (description, quantity, unit, rate, amount)
 * @param array  $meta   header fields (doc_label, doc_number, project_name, client_name,
 *                       issue_date, valid_until/due_date, notes, vat_rate, subtotal, vat, total, status)
 */
function render_document_pdf(array $rows, array $meta): void {
    require_once __DIR__ . '/../assets/vendor/fpdf.php';

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetMargins(18, 18, 18);
    $pdf->AddPage();

    // ---------------- Letterhead ----------------
    // Wide banner logo. FPDF needs a filesystem path (not a URL).
    $logo = __DIR__ . '/../assets/img/logo-full.png';
    $logoBottom = 14;

    if (is_file($logo)) {
        $info  = @getimagesize($logo);
        $logoW = 55;                                                        // mm
        $logoH = ($info && $info[0] > 0) ? ($logoW * $info[1] / $info[0])   // maintain aspect ratio
                                          : 15;
        $pdf->Image($logo, 18, 14, $logoW, $logoH);
        $logoBottom = 14 + $logoH;
    }

    // Company contact strip — sits directly below the logo banner
    $pdf->SetY($logoBottom + 4);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->Cell(0, 5, 'Construction and Civil Engineering', 0, 1);
    $pdf->Cell(0, 5, COMPANY_PHONE . '  |  ' . COMPANY_EMAIL, 0, 1);
    $pdf->Cell(0, 5, COMPANY_ADDRESS, 0, 1);
    $pdf->Ln(2);

    // Divider line under letterhead
    $pdf->SetDrawColor(183, 121, 31);
    $pdf->SetLineWidth(0.8);
    $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
    $pdf->Ln(6);

    // ---------------- Title ----------------
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetTextColor(183, 121, 31);
    $pdf->Cell(0, 8, $meta['doc_label'], 0, 1, 'R');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(31, 41, 55);
    $pdf->Cell(0, 6, 'No. ' . $meta['doc_number'], 0, 1, 'R');
    $pdf->Ln(4);

    // ---------------- Parties ----------------
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(60, 6, 'Prepared for', 0, 0);
    $pdf->Cell(60, 6, 'Document details', 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(60, 5, $meta['client_name'], 0, 0);
    $pdf->Cell(60, 5, 'Project: ' . $meta['project_name'], 0, 1);
    $pdf->Cell(60, 5, '', 0, 0);
    $pdf->Cell(60, 5, 'Issue date: ' . nice_date($meta['issue_date']), 0, 1);
    if (!empty($meta['valid_until'])) {
        $pdf->Cell(60, 5, '', 0, 0);
        $pdf->Cell(60, 5, 'Valid until: ' . nice_date($meta['valid_until']), 0, 1);
    }
    if (!empty($meta['due_date'])) {
        $pdf->Cell(60, 5, '', 0, 0);
        $pdf->Cell(60, 5, 'Due date: ' . nice_date($meta['due_date']), 0, 1);
    }
    $pdf->Cell(60, 5, '', 0, 0);
    $pdf->Cell(60, 5, 'Status: ' . $meta['status'], 0, 1);
    $pdf->Ln(6);

    // ---------------- Line items table ----------------
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(31, 41, 55);
    $pdf->SetTextColor(255, 255, 255);
    $w = [90, 20, 24, 24, 34];
    $headers = ['Description', 'Qty', 'Unit', 'Rate (R)', 'Amount (R)'];
    foreach ($headers as $i => $h) {
        $pdf->Cell($w[$i], 8, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFont('Arial', '', 9);

    $fill = false;
    foreach ($rows as $row) {
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell($w[0], 7, mb_strimwidth((string)$row['description'], 0, 70, '…'), 'LR', 0, 'L', $fill);
        $pdf->Cell($w[1], 7, number_format((float)$row['quantity'], 2), 'LR', 0, 'R', $fill);
        $pdf->Cell($w[2], 7, (string)$row['unit'], 'LR', 0, 'C', $fill);
        $pdf->Cell($w[3], 7, number_format((float)$row['rate'], 2), 'LR', 0, 'R', $fill);
        $pdf->Cell($w[4], 7, number_format((float)$row['amount'], 2), 'LR', 0, 'R', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    // Totals
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($w[0] + $w[1] + $w[2], 7, '', 'T');
    $pdf->Cell($w[3], 7, 'Subtotal', 1, 0, 'R', true);
    $pdf->Cell($w[4], 7, number_format((float)$meta['subtotal'], 2), 1, 1, 'R', true);

    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell($w[0] + $w[1] + $w[2], 7, '', '');
    $pdf->Cell($w[3], 7, 'VAT (' . rtrim(rtrim(number_format((float)$meta['vat_rate'], 2), '0'), '.') . '%)', 1, 0, 'R');
    $pdf->Cell($w[4], 7, number_format((float)$meta['vat'], 2), 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(240, 163, 47);
    $pdf->Cell($w[0] + $w[1] + $w[2], 7, '', '');
    $pdf->Cell($w[3], 7, 'TOTAL', 1, 0, 'R', true);
    $pdf->Cell($w[4], 7, 'R ' . number_format((float)$meta['total'], 2), 1, 1, 'R', true);

    $pdf->Ln(6);
    if (!empty($meta['notes'])) {
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->MultiCell(0, 5, 'Notes: ' . $meta['notes']);
    }

    $pdf->Ln(8);
    $pdf->SetDrawColor(183, 121, 31);
    $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
    $pdf->Ln(3);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(156, 163, 175);
    $pdf->Cell(0, 4, 'Generated by ' . APP_SYSTEM_NAME . ' on ' . date('d M Y H:i'), 0, 1, 'C');

    $filename = str_replace(['/', '\\'], '-', $meta['doc_number']) . '.pdf';
    $pdf->Output('D', $filename);
    exit;
}