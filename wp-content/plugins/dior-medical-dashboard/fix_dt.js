const fs = require('fs');

const files = [
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/doctor-dashboard.php',
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/patient-dashboard.php'
];

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    
    // Instead of regex, just split and replace
    const oldConfig = `
                $t.DataTable({
                    "order": [[0, "desc"]],
                    "pageLength": 10,
                    "retrieve": true,
                    "language": {
                        "search": "Filter records:"
                    }
                });`;
                
    const newConfig = `
                $t.DataTable({
                    "order": [[0, "desc"]],
                    "paging": false,
                    "info": false,
                    "searching": false,
                    "retrieve": true
                });`;
                
    if (content.includes(oldConfig.trim())) {
        content = content.replace(oldConfig.trim(), newConfig.trim());
    } else {
        // Fallback regex
        content = content.replace(/\$t\.DataTable\(\{[\s\S]*?"order": \[\[0, "desc"\]\][\s\S]*?\}\);/g, newConfig.trim());
    }
    
    fs.writeFileSync(file, content, 'utf8');
    console.log('Fixed Datatable config in ' + file);
});
