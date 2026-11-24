// HTML sintético exportado por PagosSimuladosWebTest. Node >=22, Edge local, sin dependencias.
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');

(async () => {
    const fixtures = fs.realpathSync(process.argv[2]);
    const assets = path.resolve(__dirname, '../../public');
    const browserPath = process.env.CU26_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
    const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'cu26-browser-'));
    const child = spawn(browserPath, ['--headless', '--disable-gpu', '--no-first-run', '--disable-background-networking',
        '--disable-component-update', '--host-resolver-rules=MAP * ~NOTFOUND', '--remote-debugging-port=0',
        '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let ws;
    try {
        const active = path.join(profile, 'DevToolsActivePort');
        for (let i = 0; !fs.existsSync(active) && i < 100; i++) await new Promise(r => setTimeout(r, 100));
        assert(fs.existsSync(active), 'Edge no abrió el perfil temporal de pruebas.');
        const [port, endpoint] = fs.readFileSync(active, 'utf8').trim().split(/\r?\n/);
        ws = new WebSocket('ws://127.0.0.1:' + port + endpoint);
        await new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; });
        let serial = 0;
        const pending = new Map();
        const events = new Map();
        const call = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
            const id = ++serial;
            const timer = setTimeout(() => { pending.delete(id); reject(new Error('Timeout CDP: ' + method)); }, 10000);
            pending.set(id, { resolve, reject, timer });
            ws.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
        });
        ws.onmessage = message => {
            const data = JSON.parse(message.data);
            if (data.id && pending.has(data.id)) {
                const p = pending.get(data.id); pending.delete(data.id); clearTimeout(p.timer);
                if (data.error) p.reject(new Error(data.error.message)); else p.resolve(data.result);
            } else if (events.has(data.method)) events.get(data.method)(data.params, data.sessionId);
        };
        const { targetId } = await call('Target.createTarget', { url: 'about:blank' });
        const { sessionId } = await call('Target.attachToTarget', { targetId, flatten: true });
        const page = (method, params) => call(method, params, sessionId);
        events.set('Fetch.requestPaused', async (request, session) => {
            try {
                const url = new URL(request.request.url);
                const base = url.hostname === 'fixture.invalid' ? fixtures : assets;
                const file = path.resolve(base, decodeURIComponent(url.pathname).replace(/^\/+/, ''));
                if (url.protocol !== 'http:' || !['fixture.invalid','localhost'].includes(url.hostname)
                    || !file.startsWith(base + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
                    return await call('Fetch.failRequest', { requestId: request.requestId, errorReason: 'BlockedByClient' }, session);
                }
                let body = fs.readFileSync(file);
                if (file.endsWith('.html')) body = Buffer.from(body.toString().replaceAll('file:///' + assets.replaceAll('\\','/') + '/', 'http://localhost/'));
                const mime = file.endsWith('.html') ? 'text/html' : file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'text/javascript' : 'image/png';
                await call('Fetch.fulfillRequest', { requestId: request.requestId, responseCode: 200,
                    responseHeaders: [{ name: 'Content-Type', value: mime }], body: body.toString('base64') }, session);
            } catch (error) { process.stderr.write(error.message + '\n'); }
        });
        await page('Page.enable');
        await page('Fetch.enable', { patterns: [{ urlPattern: '*' }] });
        let checks = 0;
        for (const width of [320,375,768,1440]) {
            await page('Emulation.setDeviceMetricsOverride', { width, height: 1100, deviceScaleFactor: 1, mobile: false });
            const screens = ['preparacion','qr-pendiente','tarjeta-pendiente','tarjeta-aprobada','historial'];
            for (const extra of ['qr-aprobado','tarjeta-rechazada','tarjeta-3ds','recuperacion','reportes-pagos','reportes-diarias','caja-diaria','caja-mensual','caja-anual','caja-version']) {
                if (fs.existsSync(path.join(fixtures,extra+'.html'))) screens.push(extra);
            }
            for (const name of screens) {
                const loaded = new Promise(resolve => events.set('Page.loadEventFired', resolve));
                await page('Page.navigate', { url: 'http://fixture.invalid/' + name + '.html' });
                await loaded;
                await page('Runtime.evaluate', { expression: 'document.fonts.ready', awaitPromise: true });
                const { result } = await page('Runtime.evaluate', { returnByValue: true, expression: `JSON.stringify({
                    viewport: innerWidth, scroll: document.documentElement.scrollWidth,
                    aviso: document.body.textContent.includes('SIMULACIÓN LOCAL — SIN DINERO REAL'),
                    codificacion: !document.body.textContent.includes('\uFFFD'),
                    importe: /24[.,]70|123[.,]50/.test(document.body.textContent),
                    formularios: [...document.querySelectorAll('form')].every(f => f.method !== 'post' || !!f.querySelector('[name="_token"]'))
                })` });
                const metrics = JSON.parse(result.value);
                assert.equal(metrics.viewport, width);
                assert(metrics.scroll <= width + 1, name + '@' + width + ': desbordamiento horizontal (' + metrics.scroll + ')');
                assert(metrics.aviso && metrics.codificacion && (metrics.importe || name === 'recuperacion') && metrics.formularios, name + ': aviso/codificación/importe/CSRF');
                if ((width === 375 && ['tarjeta-pendiente','reportes-pagos','caja-version'].includes(name))
                    || (width === 1440 && ['qr-pendiente','reportes-pagos'].includes(name))) {
                    const { data } = await page('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
                    fs.writeFileSync(path.join(fixtures, name + '-' + width + '.png'), Buffer.from(data,'base64'));
                }
                checks++;
            }
        }
        process.stdout.write(JSON.stringify({ resultado: 'PASS', pantallas_y_anchos: checks, conexiones_aplicacion_o_bancarias: false }) + '\n');
        await call('Browser.close');
    } finally {
        if (ws) ws.close();
        child.kill(); // Solo el proceso que creó este arnés y su perfil temporal independiente.
    }
})().catch(error => { process.stderr.write(error.stack + '\n'); process.exitCode = 1; });
