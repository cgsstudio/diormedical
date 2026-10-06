<?php defined('ABSPATH') || exit; ?>
<section class="dior-tab-panel dior-source-group" id="tab-doc-accounts">
<div class="dior-content-pad">
<div class="dior-source-group-head"><div><div class="dior-source-kicker"><i class="fas fa-wallet"></i> Doctor Workspace</div><h2>Accounts</h2><p>Structured workspace using the same visual language as the Patient Dashboard.</p></div></div>
<div class="dior-source-subtabs" role="tablist"><button type="button" class="dior-source-subtab-btn active" data-source-target="bill-list"><i class="fas fa-file-invoice-dollar"></i><span>Bill List</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="add-bill"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg><span>Add Bill</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="income"><i class="fas fa-arrow-trend-up"></i><span>Income</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="expenses"><i class="fas fa-arrow-trend-down"></i><span>Expenses</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="income-report"><i class="fas fa-chart-column"></i><span>Income Report</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="invoice"><i class="fas fa-file-invoice"></i><span>Invoice</span></button></div>
<div class="dior-source-subcontent"><?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/bill-list.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/add-bill.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/income.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/expenses.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/income-report.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/invoice.php"; ?>
</div>
</div></section>
