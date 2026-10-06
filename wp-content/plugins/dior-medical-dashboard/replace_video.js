const fs = require('fs');
const path = require('path');

const targetDir = 'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates';
const videoSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#22c55e" class="bi bi-camera-reels" viewBox="0 0 16 16" style="background:transparent;"><path d="M6 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0M1 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M9 6h.5a2 2 0 0 1 1.983 1.738l3.11-1.382A1 1 0 0 1 16 7.269v7.462a1 1 0 0 1-1.406.913l-3.111-1.382A2 2 0 0 1 9.5 16H2a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm6 8.73V7.27l-3.5 1.555v4.35zM1 8v6a1 1 0 0 0 1 1h7.5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1"/><path d="M9 6a3 3 0 1 0 0-6 3 3 0 0 0 0 6M7 3a2 2 0 1 1 4 0 2 2 0 0 1-4 0"/></svg>`;

const videoRegex = /<i\s+[^>]*class="[^"]*fa-video[^"]*"[^>]*><\/i>/g;

function walk(dir) {
    let count = 0;
    fs.readdirSync(dir).forEach(file => {
        let fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            count += walk(fullPath);
        } else if (fullPath.endsWith('.php')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let modified = false;
            
            if (videoRegex.test(content)) {
                content = content.replace(videoRegex, videoSvg);
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
console.log('Replaced video icons in ' + count + ' files.');
