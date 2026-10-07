const fs = require('fs');
const path = 'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/doctor-dashboard.php';
let content = fs.readFileSync(path, 'utf8');

// 1. Add menu item
const menuItem = `
                    <button type="button" class="dior-nav-btn" data-tab="doc-form-test">
                        <i class="fas fa-file-medical"></i><span class="nav-label">Form Test</span>
                    </button>
`;
if (!content.includes('data-tab="doc-form-test"')) {
    content = content.replace(
        '<button type="button" class="dior-nav-btn" data-tab="doc-analytics">',
        menuItem + '\n                    <button type="button" class="dior-nav-btn" data-tab="doc-analytics">'
    );
}

// 2. Add include
const includeStmt = "                <?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/form-test.php'; ?>\n";
if (!content.includes('form-test.php')) {
    content = content.replace(
        "<?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/patient-profile.php'; ?>\n",
        "<?php include DIOR_PORTAL_PATH . 'templates/doctor-tabs/patient-profile.php'; ?>\n" + includeStmt
    );
}

fs.writeFileSync(path, content, 'utf8');
console.log('Added Form Test menu item and include to doctor-dashboard.php');
