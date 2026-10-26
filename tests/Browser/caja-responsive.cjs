// Solo HTML sintético exportado por CajaWebTest. No inicia Laravel ni permite tráfico de red.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
    const fixtures = fs.realpathSync(process.argv[2]);
    const root = path.resolve(__dirname, '../..');
    const browser = await chromium.launch({ headless: true, channel: process.env.KARMA_CAJA_BROWSER || undefined });
    let checks = 0;
    try {
        for (const width of [320, 375, 768, 1440]) {
            const context = await browser.newContext({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
            try {
                await context.route('**/*', async route => {
                    const url = new URL(route.request().url());
                    const relative = decodeURIComponent(url.pathname).replace(/^\/+/, '');
                    const base = url.hostname === 'fixture.invalid' ? fixtures : root + '/public';
                    const file = path.resolve(base, relative);
                    if (!['fixture.invalid', 'localhost'].includes(url.hostname) || !file.startsWith(path.resolve(base) + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
                        return route.abort();
                    }
                    const contentType = file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'text/javascript' : file.endsWith('.html') ? 'text/html' : 'image/png';
                    return route.fulfill({ body: fs.readFileSync(file), contentType });
                });
                const page = await context.newPage();
                const screens = ['listado', 'previsualizacion', 'detalle', 'version', 'historico', 'vacio'];
                for (const form of ['confirmacion-bloqueada','rectificacion-bloqueada','confirmacion-prueba','rectificacion-prueba']) {
                    if (fs.existsSync(path.join(fixtures, form + '.html'))) screens.push(form);
                }
                for (const report of ['mensual','anual']) {
                    if (fs.existsSync(path.join(fixtures, report + '.html'))) screens.push(report);
                }
                for (const name of screens) {
                    await page.goto('http://fixture.invalid/' + name + '.html');
                    await page.evaluate(() => document.fonts.ready);
                    const result = await page.evaluate(() => {
                        const panel = document.querySelector('.caja-panel');
                        const tables = [...panel.querySelectorAll('.caja-tabla')];
                        return {
                            overflow: document.documentElement.scrollWidth > innerWidth + 1,
                            clipped: tables.some(el => el.clientWidth > panel.clientWidth + 1 || getComputedStyle(el).overflowX !== 'auto'),
                            hiddenMoney: [...panel.querySelectorAll('.caja-importe')].some(el => getComputedStyle(el).display === 'none' || getComputedStyle(el).visibility === 'hidden'),
                            storage: localStorage.length + sessionStorage.length,
                            notice: panel.textContent.includes('Las operaciones financieras de caja todavía no están habilitadas'),
                            tables: tables.length,
                        };
                    });
                    assert.equal(result.overflow, false, `${name}@${width}: desbordamiento de página`);
                    assert.equal(result.clipped, false, `${name}@${width}: tabla fuera de su contenedor`);
                    assert.equal(result.hiddenMoney, false, `${name}@${width}: importes ocultos`);
                    assert.equal(result.storage, 0, `${name}@${width}: almacenamiento del navegador`);
                    assert.equal(result.notice, true);
                    if (name.endsWith('-bloqueada')) assert.equal(await page.locator('fieldset[disabled]').count(), 1);
                    for (const table of await page.locator('.caja-tabla').all()) {
                        await table.evaluate(el => { el.scrollLeft = el.scrollWidth; });
                        assert.equal(await table.evaluate(el => el.scrollLeft + el.clientWidth >= el.scrollWidth - 1), true, 'La última columna debe ser alcanzable');
                    }
                    if (width <= 375) {
                        await page.locator('.menu-movil').click();
                        await page.locator('#paquete-pedidos').waitFor({ state: 'visible' });
                    }
                    if (width === 375 && name === 'detalle') await page.screenshot({ path: path.join(fixtures, 'detalle-375.png'), fullPage: true });
                    if (width === 375 && name === 'anual') await page.screenshot({ path: path.join(fixtures, 'anual-375.png'), fullPage: true });
                    checks++;
                }
            } finally { await context.close(); }
        }
        process.stdout.write(JSON.stringify({ pantallas_y_anchos: checks, resultado: 'PASS', red_real: false }) + '\n');
    } finally { await browser.close(); }
})().catch(error => { process.stderr.write(error.message + '\n'); process.exitCode = 1; });
