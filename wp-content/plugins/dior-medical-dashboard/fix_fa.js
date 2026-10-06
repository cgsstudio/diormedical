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
            let modified = false;
            
            // Replace fa-solid with fas
            if (content.includes('fa-solid')) {
                content = content.replace(/fa-solid/g, 'fas');
                modified = true;
            }
            
            // Replace fa-regular with far
            if (content.includes('fa-regular')) {
                content = content.replace(/fa-regular/g, 'far');
                modified = true;
            }
            
            // Replace fa-light/fa-thin etc if any, though likely not needed
            
            if (modified) {
                fs.writeFileSync(fullPath, content, 'utf8');
                count++;
            }
        }
    });
    return count;
}

const count = walk(targetDir);
console.log('Fixed fontawesome classes in ' + count + ' files.');
