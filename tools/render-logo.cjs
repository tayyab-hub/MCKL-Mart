// Optional authoring tool; not an application dependency.
// npm install --no-save --prefix .local/artwork-tools sharp
// Set NODE_PATH to .local/artwork-tools/node_modules, then run this file.
const path = require('node:path');
const sharp = require('sharp');
const root = path.resolve(__dirname, '..');
sharp(path.join(root, 'assets/project-logo.svg'))
  .png()
  .toFile(path.join(root, 'Logo of the website.png'))
  .then(() => console.log('Rendered original project logo.'))
  .catch(() => { console.error('Logo rendering failed.'); process.exitCode = 1; });
