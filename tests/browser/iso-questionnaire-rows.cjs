// Export native views with ISO_ROWS_QA=1 pest --filter=IsoQuestionnaireRows first.
// Set NODE_PATH and BROWSER_EXECUTABLE for the installed browser/runtime.
const {chromium} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
    const browser = await chromium.launch({headless:true, executablePath:process.env.BROWSER_EXECUTABLE});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', route => route.request().resourceType() === 'document'
            ? route.fulfill({contentType:'text/html',body:fs.readFileSync('tmp/iso-swot-rows.html','utf8')})
            : route.abort());
        await page.setContent(fs.readFileSync('tmp/iso-summary-rows.html', 'utf8'));
        // Even accidental repeated inclusion must not install a second listener.
        for (let i = 0; i < 2; i++) await page.addScriptTag({path:'public/js/iso-report-summary.js'});
        for (const key of ['ustalenia', 'decyzje', 'wnioski']) {
            const rows = page.locator(`[data-report-rows="${key}"] > *`);
            await page.locator(`[data-report-add="${key}"]`).click();
            assert.equal(await rows.count(), 1, key);
            await rows.first().locator('textarea').first().fill('Zapis wniosku');
            await page.locator(`[data-report-add="${key}"]`).click();
            assert.equal(await rows.count(), 2, key);
            assert.equal(await rows.first().locator('textarea').first().inputValue(), 'Zapis wniosku');
            await rows.last().locator('[data-report-remove]').click();
            assert.equal(await rows.count(), 1, key);
        }
        await page.goto('http://localhost/iso-swot');
        // Show the native tab without loading unrelated application scripts.
        await page.locator('#swot').evaluate(el => {el.hidden=false; el.style.display='block'; for(let p=el.parentElement;p;p=p.parentElement){p.hidden=false;p.style.display='block';}});
        await page.addScriptTag({path:'public/js/iso-factors.js'});
        const rows = page.locator('#swot-conclusions > [data-swot-conclusion]');
        const initial = await rows.count();
        await page.locator('#add-swot-conclusion').click();
        assert.equal(await rows.count(), initial + 1);
        await rows.last().locator('textarea').first().fill('Widoczny nowy wniosek');
        assert.equal(await rows.last().locator('textarea').first().inputValue(), 'Widoczny nowy wniosek');
        assert.equal(await page.locator('#swot-text-conclusions').inputValue(), '');
        await rows.last().locator('[data-remove-conclusion]').click();
        assert.equal(await rows.count(), initial);
        await page.locator('#custom').evaluate(el => {el.hidden=false;el.style.display='block';});
        await page.locator('#add-custom').click();
        const custom = page.locator('[data-custom-row]').last();
        await custom.locator('[name$="[tresc]"]').fill('Czynnik testowy');
        await custom.locator('[name$="[wymiar]"]').selectOption('wewn. techniczny');
        await custom.locator('[name$="[skutek]"]').fill('Ocena strat energii');
        assert.ok(!(await page.locator('#factor-progress').innerText()).includes('100%'));
        await custom.locator('[name$="[istotnosc]"]').selectOption('W');
        assert.ok(!(await page.locator('#factor-progress').innerText()).includes('100%'));
        await custom.locator('details').evaluate(el => el.open=true);
        await custom.locator('[name$="[rodzaj]"]').selectOption('pomiar');
        for (const key of ['zrodlo','okres','znaczenie','decyzja_w','dzialanie','sprawdzenie']) {
            await custom.locator(`[name$="[${key}]"]`).fill('Dane z pomiaru');
        }
        assert.ok((await page.locator('#factor-progress').innerText()).includes('100%'));
        assert.deepEqual(errors, []);
        console.log('PASS: native SWOT and three shared collections add exactly one editable row, retain existing input and remove one row; duplicate script is harmless.');
    } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exitCode=1;});
