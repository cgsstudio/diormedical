const fs = require('fs');
const path = require('path');

const targetDir = 'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates';
const editSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg>`;
const printSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16" style="background:transparent;"><path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/><path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/></svg>`;

const editRegex = /<i\s+[^>]*class="[^"]*fa-(pen|edit|pencil)[^"]*"[^>]*><\/i>/g;
const printRegex = /<i\s+[^>]*class="[^"]*fa-print[^"]*"[^>]*><\/i>/g;

function walk(dir) {
    let editCount = 0;
    let printCount = 0;
    fs.readdirSync(dir).forEach(file => {
        let fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            let counts = walk(fullPath);
            editCount += counts.editCount;
            printCount += counts.printCount;
        } else if (fullPath.endsWith('.php')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let modified = false;
            if (editRegex.test(content)) {
                content = content.replace(editRegex, editSvg);
                editCount++;
                modified = true;
            }
            if (printRegex.test(content)) {
                content = content.replace(printRegex, printSvg);
                printCount++;
                modified = true;
            }
            if (modified) {
                fs.writeFileSync(fullPath, content, 'utf8');
            }
        }
    });
    return { editCount, printCount };
}

const counts = walk(targetDir);
console.log('Replaced edit icons in ' + counts.editCount + ' files.');
console.log('Replaced print icons in ' + counts.printCount + ' files.');
