// Export real views with CALENDAR_ENTRY_QA=1 artisan test --filter=CalendarEntryTest first.
// Set NODE_PATH to Playwright's installation and BROWSER_EXECUTABLE if necessary.
const {chromium} = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
    const browser = await chromium.launch({headless:true, executablePath:process.env.BROWSER_EXECUTABLE});
    try {
        const page = await browser.newPage({viewport:{width:1440,height:1000}});
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', route => {
            const url = new URL(route.request().url());
            if (route.request().resourceType() === 'document') return route.fulfill({contentType:'text/html',body:fs.readFileSync('tmp/calendar-entry-render.html','utf8')});
            if (url.pathname === '/js/calendar-entry.js') return route.fulfill({contentType:'application/javascript',body:fs.readFileSync('public/js/calendar-entry.js','utf8')});
            if (url.pathname === '/css/board.css') return route.fulfill({contentType:'text/css',body:fs.readFileSync('public/css/board.css','utf8')});
            return route.abort();
        });
        await page.goto('http://localhost/calendar');
        const dialog = page.locator('#calendar-editor');
        await page.locator('[data-calendar-date="2026-11-18"]').click({position:{x:20,y:125}});
        assert.equal(await dialog.evaluate(e=>e.open),true);
        assert.ok(await dialog.evaluate(e=>Math.abs(e.getBoundingClientRect().left - (innerWidth-e.getBoundingClientRect().width)/2) < 2));
        assert.equal(await dialog.locator('[name=due_date]').inputValue(),'2026-11-18');
        assert.equal(await dialog.locator('[name=calendar_kind]').inputValue(),'task');
        await dialog.locator('[name=title]').fill('Przegląd projektu');
        await dialog.locator('[name=scheduled_time]').fill('09:15');
        await dialog.locator('[name=calendar_kind]').selectOption('meeting');
        assert.equal(await dialog.locator('form').evaluate(e=>e.checkValidity()),true);
        await page.screenshot({path:'tmp/calendar-entry-desktop.png'});
        await page.keyboard.press('Escape');
        assert.equal(await dialog.evaluate(e=>e.open),false);
        await page.locator('[data-calendar-new="meeting"]').click();
        assert.equal(await dialog.locator('[name=calendar_kind]').inputValue(),'meeting');
        assert.equal(await dialog.locator('[name=title]').inputValue(),'');
        await dialog.locator('[data-calendar-close]').last().click();
        await page.locator('[data-calendar-date="2026-11-12"] button').focus();
        await page.keyboard.press('Enter');
        assert.equal(await dialog.locator('[name=due_date]').inputValue(),'2026-11-12');
        await page.setViewportSize({width:390,height:844});
        assert.ok(await dialog.evaluate(e=>e.getBoundingClientRect().width <= 390));
        await page.screenshot({path:'tmp/calendar-entry-mobile.png'});
        await page.keyboard.press('Escape');
        await page.locator('a.calendar-event').filter({hasText:'Spotkanie harmonogramowe'}).click();
        assert.equal(await dialog.evaluate(e=>e.open),false);
        assert.deepEqual(errors,[]);
        console.log('PASS: day click, date, type, time, cancel, Escape, keyboard, mobile dialog, event link.');
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
