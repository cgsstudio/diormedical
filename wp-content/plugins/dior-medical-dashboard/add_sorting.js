const fs = require('fs');

const sortingScript = `
<!-- Global Table Sorting Initialization -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    function initSorting() {
        if (typeof jQuery === 'undefined') return;
        jQuery(function($) {
            if ($.fn.DataTable) {
                applySorting($);
            } else {
                $.getScript("https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js", function() {
                    $("<link/>", {
                       rel: "stylesheet",
                       type: "text/css",
                       href: "https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"
                    }).appendTo("head");
                    
                    // Small delay to ensure CSS loads and doesn't FOUC tables weirdly
                    setTimeout(function() { applySorting($); }, 100);
                });
            }
        });
    }
    
    function applySorting($) {
        var tables = $('table.va-table, table.dior-ref-table, table.table').not('.dataTable');
        tables.each(function() {
            var $t = $(this);
            if ($t.find('thead th').length > 0 && $t.find('tbody tr').length > 0) {
                $t.DataTable({
                    "order": [[0, "desc"]],
                    "pageLength": 10,
                    "retrieve": true,
                    "language": {
                        "search": "Filter records:"
                    }
                });
            }
        });
    }
    
    initSorting();
});
</script>
`;

const files = [
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/doctor-dashboard.php',
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/patient-dashboard.php'
];

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    if (!content.includes('Global Table Sorting Initialization')) {
        content += sortingScript;
        fs.writeFileSync(file, content, 'utf8');
        console.log('Added sorting to ' + file);
    }
});
