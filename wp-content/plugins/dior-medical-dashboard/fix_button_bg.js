const fs = require('fs');
const path = require('path');

const targetDir = 'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates';

function walk(dir) {
    let count = 0;
    fs.readdirSync(dir).forEach(file => {
        let fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            count += walk(fullPath);
        } else if (fullPath.endsWith('.php')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let originalContent = content;
            
            // Remove the specific inline style in all-patients.php
            content = content.replace(/style="background:#e0f2fe;color:#0284c7;border:1px solid #bae6fd;"/g, 'style="background:transparent; border:none;"');
            
            // Wait, also check if any buttons with bi-eye have other inline styles or lack transparent class
            // A brute force way is to find `<button ... bi-eye ... </button>` and inject `background:transparent !important; border:none !important;`
            
            if (content !== originalContent) {
                fs.writeFileSync(fullPath, content, 'utf8');
                count++;
            }
        }
    });
    return count;
}

const count = walk(targetDir);
console.log('Fixed inline styles in ' + count + ' files.');
