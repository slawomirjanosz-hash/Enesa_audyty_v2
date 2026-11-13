// Developer-only renderer. No dependencies or browser are needed at application runtime.
const { chromium } = require('playwright');
const { pathToFileURL } = require('node:url');
const path = require('node:path');

(async () => {
    const browser = await chromium.launch({
        headless: true,
        ...(process.env.PRESENTATION_BROWSER ? { executablePath: process.env.PRESENTATION_BROWSER } : {}),
    });
    try {
        const page = await browser.newPage({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1 });
        await page.goto(pathToFileURL(path.join(__dirname, 'presentation.html')).href);
        await page.evaluate(async () => {
            await document.fonts.ready;
            await Promise.all([...document.images].map(image => image.decode()));
        });
        const slides = page.locator('.slide');
        for (let i = 0; i < await slides.count(); i++) {
            const slide = slides.nth(i);
            const problems = await slide.evaluate(section => {
                const bounds = section.getBoundingClientRect();
                return [...section.querySelectorAll('h1,h2,h3,p,.card,.banner,.qa')].filter(element => {
                    const box = element.getBoundingClientRect();
                    return box.bottom > bounds.bottom - 68 || box.right > bounds.right - 40 ||
                        element.scrollWidth > element.clientWidth + 1 || element.scrollHeight > element.clientHeight + 4;
                }).map(element => element.textContent.trim());
            });
            if (problems.length) throw new Error(`Slide ${i + 1} overflows: ${problems.join(' / ')}`);
            await slide.screenshot({ path: path.join(__dirname, `slide-${i + 1}.png`) });
        }
        console.log(`Rendered ${await slides.count()} slides at 1280 × 720.`);
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
