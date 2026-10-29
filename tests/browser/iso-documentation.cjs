// Render the native fixture with ISO_DOCUMENTATION_QA=1 pest --filter=IsoDocumentationCoverageTest.
const {chromium} = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
(async () => {
    const browser = await chromium.launch({headless:true, executablePath:process.env.BROWSER_EXECUTABLE});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', route => {
            const url = new URL(route.request().url());
            if (route.request().resourceType() === 'document') return route.fulfill({contentType:'text/html',body:fs.readFileSync('tmp/iso-documentation.html','utf8')});
            const script = path.basename(url.pathname);
            if (['iso-system.js','table-sort.js','table-actions.js'].includes(script)) return route.fulfill({contentType:'application/javascript',body:fs.readFileSync('public/js/'+script,'utf8')});
            return route.abort();
        });
        await page.goto('http://localhost/audits/1/system/1/7-5');
        assert.equal(await page.locator('[data-coverage-count]').innerText(), '15');
        const headings = page.locator('#documentation-coverage th');
        for (let column = 0; column < 3; column++) {
            await headings.nth(column).click();
            assert.equal(await headings.nth(column).getAttribute('aria-sort'), 'ascending');
            await headings.nth(column).click();
            assert.equal(await headings.nth(column).getAttribute('aria-sort'), 'descending');
        }
        const before = await page.locator('[data-system-progress]').innerText();
        await page.locator('#system-form [data-system-field]').evaluateAll(fields => {
            for (const field of fields.filter(el => /^WYKAZ\.[^.]+\.punkt$/.test(el.dataset.key))) {
                const input = field.querySelector('textarea,input');
                if (/(^|[^\d.])10\.1(?![\d.])/.test(input.value)) {
                    input.value = input.value.replace(/10\.1(?![\d.])/g, '10.10');
                    input.dispatchEvent(new Event('input',{bubbles:true}));
                }
            }
        });
        assert.equal(await page.locator('[data-coverage-count]').innerText(), '14');
        assert.equal(await page.locator('[data-coverage-clause="10.1"] [data-coverage-status]').innerText(), 'Brak pozycji w wykazie');
        assert.notEqual(await page.locator('[data-system-progress]').innerText(), before);
        assert.deepEqual(errors, []);
        console.log('PASS: native coverage, live completeness, all three sortable columns, no browser errors');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
