// Run with Playwright installed and BROWSER_EXECUTABLE pointing to Chrome/Edge.
const {chromium}=require('playwright');
const fs=require('node:fs');
const assert=require('node:assert/strict');
(async()=>{
    const browser=await chromium.launch({executablePath:process.env.BROWSER_EXECUTABLE,headless:true});
    try {
        const page=await browser.newPage(),errors=[],drafts=new Map();
        let user='1',authenticated=true,failGet=false,cleared='',puts=0,logoutSnapshot=null;
        page.on('pageerror',error=>errors.push(error.message));
        page.on('dialog',dialog=>dialog.accept());
        await page.clock.install();
        const html=()=>`<form id="offer-form" data-autosave method="POST" action="/offers/1"><input name="title" value="Original"><button type="submit">Zapisz ofertę</button></form><form id="logout" method="POST" action="/logout"><button>Wyloguj</button></form><script>window.extra={prices:[1],text:'Original'};window.formDraftAdapters={'offer-form':{capture:()=>window.extra,restore:data=>window.extra=data}};</script><script src="/form-drafts.js" data-draft-user="${user}" data-draft-cleared="${cleared}" data-draft-endpoint="/form-drafts" data-csrf="test" defer></script>`;
        await page.route('**/*',async route=>{
            const request=route.request(),url=new URL(request.url());
            if(url.pathname==='/form-drafts.js')return route.fulfill({contentType:'text/javascript',body:fs.readFileSync('public/js/form-drafts.js','utf8')});
            if(url.pathname.startsWith('/form-drafts/')){
                if(!authenticated)return route.fulfill({status:419,json:{message:'Expired'}});
                if(failGet&&request.method()==='GET'){failGet=false;return route.fulfill({status:503,json:{message:'Temporary error'}});}
                const key=user+url.pathname,current=drafts.get(key)||{revision:0,payload:null};
                if(request.method()==='PUT'){
                    const data=request.postDataJSON();
                    if(data.revision!==current.revision)return route.fulfill({status:409,json:{message:'Conflict'}});
                    puts++;drafts.set(key,{revision:current.revision+1,payload:data.payload,saved_at:new Date().toISOString()});
                }
                return route.fulfill({json:drafts.get(key)||current});
            }
            if(url.pathname==='/logout'){
                logoutSnapshot=[...drafts.values()].at(-1)?.payload;
                authenticated=false;return route.fulfill({contentType:'text/html',body:'Logged out'});
            }
            return route.fulfill({contentType:'text/html; charset=utf-8',body:html()});
        });
        const open=async()=>{await page.goto('http://localhost/offers/1/edit');await page.clock.runFor(100);await page.locator('.draft-state').filter({hasNotText:'Łączenie'}).waitFor();};
        failGet=true;await open();
        await page.locator('[name=title]').fill('Retry startup');
        await page.clock.runFor(30010);
        await page.waitForFunction(()=>document.querySelector('.draft-state').textContent.startsWith('Autozapis:'));
        assert.equal(puts,1,'failed startup reconnects');
        await page.locator('[name=title]').fill('Unsent before expiry');
        await page.evaluate(()=>window.extra={prices:[123,456],text:'Rich offer text'});
        authenticated=false;
        await page.evaluate(()=>window.dispatchEvent(new Event('session:expired')));
        await page.clock.runFor(30010);
        await page.waitForFunction(()=>document.querySelector('.draft-state').textContent.includes('Sesja wygasła'));
        authenticated=true;await open();
        await page.locator('.draft-restore').click();
        assert.equal(await page.locator('[name=title]').inputValue(),'Unsent before expiry');
        assert.deepEqual(await page.evaluate(()=>window.extra),{prices:[123,456],text:'Rich offer text'});
        await page.locator('[name=title]').fill('Last edit before logout');
        await page.locator('#logout button').click();
        await page.waitForURL('**/logout');
        assert.equal(logoutSnapshot.snapshot.fields[0].value,'Last edit before logout');
        authenticated=true;await open();await page.locator('.draft-restore').click();
        await page.locator('[name=title]').fill('Private emergency');
        user='2';await open();
        assert.equal(await page.locator('.draft-recovery').isVisible(),false,'another account cannot recover this copy');
        assert.equal(await page.locator('[name=title]').inputValue(),'Original');
        await page.locator('.draft-switch input').uncheck();
        const count=puts;await page.locator('[name=title]').fill('Autosave disabled');await page.clock.runFor(30010);assert.equal(puts,count);
        user='1';await open();await page.locator('.draft-restore').click();
        await page.locator('.draft-exit').click();await page.waitForFunction(()=>!document.querySelector('.draft-recovery')||document.querySelector('.draft-recovery').hidden);
        await open();assert.equal(await page.locator('.draft-recovery').isVisible(),false,'discard must not resurrect emergency copy');
        await page.locator('[name=title]').fill('Manually saved');
        cleared=await page.evaluate(()=>Object.keys(sessionStorage).find(key=>key.startsWith('offer-emergency:1:')).split(':').at(-1));
        await open();
        assert.equal(await page.locator('.draft-recovery').isVisible(),false,'successful manual save clears the local copy');
        assert.deepEqual(errors,[]);
        console.log('PASS: retry, 30s autosave, expired session recovery incl. rich data, logout flush, user isolation, switch, discard');
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
