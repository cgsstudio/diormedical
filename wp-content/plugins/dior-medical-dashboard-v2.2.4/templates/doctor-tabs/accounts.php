<?php defined('ABSPATH') || exit; ?>
<section class="dior-tab-panel dior-source-group" id="tab-doc-accounts">
<div class="dior-content-pad">
<div class="dior-source-group-head"><div><div class="dior-source-kicker"><i class="fa-solid fa-wallet"></i> Doctor Workspace</div><h2>Accounts</h2><p>Structured workspace using the same visual language as the Patient Dashboard.</p></div></div>
<div class="dior-source-subtabs" role="tablist"><button type="button" class="dior-source-subtab-btn active" data-source-target="bill-list"><i class="fa-solid fa-file-invoice-dollar"></i><span>Bill List</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="add-bill"><i class="fa-solid fa-plus"></i><span>Add Bill</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="income"><i class="fa-solid fa-arrow-trend-up"></i><span>Income</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="expenses"><i class="fa-solid fa-arrow-trend-down"></i><span>Expenses</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="income-report"><i class="fa-solid fa-chart-column"></i><span>Income Report</span></button><button type="button" class="dior-source-subtab-btn " data-source-target="invoice"><i class="fa-solid fa-file-invoice"></i><span>Invoice</span></button></div>
<div class="dior-source-subcontent"><?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/bill-list.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/add-bill.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/income.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/expenses.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/income-report.php"; ?>
<?php include DIOR_PORTAL_PATH . "templates/doctor-tabs/source/invoice.php"; ?>
</div>
</div></section>
