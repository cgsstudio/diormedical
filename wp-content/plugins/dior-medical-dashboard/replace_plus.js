const fs = require('fs');
const path = require('path');

const targetDir = 'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates';
const plusSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg>`;

const plusRegex = /<i\s+[^>]*class="[^"]*fa-plus[^"]*"[^>]*><\/i>/g;

function walk(dir) {
    let count = 0;
    fs.readdirSync(dir).forEach(file => {
        let fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            count += walk(fullPath);
        } else if (fullPath.endsWith('.php')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let modified = false;
            if (plusRegex.test(content)) {
                content = content.replace(plusRegex, plusSvg);
                count++;
                modified = true;
            }
            if (modified) {
                fs.writeFileSync(fullPath, content, 'utf8');
            }
        }
    });
    return count;
}

const count = walk(targetDir);
console.log('Replaced plus icons in ' + count + ' files.');
