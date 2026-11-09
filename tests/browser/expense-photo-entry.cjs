const {chromium}=require('playwright');
const fs=require('node:fs');
const assert=require('node:assert/strict');
(async()=>{
    const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE});
    try {
        const page=await browser.newPage({viewport:{width:390,height:844}});
        let scans=0;
        const errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        await page.route('**/*',route=>{
            const url=new URL(route.request().url());
            if(url.pathname.endsWith('/scan')){scans++;return route.fulfill({contentType:'application/json',body:JSON.stringify({proposal:{supplier_name:'Dostawca zdjęcia',supplier_nip:'1234567890',invoice_number:'FV/2026/1',payment_status:'paid',payment_evidence:'Zapłacono w całości'}})});}
            if(['/js/expense-scan.js','/js/expense-form.js'].includes(url.pathname))return route.fulfill({contentType:'text/javascript',body:fs.readFileSync('public'+url.pathname,'utf8')});
            if(route.request().resourceType()==='document')return route.fulfill({contentType:'text/html',body:fs.readFileSync('tmp/expense-photo-entry.html','utf8')});
            return route.abort();
        });
        await page.goto('http://localhost/expenses/create');
        assert.equal(await page.locator('#expense-form').isVisible(),false);
        for(const text of ['Zrób zdjęcie','Wybierz z galerii','Wprowadź ręcznie'])assert.ok(await page.getByRole('button',{name:text,exact:true}).isVisible());
        assert.equal(await page.locator('#expense-photo').getAttribute('capture'),null);
        assert.equal(await page.locator('#expense-camera').getAttribute('capture'),'environment');
        await page.screenshot({path:'tmp/expense-photo-mobile-start.png'});
        await page.getByRole('button',{name:'Wprowadź ręcznie',exact:true}).click();
        assert.ok(await page.locator('#expense-form').isVisible());assert.equal(scans,0);
        await page.reload();
        await page.locator('#expense-photo').setInputFiles({name:'invoice.jpg',mimeType:'image/jpeg',buffer:Buffer.from('test photo intercepted locally')});
        await page.waitForFunction(()=>document.querySelector('[name=invoice_number]').value==='FV/2026/1');
        assert.equal(scans,1);assert.ok(await page.locator('#expense-form').isVisible());
        assert.equal(await page.locator('[name=payment_status]').inputValue(),'paid');
        assert.equal(await page.locator('[name=supplier_name]').count(),1);
        assert.equal(await page.locator('[name=wz_number]').isVisible(),false);
        await page.screenshot({path:'tmp/expense-photo-mobile-result.png',fullPage:true});
        await page.setViewportSize({width:1440,height:1000});await page.reload();
        assert.ok(await page.getByRole('button',{name:'Wybierz zdjęcie z dysku',exact:true}).isVisible());
        assert.equal(await page.getByRole('button',{name:'Zrób zdjęcie',exact:true}).isVisible(),false);
        assert.ok(await page.locator('#expense-form').isVisible());
        await page.screenshot({path:'tmp/expense-photo-desktop.png',fullPage:true});
        assert.deepEqual(errors,[]);
        console.log('PASS: three mobile choices, automatic one-shot OCR, payment proposal, single supplier, hidden WZ, manual entry, desktop disk selection.');
    } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
