const fs = require('fs');

const files = [
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/doctor-dashboard.php',
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/patient-dashboard.php'
];

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    
    // Update DataTables config to disable paging, info, and search
    content = content.replace(
        /"order": \[\[0, "desc"\]\],[\s\S]*?"retrieve": true,[\s\S]*?"language": \{[\s\S]*?"search": "Filter records:"[\s\S]*?\}/g,
        `"order": [[0, "desc"]],
                    "paging": false,
                    "info": false,
                    "searching": false,
                    "retrieve": true`
    );
    
    // Inject CSS for table centering and white background
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
    
    if (!content.includes('/* Global Table Fixes */')) {
        content = content.replace('/* Enforce no backgrounds or borders on action buttons wrapping SVG icons */', cssToInject + '\n/* Enforce no backgrounds or borders on action buttons wrapping SVG icons */');
    }
    
    fs.writeFileSync(file, content, 'utf8');
    console.log('Fixed table in ' + file);
});
