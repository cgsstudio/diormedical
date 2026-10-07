const fs = require('fs');
const path = require('path');

const targetDir = 'c:/xampp/htdocs/diormedical/wp-content/plugins/dior-medical-dashboard/templates';
const oldId = '01a07aa2-d931-728d-bfe9-8d7b3bc0389d';
const newId = '01a110f3-ddb0-7783-88de-8b9b744cb56d';

function walk(dir) {
    let count = 0;
    fs.readdirSync(dir).forEach(file => {
        let fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            count += walk(fullPath);
        } else if (fullPath.endsWith('.php')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            if (content.includes(oldId)) {
                content = content.replace(new RegExp(oldId, 'g'), newId);
                fs.writeFileSync(fullPath, content, 'utf8');
                console.log('Replaced ID in ' + fullPath);
                count++;
            }
        }
    });
    return count;
}

const numFiles = walk(targetDir);
console.log('Total files updated: ' + numFiles);
