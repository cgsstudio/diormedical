const fs = require('fs');

// 1. Fix DataTable colspan error
const files = [
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/doctor-dashboard.php',
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/patient-dashboard.php'
];

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    
    // We replace the applySorting function to remove colspan rows
    if (content.includes('function applySorting($) {') && !content.includes('colspan')) {
        content = content.replace(
            `    function applySorting($) {
        var tables = $('table.va-table, table.dior-ref-table, table.table').not('.dataTable');
        tables.each(function() {
            var $t = $(this);
            if ($t.find('thead th').length > 0 && $t.find('tbody tr').length > 0) {`,
            
            `    function applySorting($) {
        var tables = $('table.va-table, table.dior-ref-table, table.table').not('.dataTable');
        tables.each(function() {
            var $t = $(this);
            // Remove manual empty colspan rows so DataTables doesn't crash
            $t.find('tbody tr').each(function() {
                var cells = $(this).children('td');
                if (cells.length === 1 && cells.first().attr('colspan')) {
                    $(this).remove();
                }
            });
            
            if ($t.find('thead th').length > 0) {`
        );
        fs.writeFileSync(file, content, 'utf8');
        console.log('Fixed DataTable in ' + file);
    }
});

// 2. Add the shortcode to form-test.php
const formTestFile = 'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/doctor-tabs/form-test.php';
let ftContent = fs.readFileSync(formTestFile, 'utf8');

if (!ftContent.includes('[hipaatizer id="01a110f3-ddb0-7783-88de-8b9b744cb56d"]')) {
    const embedBlock = `
        <!-- HIPAAtizer Form Embed -->
        <div class="dior-dash-table-card dior-box-card" style="margin-bottom: 24px; padding: 24px;">
            <h3 style="margin-top:0;">Fill out the Test Form</h3>
            <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; min-height: 500px;">
                <?php echo do_shortcode('[hipaatizer id="01a110f3-ddb0-7783-88de-8b9b744cb56d"]'); ?>
                <iframe id="hipaatizer-test-iframe" src="https://app.hipaatizer.com/workflow/01a110f3-ddb0-7783-88de-8b9b744cb56d" style="width: 100%; min-height: 500px; border: none;"></iframe>
            </div>
        </div>
`;
    ftContent = ftContent.replace(
        '<div class="master-table-wrapper">',
        embedBlock + '\n        <div class="master-table-wrapper">'
    );
    fs.writeFileSync(formTestFile, ftContent, 'utf8');
    console.log('Added shortcode to form-test.php');
}
