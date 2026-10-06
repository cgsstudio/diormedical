const fs = require('fs');

const cssToInject = `
/* Global Table Fixes */
table.va-table th, table.va-table td, 
table.dior-ref-table th, table.dior-ref-table td, 
table.table th, table.table td {
    text-align: center !important;
    vertical-align: middle !important;
}

table.va-table, table.dior-ref-table, table.table,
table.va-table tbody tr, table.dior-ref-table tbody tr, table.table tbody tr,
table.va-table td, table.dior-ref-table td, table.table td,
table.va-table th, table.dior-ref-table th, table.table th {
    background-color: #ffffff !important;
    background: #ffffff !important;
}
`;

const files = [
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/doctor-dashboard.php',
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/patient-dashboard.php'
];

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    
    // Inject at the very beginning to be safe
    if (!content.includes('/* Global Table Fixes */')) {
        content = '<style>' + cssToInject + '</style>\n' + content;
        fs.writeFileSync(file, content, 'utf8');
        console.log('Injected CSS into ' + file);
    }
});
