const fs = require('fs');

const cssToInject = `
/* Global Action Button Fixes */
button.va-action-btn-sm, 
button.action-icon-btn, 
button.dior-ref-icon-btn, 
button.dior-action-btn-sm,
button.dior-feedback-view-action,
button.dior-ref-btn,
button.va-btn-primary,
button.va-btn-success,
button.va-btn-info,
button.va-btn-danger,
.va-actions-group button,
.dior-ref-card-head button,
.cell-actions button {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    color: #3b82f6 !important; /* Fallback color for currentColor SVGs */
}

/* Ensure SVG icons inside have visible colors */
button.action-icon-btn svg,
button.va-action-btn-sm svg,
button.dior-ref-icon-btn svg,
button.dior-action-btn-sm svg,
.va-actions-group button svg,
.dior-ref-card-head button svg,
.cell-actions button svg {
    color: #3b82f6 !important;
}

/* Specific colors for specific icon types if needed, but they mostly have direct 'fill' attributes now */
`;

const files = [
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/doctor-dashboard.php',
    'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates/patient-dashboard.php'
];

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    
    if (!content.includes('/* Global Action Button Fixes */')) {
        content = '<style>' + cssToInject + '</style>\n' + content;
        fs.writeFileSync(file, content, 'utf8');
        console.log('Injected Action Button CSS into ' + file);
    }
});
