const fs = require('fs');

const files = [
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/doctor-dashboard.php',
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/patient-dashboard.php'
];

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    
    // Remove the previous forced centering and white backgrounds that broke the design
    const regex = /<style>\s*\/\* Global Table Fixes \*\/[\s\S]*?<\/style>\s*/;
    if (regex.test(content)) {
        content = content.replace(regex, '');
        console.log('Removed old Global Table Fixes from ' + file);
    }
    
    // Inject the new reference design
    const newCss = `
<style>
/* Table Design matching Feedback & Support */
table.docs-table, table.va-table, table.dior-ref-table, table.table {
    width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
    background: #ffffff !important;
}

table.docs-table thead th, table.va-table thead th, table.dior-ref-table thead th, table.table thead th {
    background: #ffffff !important;
    color: #64748b !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    padding: 16px 24px !important;
    border-bottom: 2px solid #f1f5f9 !important;
    text-align: left !important;
    white-space: nowrap !important;
}

table.docs-table tbody td, table.va-table tbody td, table.dior-ref-table tbody td, table.table tbody td {
    padding: 20px 24px !important;
    color: #334155 !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    border-bottom: 1px solid #f1f5f9 !important;
    background: #ffffff !important;
    text-align: left !important;
    vertical-align: middle !important;
}

table.docs-table tbody tr:hover td, table.va-table tbody tr:hover td, table.dior-ref-table tbody tr:hover td, table.table tbody tr:hover td {
    background: #f8fafc !important;
}
</style>
`;
    content = newCss + content;
    fs.writeFileSync(file, content, 'utf8');
    console.log('Injected new Feedback table design to ' + file);
});
