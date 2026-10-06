const fs = require('fs');
const path = require('path');

const targetDir = 'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates';

const oldSvg1 = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16" style="background:transparent;"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>`;
const oldSvg2 = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">\n  <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>\n  <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>\n</svg>`;
const newSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3b82f6" class="bi bi-eye" viewBox="0 0 16 16" style="background:transparent;"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>`;

// Also, handle any stragglers if we missed them due to fixing the fontawesome 6 classes.
// Some fa-eye might have become fas fa-eye or far fa-eye (wait, fa-eye doesn't change, but if they were `fa-solid fa-eye`, they might have been changed or skipped).
const eyeRegex = /<i\s+[^>]*class="[^"]*fa-eye[^"]*"[^>]*><\/i>/g;

function walk(dir) {
    let count = 0;
    fs.readdirSync(dir).forEach(file => {
        let fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            count += walk(fullPath);
        } else if (fullPath.endsWith('.php')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let modified = false;
            
            // Replace old inserted SVG
            if (content.includes(oldSvg1)) {
                content = content.split(oldSvg1).join(newSvg);
                modified = true;
            }
            if (content.includes(oldSvg2)) {
                content = content.split(oldSvg2).join(newSvg);
                modified = true;
            }
            
            // Catch any remaining fa-eye icons
            if (eyeRegex.test(content)) {
                content = content.replace(eyeRegex, newSvg);
                modified = true;
            }

            if (modified) {
                fs.writeFileSync(fullPath, content, 'utf8');
                count++;
            }
        }
    });
    return count;
}

const count = walk(targetDir);
console.log('Replaced eye icons in ' + count + ' files.');
