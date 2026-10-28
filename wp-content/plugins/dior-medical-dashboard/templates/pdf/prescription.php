<?php
/**
 * Dior Medical prescription PDF template.
 * Keep the clinical prescription layout isolated from dashboard markup.
 */
if (!defined('ABSPATH')) {
    exit;
}
$rx = is_array($rx_data ?? null) ? $rx_data : [];
$rx_items = is_array($rx['items'] ?? null) ? $rx['items'] : [];
$logo_id = (int) get_theme_mod('custom_logo');
$logo_url = $logo_id ? (string) wp_get_attachment_image_url($logo_id, 'full') : (string) get_option('dior_medical_logo_url', '');
$rx_auto_download = isset($_GET['dior_action']) && sanitize_key(wp_unslash($_GET['dior_action'])) === 'download_rx_pdf';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html('Prescription ' . ($rx['rx_id'] ?? '')); ?> | Dior Medical</title>
<style>
:root{--navy:#063b68;--blue:#2587c7;--pale:#eaf4fc;--line:#b8d9f2;--text:#17395b;--muted:#5b7187;--green:#d8f4e9;--green-text:#167a5a}
*{box-sizing:border-box}
body{margin:0;background:#edf3f7;color:var(--text);font-family:Arial,Helvetica,sans-serif}
.actions{max-width:900px;margin:14px auto;display:flex;justify-content:flex-end;gap:10px}
button{border:0;border-radius:5px;padding:11px 16px;font-weight:700;cursor:pointer;background:var(--navy);color:#fff}
.document{width:100%;max-width:900px;margin:0 auto 24px;padding:32px 38px;background:#fff;box-shadow:0 8px 30px #183a5514}
.header{display:flex;justify-content:space-between;align-items:center;gap:20px;padding-bottom:18px;border-bottom:4px solid transparent;border-image:linear-gradient(90deg,var(--blue),var(--navy)) 1}
.logo{max-width:220px;max-height:95px;object-fit:contain;object-position:left center}
.wordmark{font:700 34px Georgia,serif;color:var(--navy)}
.contact{text-align:right;font-size:12px;line-height:1.7;overflow-wrap:anywhere}
.title-row{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin:24px 0}
h1{margin:0;color:var(--navy);font-size:30px}
.kicker{margin-top:5px;color:var(--blue);font-size:12px;letter-spacing:3px}
.rx-meta{border:1px solid var(--line);background:#f7fbff;padding:12px 15px;min-width:210px;font-size:13px;line-height:1.8}
.panel{border:1px solid var(--line);margin:14px 0;break-inside:avoid}
.panel-title{padding:9px 13px;background:var(--pale);color:var(--navy);font-size:12px;font-weight:800;letter-spacing:.5px;border-bottom:1px solid var(--line)}
.panel-body{padding:13px;font-size:13px;line-height:1.7;overflow-wrap:anywhere}
.patient-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 22px}
table{width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}
th{background:var(--pale);color:var(--navy);text-align:left;padding:10px;border:1px solid var(--line)}
td{padding:11px 10px;border:1px solid var(--line);vertical-align:top;overflow-wrap:anywhere}
.footer{margin-top:24px;padding-top:12px;border-top:3px solid var(--blue);display:flex;justify-content:space-between;align-items:center;gap:16px;font-size:12px;color:var(--muted)}
@page{size:A4 portrait;margin:8mm}
@media print{body{background:#fff}.actions{display:none!important}.document{width:100%;max-width:none;margin:0;padding:6mm;box-shadow:none}.panel,table,tr{break-inside:avoid}}
</style>
</head>
<body>
<div class="actions"><button type="button" onclick="window.print()">Print</button><button type="button" id="download-rx-pdf">Download PDF</button></div>
<main class="document" id="rx-pdf-content">
<header class="header">
  <div><?php if ($logo_url !== ''): ?><img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="Dior Medical"><?php else: ?><div class="wordmark">Dior Medical</div><?php endif; ?></div>
  <div class="contact"><strong><?php echo esc_html(get_bloginfo('name') ?: 'Dior Medical'); ?></strong><br><?php echo esc_html(get_option('admin_email')); ?></div>
</header>
<section class="title-row">
  <div><h1>MEDICAL PRESCRIPTION</h1><div class="kicker">CLINICAL DOCUMENT</div></div>
  <div class="rx-meta"><strong>Prescription ID:</strong> <?php echo esc_html($rx['rx_id'] ?? '—'); ?><br><strong>Date:</strong> <?php echo esc_html($rx['prescription_date'] ?? '—'); ?></div>
</section>
<section class="panel">
  <div class="panel-title">PATIENT & PRESCRIBER DETAILS</div>
  <div class="panel-body patient-grid">
    <div><strong>Patient Name:</strong> <?php echo esc_html($rx['patient_name'] ?? '—'); ?></div>
    <div><strong>Age / Gender:</strong> <?php echo esc_html($rx['age_sex'] ?? '—'); ?></div>
    <div><strong>Address:</strong> <?php echo esc_html($rx['patient_address'] ?? '—'); ?></div>
    <div><strong>Prescribed By:</strong> <?php echo esc_html($rx['doctor_name'] ?? '—'); ?></div>
    <div><strong>Credentials:</strong> <?php echo esc_html($rx['doctor_creds'] ?? '—'); ?></div>
    <div><strong>License:</strong> <?php echo esc_html($rx['doctor_license'] ?? '—'); ?> · <strong>NPI:</strong> <?php echo esc_html($rx['doctor_npi'] ?? '—'); ?></div>
  </div>
</section>
<section class="panel">
  <div class="panel-title">DIAGNOSIS</div>
  <div class="panel-body"><?php echo esc_html($rx['diagnosis'] ?? '—'); ?></div>
</section>
<section class="panel">
  <div class="panel-title">MEDICATIONS & INSTRUCTIONS</div>
  <div class="panel-body">
    <table><thead><tr><th style="width:23%">Medication</th><th style="width:16%">Dosage</th><th style="width:18%">Quantity</th><th>Instructions</th></tr></thead><tbody>
    <?php if ($rx_items): foreach ($rx_items as $item): ?>
      <tr><td><?php echo esc_html($item['medication'] ?? '—'); ?></td><td><?php echo esc_html($item['dosage'] ?? '—'); ?></td><td><?php echo esc_html($item['quantity'] ?? '—'); ?></td><td><?php echo esc_html($item['instructions'] ?? '—'); ?></td></tr>
    <?php endforeach; else: ?><tr><td colspan="4">No medication items recorded.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</section>
<section class="panel">
  <div class="panel-title">CLINICAL ADVICE</div>
  <div class="panel-body"><?php echo nl2br(esc_html((string) ($rx['clinical_advice'] ?? 'Follow the prescribed instructions and contact your clinician if symptoms worsen.'))); ?></div>
</section>
<section class="panel">
  <div class="panel-title">PHARMACY</div>
  <div class="panel-body"><?php echo esc_html($rx['pharmacy'] ?? '—'); ?></div>
</section>
<footer class="footer"><span>This is a system-generated medical prescription.</span><span><?php echo esc_html(get_bloginfo('name') ?: 'Dior Medical'); ?></span></footer>
</main>
<script src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/js/html2pdf.bundle.min.js'); ?>"></script>
<script>
(function(){
  function downloadPdf(){
    var node=document.getElementById('rx-pdf-content');
    if(typeof html2pdf!=='function'||!node){window.print();return;}
    html2pdf().set({margin:[8,8,8,8],filename:'Prescription_<?php echo esc_js(preg_replace('/[^A-Za-z0-9_-]/','_', (string)($rx['rx_id'] ?? 'record'))); ?>.pdf',image:{type:'jpeg',quality:.98},html2canvas:{scale:2,useCORS:true,backgroundColor:'#fff',scrollX:0,scrollY:0},jsPDF:{unit:'mm',format:'a4',orientation:'portrait'},pagebreak:{mode:['css','legacy'],avoid:['.panel','tr']}}).from(node).save();
  }
  var button=document.getElementById('download-rx-pdf');
  if(button)button.addEventListener('click',downloadPdf);
  if(<?php echo wp_json_encode($rx_auto_download); ?>)window.addEventListener('load',function(){setTimeout(downloadPdf,250);});
})();
</script>
</body>
</html>
