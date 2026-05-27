const fs = require('fs');
const path = require('path');

const buildDir = path.join(__dirname, '..', 'public', 'assets', 'build');
const replacements = new Map([
    ['https://react.dev/errors/', '/react-errors/'],
    ['http://www.w3.org/2000/svg', 'urn:selo:svg'],
    ['http://www.w3.org/1998/Math/MathML', 'urn:selo:mathml'],
    ['http://www.w3.org/1999/xlink', 'urn:selo:xlink'],
    ['http://www.w3.org/XML/1998/namespace', 'urn:selo:xml'],
]);

function sanitizeFile(filePath) {
    const original = fs.readFileSync(filePath, 'utf8');
    let sanitized = original;
    for (const [from, to] of replacements) {
        sanitized = sanitized.split(from).join(to);
    }
    if (sanitized !== original) {
        fs.writeFileSync(filePath, sanitized);
    }
}

for (const fileName of fs.readdirSync(buildDir)) {
    if (fileName.endsWith('.js') || fileName.endsWith('.css')) {
        sanitizeFile(path.join(buildDir, fileName));
    }
}
