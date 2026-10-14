// Syntax-check external JS and inline scripts emitted by PHP. No dependencies.
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
new vm.Script(fs.readFileSync(path.join(__dirname, '..', 'script.js'), 'utf8'), {filename:'script.js'});
const base = process.argv[2];
if (!base || !['localhost','127.0.0.1'].includes(new URL(base).hostname)) {
    console.error('Usage: node tests/javascript_test.cjs http://127.0.0.1:TEST_PORT');
    process.exit(1);
}
(async () => {
    let count = 1;
    for (const route of ['index.php','products.php','checkout.php','contact.php','login.php','register.php']) {
        const result = await fetch(base.replace(/\/$/,'')+'/'+route);
        if (!result.ok) throw new Error('Page unavailable: '+route);
        const html = await result.text();
        for (const match of html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)) {
            if (!match[1].trim()) continue;
            new vm.Script(match[1], {filename:route+' inline script'});
            count++;
        }
    }
    console.log(`PASS: ${count} external/rendered script blocks parsed successfully.`);
})().catch(error => {console.error(error.message);process.exitCode=1;});
