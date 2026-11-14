// Developer-only renderer. Production serves protected PNGs through the existing controller.
const { chromium } = require('playwright');
const { pathToFileURL } = require('node:url');
const path = require('node:path');
const catalogue = require('./catalogue.json');

(async () => {
    const browser = await chromium.launch({ headless: true,
        ...(process.env.PRESENTATION_BROWSER ? { executablePath: process.env.PRESENTATION_BROWSER } : {}) });
    const selected = new Set(process.argv.slice(2));
    let total = 0;
    try {
        const page = await browser.newPage({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1 });
        for (const deck of catalogue.filter(d => !selected.size || selected.has(d.section_id))) {
            const directory = path.join(__dirname, 'sections', deck.section_id);
            await page.goto(pathToFileURL(path.join(directory, 'presentation.html')).href);
            await page.evaluate(async () => {
                await document.fonts.ready;
                if (!document.fonts.check('23px Calibri')) throw new Error('Calibri is required by the existing template');
                await Promise.all([...document.images].map(image => image.decode()));
            });
            const slides = page.locator('.slide');
            if (await slides.count() !== deck.slide_count) throw new Error(`Wrong count: ${deck.section_id}`);
            for (let i = 0; i < deck.slide_count; i++) {
                const slide = slides.nth(i);
                const problems = await slide.evaluate(section => {
                    const bounds = section.getBoundingClientRect();
                    return [...section.querySelectorAll('h2,h3,p,.card,.qa,.example-note,.cover-path,.checklist')]
                        .filter(element => {
                            const box = element.getBoundingClientRect();
                            return box.bottom > bounds.bottom - 80 || box.right > bounds.right - 40 ||
                                element.scrollWidth > element.clientWidth + 1 || element.scrollHeight > element.clientHeight + 4;
                        }).map(element => element.textContent.trim());
                });
                if (problems.length) throw new Error(`${deck.section_id} slide ${i + 1} overflows: ${problems.join(' / ')}`);
                await slide.screenshot({ path: path.join(directory, `slide-${i + 1}.png`) });
                total++;
            }
            console.log(`${deck.section_id}: ${deck.slide_count} slides`);
        }
        console.log(`Rendered and checked ${total} slides at 1280 × 720.`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
