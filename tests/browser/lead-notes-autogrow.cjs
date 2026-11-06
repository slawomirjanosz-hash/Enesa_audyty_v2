const {chromium} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
    const browser = await chromium.launch({headless:true, executablePath:process.env.BROWSER_EXECUTABLE});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.route('**/*', route => {
            if (route.request().url().endsWith('/autogrow.js')) return route.fulfill({contentType:'text/javascript', body:fs.readFileSync('public/js/lead-notes-autogrow.js','utf8')});
            return route.fulfill({contentType:'text/html', body:`<style>#modal{display:none;width:600px;max-height:80vh;overflow:auto}textarea{box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid;font:13px Arial;resize:none}</style><div id="modal"><form><textarea name="notes" rows="2" data-lead-notes-autogrow></textarea><button>Zapisz</button></form></div><script src="/autogrow.js" defer></script>`});
        });
        await page.goto('http://localhost/');
        const field = page.locator('textarea');
        const height = () => field.evaluate(e => e.clientHeight);
        const fits = () => field.evaluate(e => e.scrollHeight <= e.clientHeight + 1);
        await page.evaluate(() => {document.querySelector('#modal').style.display='block';});
        await page.waitForFunction(() => document.querySelector('textarea').style.height !== '');
        const initial = await height();
        await field.fill(Array(35).fill('Długa notatka dotycząca rozmowy z klientem.').join('\n'));
        assert.ok(await height() > initial * 5);
        assert.ok(await fits());
        await field.fill('Krótka notatka');
        assert.ok(await height() <= initial + 1);
        await page.evaluate(() => {document.querySelector('#modal').style.display='none';});
        await page.waitForTimeout(60);
        await page.evaluate(() => {const e=document.querySelector('textarea');e.value='Istniejące notatki '.repeat(200);e.disabled=true;document.querySelector('#modal').style.display='block';});
        await page.waitForFunction(() => document.querySelector('textarea').scrollHeight <= document.querySelector('textarea').clientHeight + 1);
        const wide = await height();
        await page.evaluate(() => {document.querySelector('#modal').style.width='300px';});
        await page.waitForFunction(() => document.querySelector('textarea').scrollHeight <= document.querySelector('textarea').clientHeight + 1);
        assert.ok(await height() > wide);
        await page.evaluate(() => {document.querySelector('textarea').disabled=false;document.querySelector('form').reset();});
        await page.waitForFunction(() => document.querySelector('textarea').clientHeight < 100);
        assert.deepEqual(errors, []);
        console.log('PASS: multiline input/paste, shrinking, saved read-only notes on modal opening, width change and form reset.');
    } finally {await browser.close();}
})().catch(e => {console.error(e);process.exitCode=1;});
