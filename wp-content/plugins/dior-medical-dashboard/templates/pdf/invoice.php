<?php
/**
 * Dior Medical invoice PDF view.
 * Layout follows the approved blue/gold invoice HTML reference.
 *
 * Expected variables from Dior_Invoice_Pdf_Service:
 * $invoice_pdf, $auto_download
 */
if (!defined('ABSPATH')) {
    exit;
}

$invoice = is_array($invoice_pdf ?? null) ? $invoice_pdf : [];
$patient = is_array($invoice['patient'] ?? null) ? $invoice['patient'] : [];
$company = is_array($invoice['company'] ?? null) ? $invoice['company'] : [];
$payment = is_array($invoice['payment'] ?? null) ? $invoice['payment'] : [];
$doctor = is_array($invoice['doctor'] ?? null) ? $invoice['doctor'] : [];
$currency = strtoupper((string) ($invoice['currency'] ?? 'USD'));
$symbols = ['USD' => '$', 'INR' => '₹', 'GBP' => '£', 'EUR' => '€', 'CAD' => '$', 'AUD' => '$'];
$currency_symbol = $symbols[$currency] ?? ($currency . ' ');
$format_money = static function ($amount) use ($currency_symbol) {
    return $currency_symbol . ' ' . number_format((float) $amount, 2, '.', ',');
};
$format_date = static function ($date) {
    if (!$date) {
        return '—';
    }
    $timestamp = strtotime((string) $date);
    return $timestamp ? date_i18n('j M Y', $timestamp) : '—';
};
$invoice_number = (string) ($invoice['id'] ?? '');
$status = trim((string) ($invoice['status'] ?? 'Pending'));
$status_label = $status !== '' ? ucfirst($status) : 'Pending';
$issued_at = $format_date($invoice['issued_at'] ?? '');
$due_at = $format_date($invoice['due_at'] ?? '');
$amount = (float) ($invoice['amount'] ?? 0);
$subtotal = (float) ($invoice['subtotal'] ?? $amount);
$discount = (float) ($invoice['discount'] ?? 0);
$tax = (float) ($invoice['tax'] ?? 0);
$total = (float) ($invoice['total'] ?? $amount);
$logo_url = (string) ($company['logo'] ?? '');
$company_name = trim((string) ($company['name'] ?? 'Dior Medical'));
$company_address = trim((string) ($company['address'] ?? ''));
$company_phone = trim((string) ($company['phone'] ?? ''));
$company_email = trim((string) ($company['email'] ?? ''));
$company_url = trim((string) ($company['url'] ?? home_url('/')));
$patient_name = trim((string) ($patient['name'] ?? 'Patient'));
$patient_address = trim((string) ($patient['address'] ?? ''));
$patient_email = trim((string) ($patient['email'] ?? ''));
$patient_phone = trim((string) ($patient['phone'] ?? ''));
$patient_dob = trim((string) ($patient['dob'] ?? ''));
$patient_gender = trim((string) ($patient['gender'] ?? ''));
$payment_method = trim((string) ($payment['method'] ?? ''));
$transaction_id = trim((string) ($payment['transaction_id'] ?? ''));
$payment_date = $format_date($payment['paid_at'] ?? '');
$received_amount = (float) ($payment['amount'] ?? 0);
$description = trim((string) ($invoice['description'] ?? 'Medical services'));
if ($description === '') {
    $description = 'Medical services';
}
$currency_words = ['USD' => 'Dollars', 'INR' => 'Rupees', 'GBP' => 'Pounds', 'EUR' => 'Euros', 'CAD' => 'Dollars', 'AUD' => 'Dollars'];
$number_words = static function ($number) {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $convert = null;
    $convert = static function ($n) use (&$convert, $ones, $tens) {
        $n = (int) $n;
        if ($n < 20) return $ones[$n];
        if ($n < 100) return trim($tens[(int) ($n / 10)] . ' ' . $ones[$n % 10]);
        if ($n < 1000) return trim($ones[(int) ($n / 100)] . ' Hundred ' . $convert($n % 100));
        if ($n < 1000000) return trim($convert((int) ($n / 1000)) . ' Thousand ' . $convert($n % 1000));
        if ($n < 1000000000) return trim($convert((int) ($n / 1000000)) . ' Million ' . $convert($n % 1000000));
        return (string) $n;
    };
    return $convert(max(0, (int) $number));
};
$whole_amount = (int) floor(max(0, $total));
$cents = (int) round(($total - $whole_amount) * 100);
$amount_words = trim($number_words($whole_amount) . ' ' . ($currency_words[$currency] ?? $currency) . ($cents ? ' and ' . $number_words($cents) . ' Cents' : '') . ' Only');
$auto_download = !empty($auto_download);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html('Invoice ' . $invoice_number . ' | ' . $company_name); ?></title>
<style>
:root{--navy:#063b68;--blue:#2587c7;--pale:#eaf4fc;--line:#b8d9f2;--text:#17395b;--muted:#5b7187;--gold:#d9ad68;--green:#d8f4e9;--green-text:#167a5a}
*{box-sizing:border-box}
html,body{margin:0;padding:0;background:#edf3f7;color:var(--text);font-family:Arial,Helvetica,sans-serif}
.actions{width:100%;max-width:1060px;margin:14px auto;display:flex;justify-content:flex-end;gap:10px}
button{border:0;border-radius:5px;padding:11px 16px;font-weight:700;cursor:pointer}
.print-btn{background:var(--navy);color:#fff}
.invoice{position:relative;isolation:isolate;overflow:hidden;width:100%;max-width:1060px;margin:0 auto 24px;padding:30px 38px 24px;background:#fff;box-shadow:0 8px 30px #183a5514}
.invoice>*{position:relative;z-index:1}
.watermark{position:absolute;z-index:0!important;pointer-events:none;top:32%;left:50%;transform:translate(-50%,-50%);width:min(58%,590px);height:auto;max-height:480px;opacity:.055;object-fit:contain}
.header{display:flex;justify-content:space-between;align-items:center;gap:24px;padding-bottom:17px;border-bottom:4px solid transparent;border-image:linear-gradient(90deg,var(--gold) 0 18%,var(--blue) 18% 88%,var(--navy) 88%) 1}
.brand-logo{display:block;width:245px;max-width:100%;height:125px;object-fit:contain;object-position:left center}
.brand-wordmark{font-family:Georgia,'Times New Roman',serif;font-size:38px;color:var(--navy);font-weight:700;line-height:1.1}
.brand-wordmark span{color:var(--blue)}
.contact{font-size:13px;line-height:1.75;color:var(--text);text-align:right;overflow-wrap:anywhere}
.contact div{white-space:normal}
.contact b{display:inline-block;width:22px;color:var(--navy);font-size:15px}
.title-row{display:flex;justify-content:space-between;align-items:flex-start;gap:22px;margin:18px 0}
h1{margin:0;color:var(--navy);font-family:Georgia,'Times New Roman',serif;font-size:48px;line-height:1.05;letter-spacing:.5px}
.kicker{margin-top:7px;color:var(--navy);font-size:15px;letter-spacing:5px}
.meta{width:375px;max-width:50%;border:1px solid var(--line);border-radius:5px;background:#f7fbff;padding:10px 15px;font-size:13px;line-height:1.8}
.meta-row{display:grid;grid-template-columns:126px 12px minmax(0,1fr);gap:3px}
.meta-row strong{overflow-wrap:anywhere}
.paid{display:inline-block;padding:1px 11px;border-radius:16px;background:var(--green);color:var(--green-text);font-size:12px;font-weight:700}
.details{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}
.panel{border:1px solid var(--line);border-radius:4px;overflow:hidden;background:rgba(255,255,255,.88);break-inside:avoid}
.panel-title{padding:9px 15px;background:rgba(226,240,252,.92);border-bottom:1px solid var(--line);color:var(--navy);font-size:13px;font-weight:800;letter-spacing:.4px}
.panel-body{padding:11px 15px 13px;font-size:13px;line-height:1.65;min-height:118px;overflow-wrap:anywhere}
.panel-body strong{font-weight:700}
.patient-row{display:grid;grid-template-columns:108px 10px minmax(0,1fr);gap:3px}
.table-wrap{width:100%;overflow:visible}
table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12.5px;background:rgba(255,255,255,.9)}
th{padding:10px 8px;text-align:left;color:var(--navy);background:rgba(226,240,252,.94);font-weight:700;border:1px solid var(--line);overflow-wrap:anywhere}
td{padding:10px 8px;border:1px solid var(--line);line-height:1.35;overflow-wrap:anywhere;vertical-align:top}
th:first-child,td:first-child{text-align:center;width:6%}
th:nth-child(2),td:nth-child(2){width:24%}
th:nth-child(3),td:nth-child(3){width:30%}
th:nth-child(4),td:nth-child(4){text-align:center;width:8%}
th:nth-child(5),td:nth-child(5){text-align:right;width:16%}
th:nth-child(6),td:nth-child(6){text-align:right;width:16%}
.totals{width:44%;min-width:280px;margin:12px 0 12px auto;border:1px solid var(--line);border-radius:4px;overflow:hidden;background:rgba(247,251,255,.94);break-inside:avoid}
.total-line{display:flex;justify-content:space-between;gap:12px;padding:7px 13px;border-bottom:1px solid #d8e7f3;font-size:13px}
.total-line:last-child{border-bottom:0}
.total-line.final{background:#dceefb;color:var(--navy);font-size:17px;font-weight:800}
.amount-words{padding:9px 3px 12px;font-size:13px;line-height:1.5;overflow-wrap:anywhere}
.amount-words strong{color:var(--navy);margin-right:8px}
.bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:4px}
.bottom-grid .panel-body{min-height:112px}
.notes{margin:0;padding-left:18px}
.notes li{padding-left:3px;margin-bottom:2px}
.footer{display:grid;grid-template-columns:150px minmax(0,1fr) 220px;align-items:center;gap:14px;margin-top:18px;padding-top:10px;border-top:4px solid transparent;border-image:linear-gradient(90deg,var(--gold) 0 18%,var(--blue) 18% 88%,var(--navy) 88%) 1;break-inside:avoid}
.footer-logo{width:135px;height:65px;object-fit:contain;object-position:left center}
.thanks{text-align:center;font-size:13px;line-height:1.65;color:var(--navy)}
.thanks strong{font-weight:800}
.footer-contact{font-size:11px;line-height:1.8;justify-self:end;overflow-wrap:anywhere}
.footer-contact div{white-space:normal}
.footer-contact b{display:inline-block;width:18px;color:var(--navy)}
@page{size:A4 portrait;margin:8mm}
@media(max-width:700px){
 body{padding:8px}
 .invoice{padding:18px 14px}
 .header,.title-row{flex-direction:column;align-items:flex-start;gap:12px}
 .brand-logo{width:200px;height:100px}
 .contact{text-align:left;font-size:12px}
 .meta{width:100%;max-width:none}
 h1{font-size:38px}
 .kicker{font-size:12px;letter-spacing:4px}
 .details,.bottom-grid{grid-template-columns:1fr}
 .totals{width:100%;min-width:0}
 .footer{grid-template-columns:1fr;justify-items:start;text-align:left;gap:8px}
 .thanks{text-align:left}
 .footer-contact{justify-self:start}
 table{font-size:10px}
 th,td{padding:7px 5px}
}
@media print{
 html,body{padding:0!important;margin:0!important;background:#fff!important}
 .actions{display:none!important}
 .invoice{width:100%;max-width:none;margin:0;padding:7mm 6mm;box-shadow:none;overflow:visible}
 .watermark{position:absolute;opacity:.055}
 .panel,.meta,.totals,table{print-color-adjust:exact;-webkit-print-color-adjust:exact}
 .table-wrap{overflow:visible}
 .footer{break-inside:avoid}
}
</style>
</head>
<body>
<div class="actions"><button class="print-btn" type="button" onclick="window.print()">Print / Save as PDF</button><button class="print-btn" id="download-invoice-pdf" type="button">Download PDF</button></div>
<main class="invoice" id="invoice-pdf-content">
  <?php if ($logo_url !== ''): ?><img class="watermark" src="<?php echo esc_url($logo_url); ?>" alt="" aria-hidden="true"><?php endif; ?>
  <header class="header">
    <div class="brand">
      <?php if ($logo_url !== ''): ?><img class="brand-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($company_name); ?>"><?php else: ?><div class="brand-wordmark">Dior <span>Medical</span></div><?php endif; ?>
    </div>
    <div class="contact">
      <?php if ($company_address !== ''): ?><div><b>⌖</b> <?php echo esc_html($company_address); ?></div><?php endif; ?>
      <?php if ($company_phone !== ''): ?><div><b>☎</b> <?php echo esc_html($company_phone); ?></div><?php endif; ?>
      <?php if ($company_email !== ''): ?><div><b>✉</b> <?php echo esc_html($company_email); ?></div><?php endif; ?>
      <?php if ($company_url !== ''): ?><div><b>◎</b> <?php echo esc_html(preg_replace('#^https?://#', '', $company_url)); ?></div><?php endif; ?>
    </div>
  </header>

  <section class="title-row">
    <div><h1>INVOICE</h1><div class="kicker">MEDICAL SERVICES</div></div>
    <div class="meta">
      <div class="meta-row"><span>Invoice No.</span><span>:</span><strong><?php echo esc_html($invoice_number); ?></strong></div>
      <div class="meta-row"><span>Invoice Date</span><span>:</span><strong><?php echo esc_html($issued_at); ?></strong></div>
      <div class="meta-row"><span>Due Date</span><span>:</span><strong><?php echo esc_html($due_at); ?></strong></div>
      <div class="meta-row"><span>Payment Status</span><span>:</span><strong><span class="paid"><?php echo esc_html($status_label); ?></span></strong></div>
    </div>
  </section>

  <section class="details">
    <div class="panel"><div class="panel-title">BILL TO</div><div class="panel-body">
      <strong><?php echo esc_html($patient_name); ?></strong><br>
      <?php if ($patient_address !== ''): echo nl2br(esc_html($patient_address)); ?><br><?php endif; ?>
      <?php if ($patient_email !== ''): ?>Email: <?php echo esc_html($patient_email); ?><br><?php endif; ?>
      <?php if ($patient_phone !== ''): ?>Phone: <?php echo esc_html($patient_phone); ?><?php endif; ?>
    </div></div>
    <div class="panel"><div class="panel-title">PATIENT DETAILS</div><div class="panel-body">
      <div class="patient-row"><span>Patient Name</span><span>:</span><strong><?php echo esc_html($patient_name); ?></strong></div>
      <div class="patient-row"><span>Patient ID</span><span>:</span><strong><?php echo esc_html($patient['id'] ?? '—'); ?></strong></div>
      <div class="patient-row"><span>Date of Birth</span><span>:</span><strong><?php echo esc_html($patient_dob !== '' ? $format_date($patient_dob) : '—'); ?></strong></div>
      <div class="patient-row"><span>Gender</span><span>:</span><strong><?php echo esc_html($patient_gender !== '' ? ucfirst($patient_gender) : '—'); ?></strong></div>
      <div class="patient-row"><span>Address</span><span>:</span><strong><?php echo esc_html($patient_address !== '' ? $patient_address : '—'); ?></strong></div>
    </div></div>
  </section>

  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Service / Item</th><th>Description</th><th>Qty</th><th>Unit Price (<?php echo esc_html($currency); ?>)</th><th>Amount (<?php echo esc_html($currency); ?>)</th></tr></thead>
    <tbody><tr>
      <td>1</td><td><?php echo esc_html($description); ?></td><td><?php echo esc_html($invoice['appointment_uid'] !== '' ? 'Appointment ' . $invoice['appointment_uid'] : $description); ?></td><td>1</td><td><?php echo esc_html(number_format($amount, 2, '.', ',')); ?></td><td><?php echo esc_html(number_format($amount, 2, '.', ',')); ?></td>
    </tr></tbody>
  </table></div>

  <section class="totals">
    <div class="total-line"><span>Subtotal</span><strong><?php echo esc_html($format_money($subtotal)); ?></strong></div>
    <div class="total-line"><span>Discount</span><span><?php echo esc_html($format_money($discount)); ?></span></div>
    <div class="total-line"><span>Tax</span><span><?php echo esc_html($format_money($tax)); ?></span></div>
    <div class="total-line final"><span>Total Amount</span><span><?php echo esc_html($format_money($total)); ?></span></div>
  </section>

  <div class="amount-words"><strong>Amount in Words:</strong> <?php echo esc_html($amount_words); ?></div>

  <section class="bottom-grid">
    <div class="panel"><div class="panel-title">PAYMENT DETAILS</div><div class="panel-body">
      <div class="patient-row"><span>Payment Method</span><span>:</span><span><?php echo esc_html($payment_method !== '' ? $payment_method : '—'); ?></span></div>
      <div class="patient-row"><span>Transaction ID</span><span>:</span><span><?php echo esc_html($transaction_id !== '' ? $transaction_id : '—'); ?></span></div>
      <div class="patient-row"><span>Payment Date</span><span>:</span><span><?php echo esc_html($payment_date); ?></span></div>
      <div class="patient-row"><span>Received Amount</span><span>:</span><strong><?php echo esc_html($format_money($received_amount)); ?></strong></div>
    </div></div>
    <div class="panel"><div class="panel-title">NOTES</div><div class="panel-body">
      <ol class="notes">
        <li>This invoice is generated for medical services provided.</li>
        <li>Please make the payment by the due date.</li>
        <li>For billing queries, please contact the clinic.</li>
        <li>This is a system-generated invoice. No signature required.</li>
      </ol>
    </div></div>
  </section>

  <footer class="footer">
    <?php if ($logo_url !== ''): ?><img class="footer-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($company_name); ?>"><?php else: ?><div class="brand-wordmark">Dior <span>Medical</span></div><?php endif; ?>
    <div class="thanks"><strong>Thank you for choosing <?php echo esc_html($company_name); ?>.</strong><br>Your health is our priority.</div>
    <div class="footer-contact">
      <?php if ($company_phone !== ''): ?><div><b>☎</b> <?php echo esc_html($company_phone); ?></div><?php endif; ?>
      <?php if ($company_email !== ''): ?><div><b>✉</b> <?php echo esc_html($company_email); ?></div><?php endif; ?>
      <?php if ($company_url !== ''): ?><div><b>◎</b> <?php echo esc_html(preg_replace('#^https?://#', '', $company_url)); ?></div><?php endif; ?>
    </div>
  </footer>
</main>
<script src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/js/html2pdf.bundle.min.js'); ?>"></script>
<script>
(function(){
  var download = <?php echo wp_json_encode($auto_download); ?>;
  var button = document.getElementById('download-invoice-pdf');
  function createPdf(){
    var element = document.getElementById('invoice-pdf-content');
    if (!element || typeof html2pdf !== 'function') {
      window.print();
      return;
    }
    var options = {
      margin: [8, 8, 8, 8],
      filename: 'Invoice_<?php echo esc_js(preg_replace('/[^A-Za-z0-9_-]/', '_', $invoice_number)); ?>.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollX: 0, scrollY: 0 },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      pagebreak: { mode: ['css', 'legacy'], avoid: ['.panel', '.totals', '.footer', 'tr'] }
    };
    html2pdf().set(options).from(element).save();
  }
  if (button) button.addEventListener('click', createPdf);
  if (download) window.addEventListener('load', function(){ setTimeout(createPdf, 250); });
})();
</script>
</body>
</html>
