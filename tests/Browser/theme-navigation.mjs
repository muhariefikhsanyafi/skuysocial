import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { once } from 'node:events';
import fs from 'node:fs/promises';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const options = Object.fromEntries(process.argv.slice(2).map(argument => {
    const split = argument.indexOf('=');
    return [argument.slice(2, split), argument.slice(split + 1)];
}));
const phase = options.phase ?? 'after';
assert(['before', 'after'].includes(phase));
const output = path.resolve(options.output ?? path.join(os.tmpdir(), 'skuy-theme-browser-results'), phase);
const temporary = await fs.mkdtemp(path.join(os.tmpdir(), 'skuy-theme-browser-'));
const browserExecutable = options.browser ?? process.env.BROWSER_EXECUTABLE;
const php = options.php ?? process.env.PHP_EXECUTABLE ?? 'php';
assert(browserExecutable, 'Set BROWSER_EXECUTABLE or --browser= to an installed Chromium/Edge executable.');
const manifest = JSON.parse(await fs.readFile(path.join(root, 'public/build/manifest.json'), 'utf8'));
assert(!(await fs.stat(path.join(root, 'public/hot')).catch(() => null)), 'Stop the Vite dev server/remove its stale hot marker before running built-asset QA.');
const appModule = '/build/' + manifest['resources/js/app.js'].file;
await fs.mkdir(output, { recursive: true });
await fs.mkdir(path.join(temporary, 'views'));
await fs.writeFile(path.join(temporary, 'qa.sqlite'), '');
const portServer = net.createServer();
portServer.listen(0, '127.0.0.1');
await once(portServer, 'listening');
const port = portServer.address().port;
await new Promise(resolve => portServer.close(resolve));
const base = `http://127.0.0.1:${port}`;
const env = {
    ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: base,
    APP_KEY: 'base64:' + Buffer.alloc(32, 7).toString('base64'),
    APP_CONFIG_CACHE: path.join(temporary, 'config.php'), APP_ROUTES_CACHE: path.join(temporary, 'routes.php'),
    DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(temporary, 'qa.sqlite'), DB_URL: '',
    CACHE_STORE: 'array', SESSION_DRIVER: 'database', SESSION_CONNECTION: 'sqlite',
    SESSION_COOKIE: 'skuy_theme_browser', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false',
    VIEW_COMPILED_PATH: path.join(temporary, 'views'), BCRYPT_ROUNDS: '4',
    QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', LOG_CHANNEL: 'stderr',
    ONESIGNAL_ENABLED: 'false', TURNSTILE_ENABLED: 'false',
};
function command(executable, args, cwd = root) {
    return new Promise((resolve, reject) => {
        const child = spawn(executable, args, { cwd, env, windowsHide: true });
        let stdout = '', stderr = '';
        child.stdout.on('data', chunk => { stdout += chunk; });
        child.stderr.on('data', chunk => { stderr += chunk; });
        child.on('error', reject);
        child.on('exit', code => code === 0 ? resolve(stdout) : reject(new Error(`${path.basename(executable)} exited ${code}: ${stderr.slice(-1500)} ${stdout.slice(-1500)}`)));
    });
}
const results = { phase, engine: 'installed Chromium via CDP', checks: [], paints: [], badges: [], desktop: [], savedSearchVisual: [], flowVisual: [], appreciationVisual: [], externalRequestsBlocked: [] };
function check(name, condition, evidence) {
    results.checks.push({ name, pass: Boolean(condition), evidence });
    if (!condition) console.log(`FAIL ${name}: ${JSON.stringify(evidence).slice(0,600)}`);
}
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(callback, description, timeout = 15000) {
    const deadline = Date.now() + timeout;
    while (Date.now() < deadline) {
        const value = await callback();
        if (value) return value;
        await pause(40);
    }
    throw new Error(`Timed out: ${description}`);
}
class CDP {
    constructor(socket) {
        this.socket = socket;
        this.id = 0;
        this.pending = new Map();
        this.listeners = new Map();
        socket.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (message.id) {
                const pending = this.pending.get(message.id);
                this.pending.delete(message.id);
                message.error ? pending?.reject(new Error(JSON.stringify(message.error))) : pending?.resolve(message.result);
            } else {
                for (const listener of this.listeners.get(message.method) ?? []) listener(message.params);
            }
        });
    }
    on(method, listener) {
        this.listeners.set(method, [...(this.listeners.get(method) ?? []), listener]);
    }
    send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = ++this.id;
            // A stalled DevTools command must fail with diagnostics, not hang CI for 15 minutes.
            const timer = setTimeout(() => {
                this.pending.delete(id);
                reject(new Error(`CDP command timed out after 30s: ${method}`));
            }, 30000);
            this.pending.set(id, {
                resolve: value => { clearTimeout(timer); resolve(value); },
                reject: error => { clearTimeout(timer); reject(error); },
            });
            try {
                this.socket.send(JSON.stringify({ id, method, params }));
            } catch (error) {
                this.pending.delete(id);
                clearTimeout(timer);
                reject(error);
            }
        });
    }
    async evaluate(expression) {
        const result = await this.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.text + ': ' + (result.exceptionDetails.exception?.description ?? expression));
        return result.result.value;
    }
}
let server, browser, cdp;
let serverLog = '', holdModule = false, heldRequests = [], csp = false;
try {
    const fixtureOutput = await command(php, ['tests/Browser/fixtures/theme-navigation.php']);
    let fixture;
    try { fixture = JSON.parse(fixtureOutput); } catch { throw new Error('Fixture did not return JSON: '+fixtureOutput.slice(-2000)); }
    server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', path.join(root, 'public'), path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.join(root, 'public'), env, windowsHide: true });
    server.stdout.on('data', chunk => { serverLog = (serverLog + chunk).slice(-12000); });
    server.stderr.on('data', chunk => { serverLog = (serverLog + chunk).slice(-12000); });
    await until(() => fetch(base + '/login').then(async response => {
        if (!response.ok) serverLog = (serverLog + `\nReadiness HTTP ${response.status}: ${(await response.text()).slice(0, 1000)}`).slice(-12000);
        return response.ok;
    }).catch(error => {
        serverLog = (serverLog + `\nReadiness fetch error: ${error.message}; cause=${error.cause?.code ?? error.cause?.message ?? 'unknown'}; serverExit=${server.exitCode}`).slice(-12000);
        return false;
    }), 'isolated PHP server');
    // Fresh user-data-dir per attempt prevents a crashed Chromium startup from
    // poisoning a subsequent one. Never retry a failed assertion or actual QA.
    let debuggingPort;
    for (let attempt = 1; attempt <= 2; attempt++) {
        const browserProfile = path.join(temporary, `browser-${attempt}`);
        let startupError = null, browserLog = '';
        browser = spawn(browserExecutable, [
            '--headless=new', '--no-first-run', '--no-default-browser-check',
            '--disable-background-networking', '--disable-component-update', '--disable-sync',
            '--disable-dev-shm-usage', '--disable-gpu',
            '--remote-debugging-port=0', '--remote-debugging-address=127.0.0.1',
            `--user-data-dir=${browserProfile}`, 'about:blank',
        ], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
        browser.on('error', error => { startupError = error.message; });
        browser.stderr.on('data', chunk => { browserLog = (browserLog + chunk).slice(-1500); });
        try {
            debuggingPort = await until(async () => {
                if (startupError) throw new Error('Chromium could not start: ' + startupError);
                if (browser.exitCode !== null || browser.signalCode !== null) {
                    throw new Error(`Chromium exited before CDP became ready (exit=${browser.exitCode}, signal=${browser.signalCode})`);
                }
                return fs.readFile(path.join(browserProfile, 'DevToolsActivePort'), 'utf8')
                    .then(text => {
                        const port = Number(text.split('\n')[0]);
                        return Number.isInteger(port) && port > 0 && port <= 65535 ? port : false;
                    }).catch(() => false);
            }, 'browser CDP startup', 25000);
            break;
        } catch (error) {
            browser.kill();
            if (attempt === 2) {
                throw new Error(`${error.message} (after 2 isolated startup attempts); Chromium diagnostics: ${browserLog.slice(-750)}`);
            }
            console.warn('Chromium CDP startup unavailable; retrying once with a fresh isolated profile.');
            await pause(1000);
        }
    }
    const tabs = await fetch(`http://127.0.0.1:${debuggingPort}/json/list`).then(response => response.json());
    const socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    cdp = new CDP(socket);
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Network.enable');
    await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
    cdp.on('Fetch.requestPaused', async event => {
        try {
            if (event.responseStatusCode) {
                if (!csp || event.responseStatusCode !== 200) return await cdp.send('Fetch.continueRequest', { requestId: event.requestId });
                const response = await cdp.send('Fetch.getResponseBody', { requestId: event.requestId });
                const body = response.base64Encoded ? Buffer.from(response.body, 'base64').toString() : response.body;
                const bootstrap = body.match(/<script[^>]*data-skuy-theme[^>]*>([\s\S]*?)<\/script>/)?.[1];
                assert(bootstrap, 'CSP test requires the shared theme bootstrap.');
                const hash = createHash('sha256').update(bootstrap).digest('base64');
                const headers = (event.responseHeaders ?? []).filter(header => !['content-security-policy', 'content-length'].includes(header.name.toLowerCase()));
                headers.push({ name: 'Content-Security-Policy', value: `default-src 'self'; script-src 'self' 'unsafe-eval' 'sha256-${hash}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'` });
                return await cdp.send('Fetch.fulfillRequest', { requestId: event.requestId, responseCode: 200, responseHeaders: headers, body: Buffer.from(body).toString('base64') });
            }
            if (new URL(event.request.url).origin !== base) {
                results.externalRequestsBlocked.push(event.request.url.split('?')[0]);
                return await cdp.send('Fetch.fulfillRequest', { requestId: event.requestId, responseCode: 200, responseHeaders: [{ name: 'Content-Type', value: event.resourceType === 'Stylesheet' ? 'text/css' : 'text/plain' }], body: '' });
            }
            if (new URL(event.request.url).pathname.startsWith('/pengaturan/avatar/preview/')) {
                return await cdp.send('Fetch.fulfillRequest', { requestId:event.requestId,responseCode:503,body:'' });
            }
            if (holdModule && new URL(event.request.url).pathname === appModule) { heldRequests.push(event.requestId); return; }
            await cdp.send('Fetch.continueRequest', { requestId: event.requestId });
        } catch (error) { results.interceptionError = error.message; }
    });
    await cdp.send('Fetch.enable', { patterns: [{ urlPattern: '*', requestStage: 'Request' }, { urlPattern: '*', resourceType: 'Document', requestStage: 'Response' }] });
    await cdp.send('Page.addScriptToEvaluateOnNewDocument', { source: `
        window.__themeQA = { classes: [], paints: [], frames: [], violations: [], alpine: false };
        new MutationObserver(records => { if (records.some(record => record.target === document.documentElement)) __themeQA.classes.push({ at: performance.now(), dark: document.documentElement.classList.contains('dark') }); })
            .observe(document, { subtree: true, attributes: true, attributeFilter: ['class'] });
        new PerformanceObserver(list => __themeQA.paints.push(...list.getEntries().map(entry => ({ name: entry.name, at: entry.startTime }))))
            .observe({ type: 'paint', buffered: true });
        document.addEventListener('alpine:initialized', () => { __themeQA.alpine = true; __themeQA.alpineAt = performance.now(); });
        document.addEventListener('securitypolicyviolation', event => __themeQA.violations.push({ directive: event.effectiveDirective, blocked: event.blockedURI, line: event.lineNumber, sample: event.sample }));
        function frame() { if (document.body && __themeQA.frames.length < 120) __themeQA.frames.push({ at: performance.now(), dark: document.documentElement.classList.contains('dark'), background: getComputedStyle(document.body).backgroundColor }); if (__themeQA.frames.length < 120) requestAnimationFrame(frame); }
        requestAnimationFrame(frame);
    ` });
    async function viewport(width, height) {
        await cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
        await cdp.send('Emulation.setTouchEmulationEnabled', { enabled: width < 768 });
    }
    async function theme(value, system = 'light') {
        await cdp.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: system }] });
        await cdp.evaluate(value === null ? `localStorage.removeItem('theme')` : `localStorage.setItem('theme', ${JSON.stringify(value)})`);
    }
    async function navigate(route, reload = false) {
        const pathname = new URL(route, base).pathname;
        if (reload) await cdp.send('Page.reload', { ignoreCache: true });
        else await cdp.send('Page.navigate', { url: base + route });
        await until(() => cdp.evaluate(`document.readyState === 'complete' && location.pathname === ${JSON.stringify(pathname)} && (!!window.__themeQA?.alpine || !document.querySelector('[x-data]'))`).catch(() => false), 'complete route ' + route);
    }
    async function screenshot(name) {
        const shot = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        await fs.writeFile(path.join(output, name + '.png'), Buffer.from(shot.data, 'base64'));
        return `${phase}/${name}.png`;
    }
    async function capture(route, expected, label, reload = false, click = false) {
        console.log('Browser QA capture: ' + label);
        holdModule = true;
        heldRequests = [];
        if (click) {
            const clicked = await cdp.evaluate(`(() => { const link = [...document.querySelectorAll('a[href]')].find(a => new URL(a.href).pathname === ${JSON.stringify(route)}); if (!link) return false; link.click(); return true; })()`);
            assert(clicked, 'Expected real navigation anchor for ' + route);
        } else if (reload) await cdp.send('Page.reload', { ignoreCache: true });
        else await cdp.send('Page.navigate', { url: base + route });
        await until(() => heldRequests.length, 'held app module ' + route);
        await until(() => cdp.evaluate(`!!document.body && location.pathname === ${JSON.stringify(route)} && !!__themeQA.paints.length && document.styleSheets.length > 0`).catch(() => false), 'paint while Alpine module is held');
        const early = await cdp.evaluate(`({ ...__themeQA, dark: document.documentElement.classList.contains('dark'), background: getComputedStyle(document.body).backgroundColor, content: document.body.innerText.length, csp: document.querySelector('meta[http-equiv="Content-Security-Policy"]')?.content ?? null })`);
        const earlyScreenshot = await screenshot(label + '-first-paint');
        holdModule = false;
        await Promise.all(heldRequests.map(requestId => cdp.send('Fetch.continueRequest', { requestId })));
        await until(() => cdp.evaluate('window.__themeQA.alpine && document.readyState === "complete"'), 'Alpine initialized');
        await cdp.evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
        const settled = await cdp.evaluate(`({ ...__themeQA, dark: document.documentElement.classList.contains('dark'), background: getComputedStyle(document.body).backgroundColor })`);
        const settledScreenshot = await screenshot(label + '-settled');
        results.paints.push({ label, route, expected, early, settled, earlyScreenshot, settledScreenshot });
        const firstPaint = early.paints.find(paint => paint.name === 'first-paint')?.at;
        const appliedDark = early.classes.find(change => change.dark)?.at;
        check(label + ' first paint', early.dark === (expected === 'dark') && early.frames.every(frame => frame.dark === (expected === 'dark')) && (expected !== 'dark' || appliedDark <= firstPaint) && early.background === settled.background, { earlyDark: early.dark, paints: early.paints, classChanges: settled.classes, earlyBackground:early.background,settledBackground:settled.background });
        check(label + ' Alpine retains theme', settled.dark === (expected === 'dark'), settled.dark);
        if (csp) check(label + ' enforced CSP bootstrap', await cdp.evaluate(`typeof window.skuyTheme?.persist === 'function'`), {otherViolations:settled.violations});
    }
    async function login(count) {
        await cdp.send('Network.clearBrowserCookies');
        await navigate('/login');
        await cdp.evaluate(`(() => { document.querySelector('#email').value = ${JSON.stringify(fixture.accounts[count].email)}; document.querySelector('#password').value = 'password'; document.querySelector('.skuy-auth-form').requestSubmit(); })()`);
        await until(() => cdp.evaluate(`location.pathname !== '/login' && document.readyState === 'complete' && !!document.querySelector('[data-mobile-nav-key]')`), 'synthetic login');
        await command(php, ['tests/Browser/fixtures/theme-navigation.php','unread',String(count)]);
    }
    async function badges(route, count, width, mode, label) {
        await navigate(route);
        const measured = await cdp.evaluate(`(() => {
            const rect = node => { const r = node.getBoundingClientRect(); return { x:r.x,y:r.y,width:r.width,height:r.height }; };
            const measure = key => { const item=document.querySelector('[data-mobile-nav-key="'+key+'"]'); const wrapper=item.querySelector('.skuy-mobile-nav__icon-wrap'); const badge=wrapper.querySelector('.skuy-mobile-nav__badge'); if (!badge) return null; const b=rect(badge),w=rect(wrapper),s=getComputedStyle(badge),clippedBy=[]; for(let parent=badge.parentElement;parent;parent=parent.parentElement){ const r=rect(parent),style=getComputedStyle(parent); if((['hidden','clip','auto','scroll'].includes(style.overflowX)&&(b.x<r.x-.1||b.x+b.width>r.x+r.width+.1))||(['hidden','clip','auto','scroll'].includes(style.overflowY)&&(b.y<r.y-.1||b.y+b.height>r.y+r.height+.1)))clippedBy.push(parent.className); } return { text:badge.textContent.trim(),dx:b.x-w.x,dy:b.y-w.y,width:b.width,height:b.height,wrapperWidth:w.width,wrapperHeight:w.height,background:s.backgroundColor,color:s.color,font:s.fontSize,radius:s.borderRadius,transform:s.transform,position:s.position,active:item.getAttribute('aria-current'),label:badge.getAttribute('aria-label'),paintVisible:document.elementFromPoint(b.x+b.width/2,b.y+b.height/2)===badge,clippedBy }; };
            const normal={activity:measure('activity'),messages:measure('messages')};
            for(const key of ['activity','messages']) document.querySelector('[data-mobile-nav-key="'+key+'"]').setAttribute('aria-current','page');
            const active={activity:measure('activity'),messages:measure('messages')};
            return { normal,active, keys:[...document.querySelectorAll('[data-mobile-nav-key]')].map(n=>n.dataset.mobileNavKey),overflow:document.documentElement.scrollWidth>innerWidth,lang:document.documentElement.lang };
        })()`);
        const image = await screenshot(label);
        results.badges.push({ route, count, width, mode, measured, image });
        check(label + ' count / five destinations / overflow', measured.keys.join(',') === 'home,search,create,activity,messages' && !measured.overflow && ['activity','messages'].every(key => count === 0 ? measured.normal[key] === null : measured.normal[key]?.text === String(count)), measured);
        check(label + ' badge visible without ancestor clipping', ['normal','active'].every(state=>['activity','messages'].every(key=>!measured[state][key] || (measured[state][key].paintVisible && !measured[state][key].clippedBy.length))), measured);
        return measured;
    }
    function signature(badge) {
        if (!badge) return null;
        const { active, label, paintVisible, clippedBy, ...shape } = badge;
        return shape;
    }
    if (options.appreciationOnly === '1') {
        const statusUid = fixture.appreciationStatusPost;
        await login(2);
        for (const width of [1536, 1280, 390, 320]) {
            const height = width === 1536 ? 864 : width === 1280 ? 800 : width === 390 ? 844 : 740;
            await viewport(width, height);
            for (const mode of ['dark', 'light']) {
                await theme(mode);
                await navigate(fixture.savedPage);
                const shell = await cdp.evaluate(`(() => ({theme:document.documentElement.classList.contains('dark')?'dark':'light',width:innerWidth,nav:[...document.querySelectorAll('[data-mobile-nav-key]')].map(item=>item.dataset.mobileNavKey),dockVisible:Boolean(document.querySelector('.skuy-mobile-nav')?.getClientRects().length),topbar:Boolean(document.querySelector('.skuy-topbar')),rail:Boolean(document.querySelector('.skuy-app-rail')),badges:Object.fromEntries([...document.querySelectorAll('[data-mobile-nav-key]')].map(item=>[item.dataset.mobileNavKey,item.querySelector('.skuy-mobile-nav__badge')?.textContent.trim()??null])),overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth}))()`);
                check(`Feed theme, navigation, badges and viewport ${width} ${mode}`, shell.theme === mode && shell.nav.join(',') === 'home,search,create,activity,messages' && shell.topbar && shell.rail && (width >= 768 || shell.dockVisible) && shell.badges.activity === '2' && shell.badges.messages === '2' && !shell.overflow, shell);
                await cdp.evaluate(`(() => { const card=document.querySelector('[data-feed-post-uid="${statusUid}"]'); if(!card)return false; window.scrollBy(0,card.getBoundingClientRect().top-112); return true; })()`);
                await pause(180);
                const statusMetrics = await cdp.evaluate(`(() => {
                    const card=document.querySelector('[data-feed-post-uid="${statusUid}"]');
                    const status=card?.querySelector('.skuy-appreciation-status');
                    const preview=card?.querySelector('.skuy-feed-post__comment-preview');
                    const wrapper=status?.parentElement;
                    if(!card||!status||!preview)return null;
                    const s=status.getBoundingClientRect(),c=preview.getBoundingClientRect(),w=wrapper.getBoundingClientRect();
                    return { statusText:status.innerText.trim(),statusTop:s.top,statusBottom:s.bottom,statusHeight:s.height,commentTop:c.top,commentBottom:c.bottom,gap:c.top-s.bottom,wrapperHeight:w.height,wrapperCssHeight:getComputedStyle(wrapper).height,wrapperMinHeight:getComputedStyle(wrapper).minHeight,commentText:preview.innerText.trim(),theme:document.documentElement.classList.contains('dark')?'dark':'light',width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth };
                })()`);
                const statusImage = await screenshot(`appreciation-status-${width}-${mode}`);
                results.appreciationVisual.push({ kind: 'status-comment', width, height, mode, metrics: statusMetrics, image: statusImage });
                check(`Appreciation status and comment stay in flow ${width} ${mode}`, Boolean(statusMetrics) && statusMetrics.gap >= -0.5 && statusMetrics.statusText.length > 0 && statusMetrics.commentText.length > 0 && !statusMetrics.overflow, statusMetrics);
            }
        }

        await login(0);
        for (const width of [1536, 1280, 390, 320]) {
            const height = width === 1536 ? 864 : width === 1280 ? 800 : width === 390 ? 844 : 740;
            await viewport(width, height);
            for (const mode of ['dark', 'light']) {
                await theme(mode);
                await navigate(fixture.savedPage);
                const listedUids = await cdp.evaluate(`([...document.querySelectorAll('.skuy-saved-post-list > [data-feed-post-uid]')].map(card=>card.dataset.feedPostUid))`);
                check(`Saved fixture order ${width} ${mode}`, JSON.stringify(listedUids) === JSON.stringify(fixture.appreciationChoicePosts), { listedUids, expected: fixture.appreciationChoicePosts });
                const commentStates = await cdp.evaluate(`(() => [...document.querySelectorAll('.skuy-saved-post-list > [data-feed-post-uid]')].map(card=>({uid:card.dataset.feedPostUid,hasComment:Boolean(card.querySelector('.skuy-feed-post__comment-preview'))})))()`);
                check(`Saved fixture has comment and no-comment posts ${width} ${mode}`, commentStates.filter(item=>item.hasComment).length === 2 && commentStates.filter(item=>!item.hasComment).length === 1, commentStates);

                for (const [index, position] of [[0, 'first'], [1, 'middle'], [2, 'last']]) {
                    const uid = listedUids[index];
                    const cardSelector = `[data-feed-post-uid="${uid}"]`;
                    const triggerSelector = `${cardSelector} [data-post-appreciation-trigger]`;
                    const popoverSelector = `${cardSelector} .skuy-appreciation__popover`;
                    const overlaySelector = `${cardSelector} .skuy-appreciation__overlay`;
                    await cdp.evaluate(`(() => { const card=document.querySelector(${JSON.stringify(cardSelector)}); if(!card)return false; window.scrollBy(0,card.getBoundingClientRect().top-112); return true; })()`);
                    await pause(180);
                    const triggerExists = await cdp.evaluate(`Boolean(document.querySelector(${JSON.stringify(triggerSelector)}))`);
                    check(`Appreciation trigger available at ${position} Saved card ${width} ${mode}`, triggerExists, { uid, position });
                    if (!triggerExists) continue;
                    const clickAt = async selector => {
                        const point = await cdp.evaluate(`(() => { const node=document.querySelector(${JSON.stringify(selector)}); if(!node)return null; const r=node.getBoundingClientRect(); return {x:r.left+r.width/2,y:r.top+r.height/2}; })()`);
                        assert(point, `Missing click target ${selector}`);
                        await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...point });
                        await cdp.send('Input.dispatchMouseEvent', { type: 'mousePressed', ...point, button: 'left', clickCount: 1 });
                        await cdp.send('Input.dispatchMouseEvent', { type: 'mouseReleased', ...point, button: 'left', clickCount: 1 });
                        return point;
                    };
                    await clickAt(triggerSelector);
                    await until(() => cdp.evaluate(`document.querySelector(${JSON.stringify(popoverSelector)}) && getComputedStyle(document.querySelector(${JSON.stringify(overlaySelector)})).display !== 'none'`), `popover opens on ${position} card ${width} ${mode}`);
                    await pause(230);
                    const optionSelector = `${cardSelector} .skuy-appreciation__choice`;
                    const optionPoint = await cdp.evaluate(`(() => { const nodes=document.querySelectorAll(${JSON.stringify(optionSelector)}); const node=nodes[nodes.length-1]; if(!node)return null; const r=node.getBoundingClientRect(); return {x:r.left+r.width/2,y:r.top+r.height/2}; })()`);
                    assert(optionPoint, `No Appreciation choices on ${position} card`);
                    await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...optionPoint });
                    await pause(120);
                    const popupMetrics = await cdp.evaluate(`(() => {
                        const card=document.querySelector(${JSON.stringify(cardSelector)}),overlay=card?.querySelector('.skuy-appreciation__overlay'),panel=card?.querySelector('.skuy-appreciation__popover');
                        if(!card||!overlay||!panel)return null;
                        const r=panel.getBoundingClientRect(),target=document.elementFromPoint(${optionPoint.x},${optionPoint.y}),dock=document.querySelector('.skuy-mobile-nav'),bound=dock&&dock.getClientRects().length?dock.getBoundingClientRect().top:innerHeight;
                        const ancestorClipping=[...function*(){for(let node=overlay.parentElement;node&&node!==document.body&&node!==document.documentElement;node=node.parentElement)yield node}()].filter(node=>['hidden','clip'].includes(getComputedStyle(node).overflowY)||['hidden','clip'].includes(getComputedStyle(node).overflowX)).map(node=>({tag:node.tagName,class:node.className.toString(),overflowX:getComputedStyle(node).overflowX,overflowY:getComputedStyle(node).overflowY}));
                        return {position:overlay.classList.contains('is-above')?'above':'below',presentation:panel.dataset.appreciationPresentation,choices:panel.querySelectorAll('.skuy-appreciation__choice').length,panel:{left:r.left,top:r.top,right:r.right,bottom:r.bottom,width:r.width,height:r.height},bound,viewport:{width:innerWidth,layoutWidth:document.documentElement.clientWidth,height:innerHeight},targetClass:target?.className?.toString()??target?.tagName??null,targetPost:target?.closest('[data-feed-post-uid]')?.dataset.feedPostUid??null,hoveredPostUids:[...document.querySelectorAll('.skuy-saved-post-list > [data-feed-post-uid]:hover')].map(node=>node.dataset.feedPostUid),sourcePost:card.dataset.feedPostUid,scrollWidth:document.documentElement.scrollWidth,horizontalOverflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,overlayPointerEvents:getComputedStyle(overlay).pointerEvents,ancestorClipping};
                    })()`);
                    const image = await screenshot(`appreciation-popover-${width}-${mode}-${position}`);
                    results.appreciationVisual.push({ kind: 'popover', width, height, mode, position, metrics: popupMetrics, image });
                    const withinViewport = popupMetrics && popupMetrics.panel.left >= 0 && popupMetrics.panel.top >= 0 && popupMetrics.panel.right <= popupMetrics.viewport.layoutWidth + 0.5 && popupMetrics.panel.bottom <= popupMetrics.bound + 0.5;
                    check(`Appreciation popup is clickable and stays in viewport ${width} ${mode} ${position}`, Boolean(popupMetrics) && popupMetrics.choices === 3 && withinViewport && !popupMetrics.ancestorClipping.length && !popupMetrics.horizontalOverflow, popupMetrics);
                    check(`Pointer over Appreciation does not hover a card behind it ${width} ${mode} ${position}`, Boolean(popupMetrics) && popupMetrics.targetPost === uid && popupMetrics.hoveredPostUids.every(postUid => postUid === uid), popupMetrics);

                    await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                    await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                    await until(() => cdp.evaluate(`getComputedStyle(document.querySelector(${JSON.stringify(overlaySelector)})).display === 'none'`), `Escape closes ${position} popover`);
                    await until(() => cdp.evaluate(`document.activeElement===document.querySelector(${JSON.stringify(triggerSelector)})`), `focus returns to ${position} trigger`);
                    const escapeState = await cdp.evaluate(`({focus:document.activeElement===document.querySelector(${JSON.stringify(triggerSelector)}),bodyOverflow:document.body.style.overflow,expanded:document.querySelector(${JSON.stringify(triggerSelector)}).getAttribute('aria-expanded')})`);
                    check(`Appreciation closes and restores focus ${width} ${mode} ${position}`, escapeState.focus && escapeState.expanded === 'false' && (width >= 768 || escapeState.bodyOverflow !== 'hidden'), escapeState);

                    await clickAt(triggerSelector);
                    await until(() => cdp.evaluate(`getComputedStyle(document.querySelector(${JSON.stringify(overlaySelector)})).display !== 'none'`), `reopen ${position} popover`);
                    if (width < 768) {
                        await clickAt(`${cardSelector} .skuy-appreciation__dismiss`);
                        await until(() => cdp.evaluate(`getComputedStyle(document.querySelector(${JSON.stringify(overlaySelector)})).display === 'none'`), `dismiss ${position} mobile sheet`);
                    } else {
                        await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                        await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                        await until(() => cdp.evaluate(`getComputedStyle(document.querySelector(${JSON.stringify(overlaySelector)})).display === 'none'`), `repeat Escape closes ${position} popover`);
                    }
                }

                if (width >= 768) {
                    const edgeUid = listedUids[2];
                    const edgeCard = `[data-feed-post-uid="${edgeUid}"]`;
                    const edgeTrigger = `${edgeCard} [data-post-appreciation-trigger]`;
                    const edgePanel = `${edgeCard} .skuy-appreciation__popover`;
                    const edgeOverlay = `${edgeCard} .skuy-appreciation__overlay`;
                    await cdp.evaluate(`(() => {const card=document.querySelector(${JSON.stringify(edgeCard)});if(!card)return false;const r=card.getBoundingClientRect();const desiredTop=Math.max(112,innerHeight-r.height-28);window.scrollBy(0,r.top-desiredTop);return true;})()`);
                    await pause(180);
                    const edgePoint = await cdp.evaluate(`(() => {const n=document.querySelector(${JSON.stringify(edgeTrigger)}),r=n?.getBoundingClientRect();return r?{x:r.left+r.width/2,y:r.top+r.height/2}:null;})()`);
                    assert(edgePoint, `Missing viewport-edge trigger at ${width} ${mode}`);
                    await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...edgePoint });
                    await cdp.send('Input.dispatchMouseEvent', { type: 'mousePressed', ...edgePoint, button: 'left', clickCount: 1 });
                    await cdp.send('Input.dispatchMouseEvent', { type: 'mouseReleased', ...edgePoint, button: 'left', clickCount: 1 });
                    await until(() => cdp.evaluate(`getComputedStyle(document.querySelector(${JSON.stringify(edgeOverlay)})).display !== 'none'`), `edge popover opens ${width} ${mode}`);
                    await pause(230);
                    const edgeMetrics = await cdp.evaluate(`(() => {const panel=document.querySelector(${JSON.stringify(edgePanel)}),overlay=document.querySelector(${JSON.stringify(edgeOverlay)}),r=panel?.getBoundingClientRect();return r?{placement:overlay.classList.contains('is-above')?'above':'below',panel:{left:r.left,top:r.top,right:r.right,bottom:r.bottom},layoutWidth:document.documentElement.clientWidth,height:innerHeight,scrollWidth:document.documentElement.scrollWidth}:null;})()`);
                    const edgeImage = await screenshot(`appreciation-popover-edge-${width}-${mode}`);
                    results.appreciationVisual.push({ kind: 'viewport-edge', width, height, mode, metrics: edgeMetrics, image: edgeImage });
                    check(`Appreciation adapts near viewport bottom ${width} ${mode}`, Boolean(edgeMetrics) && edgeMetrics.placement === 'above' && edgeMetrics.panel.top >= 0 && edgeMetrics.panel.left >= 0 && edgeMetrics.panel.right <= edgeMetrics.layoutWidth + 0.5 && edgeMetrics.panel.bottom <= edgeMetrics.height + 0.5 && edgeMetrics.scrollWidth <= edgeMetrics.layoutWidth, edgeMetrics);
                    await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                    await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                    await until(() => cdp.evaluate(`getComputedStyle(document.querySelector(${JSON.stringify(edgeOverlay)})).display === 'none'`), `edge popover closes ${width} ${mode}`);
                }
            }
        }

        const choiceUid = fixture.appreciationChoicePosts[0];
        const choiceCard = `[data-feed-post-uid="${choiceUid}"]`;
        const choiceOverlay = `${choiceCard} .skuy-appreciation__overlay`;
        await viewport(1536, 864);
        await theme('dark');
        await navigate(fixture.savedPage);
        await cdp.evaluate(`(() => {const card=document.querySelector(${JSON.stringify(choiceCard)});if(!card)return false;window.scrollBy(0,card.getBoundingClientRect().top-112);return true;})()`);
        const trigger = `${choiceCard} [data-post-appreciation-trigger]`;
        await cdp.evaluate(`document.querySelector(${JSON.stringify(trigger)}).focus()`);
        const clickTarget = async selector => {
            const point = await cdp.evaluate(`(() => { const node=document.querySelector(${JSON.stringify(selector)}); if(!node)return null; const r=node.getBoundingClientRect(); return {x:r.left+r.width/2,y:r.top+r.height/2}; })()`);
            assert(point, `Missing actual selection target ${selector}`);
            await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...point });
            await cdp.send('Input.dispatchMouseEvent', { type: 'mousePressed', ...point, button: 'left', clickCount: 1 });
            await cdp.send('Input.dispatchMouseEvent', { type: 'mouseReleased', ...point, button: 'left', clickCount: 1 });
        };
        await clickTarget(trigger);
        await until(() => cdp.evaluate(`getComputedStyle(document.querySelector(${JSON.stringify(choiceOverlay)})).display !== 'none'`), 'actual-choice popover opens');
        await screenshot('appreciation-actual-choice-open');
        await clickTarget(`${choiceCard} .skuy-appreciation__choice[value="bermanfaat"]`);
        await until(() => cdp.evaluate(`location.pathname !== ${JSON.stringify(fixture.savedPage)} && document.readyState === 'complete'`), 'actual Appreciation click navigates or submits');
        await pause(300);
        const state = JSON.parse(await command(php, ['tests/Browser/fixtures/theme-navigation.php', 'appreciation-state', '0', choiceUid]));
        await navigate(fixture.savedPage);
        const selected = await cdp.evaluate(`(() => {const card=document.querySelector('[data-feed-post-uid="${choiceUid}"]');return {path:location.pathname,status:card?.querySelector('.skuy-appreciation-status')?.innerText.trim()??null,trigger:card?.querySelector('[data-post-appreciation-trigger]')??null,selected:card?.querySelector('.skuy-action--appreciated')?.getAttribute('aria-pressed')??null};})()`);
        check('Actual pointer click selects Appreciation and spends only five QA points', selected.path === '/tersimpan' && state.appreciation === 'bermanfaat' && state.pointsSpent === 5 && state.balance === 95 && selected.selected === 'true' && !selected.trigger && Boolean(selected.status), { state, selected });
        await screenshot('appreciation-actual-choice-saved');

        for (const [width, height, mode] of [[1280, 800, 'dark'], [390, 844, 'light']]) {
            await viewport(width, height);
            await theme(mode);
            for (const [surface, route] of [['feed', '/feed'], ['profile', fixture.otherProfile], ['search', fixture.searchResults]]) {
                await navigate(route);
                const firstPost = await cdp.evaluate(`(() => {const card=[...document.querySelectorAll('.skuy-feed-post[data-feed-post-uid]')].find(node=>node.dataset.feedThreadKey===node.dataset.feedPostUid&&node.querySelector('[data-post-appreciation-trigger]'));return card?card.dataset.feedPostUid:null;})()`);
                check(`Shared Appreciation renders on ${surface} ${width} ${mode}`, Boolean(firstPost), { route, firstPost });
                if (!firstPost) continue;
                const cardSelector = `.skuy-feed-post[data-feed-post-uid="${firstPost}"]`;
                const triggerSelector = `${cardSelector} [data-post-appreciation-trigger]`;
                const overlaySelector = `${cardSelector} .skuy-appreciation__overlay`;
                await cdp.evaluate(`(() => {const card=document.querySelector(${JSON.stringify(cardSelector)});if(!card)return false;window.scrollBy(0,card.getBoundingClientRect().top-112);return true;})()`);
                await clickTarget(triggerSelector);
                await until(() => cdp.evaluate(`getComputedStyle(document.querySelector(${JSON.stringify(overlaySelector)})).display !== 'none'`), `${surface} Appreciation opens`);
                await pause(230);
                const point = await cdp.evaluate(`(() => {const nodes=document.querySelectorAll(${JSON.stringify(`${cardSelector} .skuy-appreciation__choice`)}),node=nodes[nodes.length-1],r=node?.getBoundingClientRect();return r?{x:r.left+r.width/2,y:r.top+r.height/2}:null;})()`);
                assert(point, `No Appreciation choice on ${surface}`);
                await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...point });
                await pause(100);
                const interaction = await cdp.evaluate(`(() => {const target=document.elementFromPoint(${point.x},${point.y}),card=document.querySelector(${JSON.stringify(cardSelector)});return {source:card?.dataset.feedPostUid,target:target?.closest('[data-feed-post-uid]')?.dataset.feedPostUid??null,targetNode:target?{tag:target.tagName,class:target.className?.toString()??''}:null,point:{x:${point.x},y:${point.y}},viewport:{width:innerWidth,height:innerHeight},hovered:[...document.querySelectorAll('.skuy-feed-post:hover')].map(node=>node.dataset.feedPostUid),overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth};})()`);
                const image = await screenshot(`appreciation-shared-${surface}-${width}-${mode}`);
                results.appreciationVisual.push({ kind: 'shared-surface', surface, width, height, mode, metrics: interaction, image });
                check(`Shared Appreciation stays above adjacent posts on ${surface} ${width} ${mode}`, interaction.target === firstPost && interaction.hovered.every(uid => uid === firstPost) && !interaction.overflow, interaction);
                await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                await until(() => cdp.evaluate(`getComputedStyle(document.querySelector(${JSON.stringify(overlaySelector)})).display === 'none'`), `${surface} Appreciation closes`);
            }
        }
    } else {
    await viewport(390, 844);
    await login(2);
    const own = fixture.accounts[2];
    // Real Chromium layout acceptance of the empty-cover, verified visitor Profile.
    // Unlike a green route/status test, these assertions inspect the visible computed result.
    for (const [profileWidth, profileMode] of [[1280, 'light'], [1280, 'dark'], [390, 'light'], [390, 'dark']]) {
        await viewport(profileWidth, profileWidth === 390 ? 844 : 800);
        await theme(profileMode);
        await navigate(fixture.otherProfile);
        const profileVisual = await cdp.evaluate(`(() => {
            const root = document.querySelector('[data-skuy-profile]');
            const header = root?.querySelector('[data-profile-hero]');
            const cover = header?.querySelector('.skuy-profile-cover');
            const avatar = header?.querySelector('.skuy-profile-avatar');
            const identity = header?.querySelector('[data-profile-identity]');
            const actions = header?.querySelector('[data-profile-actions]');
            const verified = identity?.querySelector('[data-flow-verified]');
            const rect = node => {
                if (!node) return null;
                const r = node.getBoundingClientRect();
                return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height};
            };
            return {
                scope: document.documentElement.dataset.flowPage,
                mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                coverClass: header?.classList.contains('skuy-profile-header--no-cover'),
                coverDisplay: cover ? getComputedStyle(cover).display : null,
                badgeSvg: !!verified?.querySelector('svg'),
                badgeColor: verified ? getComputedStyle(verified).color : null,
                avatar: rect(avatar), identity: rect(identity), actions: rect(actions),
                overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
            };
        })()`);
        await screenshot(`profile-visual-fix-${profileWidth}-${profileMode}`);
        const expectedColor = profileMode === 'light' ? 'rgb(110, 91, 239)' : 'rgb(169, 152, 255)';
        const aligned = profileVisual.avatar && profileVisual.identity && profileVisual.actions
            && profileVisual.identity.left >= profileVisual.avatar.right - 2
            && (profileWidth >= 768
                ? profileVisual.actions.left >= profileVisual.identity.right - 2
                : profileVisual.actions.top >= profileVisual.avatar.bottom - 2);
        check(`Profile visual QA: no phantom cover, Flow badge, readable composition ${profileWidth}px ${profileMode}`,
            profileVisual.scope === 'profile'
            && profileVisual.mode === profileMode
            && profileVisual.coverClass
            && profileVisual.coverDisplay === 'none'
            && profileVisual.badgeSvg
            && profileVisual.badgeColor === expectedColor
            && aligned && !profileVisual.overflow, profileVisual);
    }
    for (const width of [1536, 1280, 1024, 390, 320]) {
        const height = width === 1536 ? 960 : width === 1280 ? 900 : width === 1024 ? 900 : width === 390 ? 844 : 740;
        await viewport(width, height);

        for (const mode of ['dark', 'light']) {
            await theme(mode);

            for (const [page, route] of [
                ['saved', fixture.savedPage],
                ['search-initial', '/cari'],
                ['search-results', fixture.searchResults],
            ]) {
                await navigate(route);
                await cdp.evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');

                const metrics = await cdp.evaluate(`(() => {
                    const rect = node => { const value = node.getBoundingClientRect(); return { x: value.x, y: value.y, width: value.width, height: value.height }; };
                    const nav = [...document.querySelectorAll('[data-mobile-nav-key]')].map(node => node.dataset.mobileNavKey);
                    const rail = document.querySelector('.skuy-app-rail');
                    const context = document.querySelector('.skuy-feed-context');
                    const scrollableSidebars = [rail, context].filter(node => node && ['auto', 'scroll'].includes(getComputedStyle(node).overflowY)).map(node => node.className);
                    return {
                        flowPage: document.documentElement.dataset.flowPage,
                        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                        width: innerWidth,
                        scrollWidth: document.documentElement.scrollWidth,
                        overflow: document.documentElement.scrollWidth > innerWidth,
                        nav,
                        activeNav: [...document.querySelectorAll('.skuy-app-nav__link[aria-current="page"]')].map(node => node.dataset.navKey),
                        savedPostCount: document.querySelectorAll('[data-skuy-prism-saved] [data-feed-post-uid]').length,
                        searchPostCount: document.querySelectorAll('.skuy-discovery-post-list [data-feed-post-uid], .skuy-discovery-post-list > article').length,
                        hasInitialSearchState: Boolean(document.querySelector('.skuy-discovery-empty--initial')),
                        hasSearchInput: Boolean(document.querySelector('#discovery-search-input')),
                        rail: rail ? { ...rect(rail), position: getComputedStyle(rail).position } : null,
                        context: context ? { ...rect(context), position: getComputedStyle(context).position } : null,
                        scrollableSidebars,
                    };
                })()`);
                const image = await screenshot(`saved-search-${page}-${width}-${mode}`);
                results.savedSearchVisual.push({ page, route, width, height, mode, metrics, image });

                check(`${page} ${width}px ${mode}: viewport and five-item navigation`,
                    !metrics.overflow && metrics.nav.join(',') === 'home,search,create,activity,messages', metrics);
                if (width >= 1280) {
                    check(`${page} ${width}px ${mode}: Feed rail remains sticky without internal scroll`,
                        metrics.rail?.position === 'sticky' && metrics.scrollableSidebars.length === 0, metrics);
                }
                if (page === 'saved') {
                    check(`Saved ${width}px ${mode}: synthetic saved item renders`, metrics.savedPostCount > 0, metrics);
                }
                if (page === 'search-initial') {
                    check(`Search ${width}px ${mode}: usable initial state and input`, metrics.hasSearchInput && metrics.hasInitialSearchState, metrics);
                }
                if (page === 'search-results') {
                    check(`Search ${width}px ${mode}: synthetic post results render`, metrics.searchPostCount > 0, metrics);
                    await cdp.evaluate(`document.querySelector('.skuy-discovery-post-list')?.scrollIntoView({block:'start'})`);
                    await cdp.evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
                    const postsImage = await screenshot(`saved-search-search-posts-${width}-${mode}`);
                    results.savedSearchVisual.push({ page: 'search-posts', route, width, height, mode, image: postsImage });
                }
            }
        }
    }
    for (const mode of ['dark', 'light']) {
        await theme(mode);
        await capture('/feed', mode, mode + '-feed-direct');
        await capture(own.profile, mode, mode + '-feed-own-profile', false, true);
        await capture('/feed', mode, mode + '-profile-feed', false, true);
        await capture(fixture.otherProfile, mode, mode + '-feed-other-profile', false, true);
        await capture(own.profile, mode, mode + '-profile-own-direct');
        await capture(fixture.otherProfile, mode, mode + '-profile-other-profile', false, true);
        await capture('/feed', mode, mode + '-feed-repeat');
        await capture(own.settings, mode, mode + '-feed-settings');
        await capture(own.settings, mode, mode + '-settings-hard-reload', true);
        await capture(own.profile, mode, mode + '-profile-hard-reload');
        await capture(own.profile, mode, mode + '-profile-reload', true);
        if (phase === 'after') {
            for (const [index, route] of [...fixture.routes.slice(1), own.account, fixture.post, '/', '/missing-theme-qa-page'].entries()) {
                await capture(route, mode, `${mode}-shared-route-${index}`);
            }
        }
    }
    for (const width of [390, 320]) {
        await viewport(width, width === 390 ? 844 : 740);
        for (const count of [0,1,2,12]) {
            await login(count);
            for (const mode of ['dark','light']) {
                await theme(mode);
                const feed = await badges('/feed', count, width, mode, `badges-${width}-${mode}-${count}-feed`);
                const routes = [fixture.accounts[count].profile];
                if (phase === 'after' && count === 12) routes.push(fixture.accounts[count].settings, '/tersimpan', '/cari', '/pesan', '/poin/riwayat', '/dashboard');
                for (const [index, route] of routes.entries()) {
                    const page = await badges(route, count, width, mode, `badges-${width}-${mode}-${count}-route-${index}`);
                    check(`badge geometry ${width} ${mode} ${count} ${route}`, ['activity','messages'].every(key => JSON.stringify(signature(feed.normal[key])) === JSON.stringify(signature(page.normal[key])) && JSON.stringify(signature(feed.normal[key])) === JSON.stringify(signature(page.active[key]))), { feed:feed.normal, page:page.normal, active:page.active });
                }
            }
        }
    }
    // Guard the approved Feed shell against route-specific CSS regressions.
    // Exercise authenticated pages in real Chromium rather than relying on static CSS checks.
    await login(2);
    for (const width of [1536, 390]) {
        await viewport(width, width === 1536 ? 900 : 844);
        for (const mode of ['dark', 'light']) {
            await theme(mode);
            for (const [surface, route] of [['feed', '/feed'], ['messages', '/pesan'], ['activity', '/aktivitas']]) {
                await navigate(route);
                const geometry = await cdp.evaluate(`(() => {
                    const measure = selector => {
                        const node = document.querySelector(selector);
                        if (!node) return null;
                        const box = node.getBoundingClientRect();
                        const style = getComputedStyle(node);
                        return { x: box.x, y: box.y, width: box.width, height: box.height,
                            position: style.position, display: style.display, overflowY: style.overflowY };
                    };
                    return {
                        page: document.documentElement.dataset.flowPage,
                        viewport: innerWidth,
                        layoutWidth: document.documentElement.clientWidth,
                        topbar: measure('.skuy-topbar'),
                        actions: measure('.skuy-topbar__actions'),
                        rail: measure('.skuy-app-rail'),
                        search: measure('.skuy-flow-topbar-search'),
                        mobileNav: measure('.skuy-mobile-nav'),
                        navKeys: [...document.querySelectorAll('[data-mobile-nav-key]')].map(n => n.dataset.mobileNavKey),
                        iconWidths: ['feed','search','activity','messages','saved'].map(key => measure('[data-nav-key="' + key + '"] .skuy-app-nav__icon')?.width),
                        overflow: document.documentElement.scrollWidth > innerWidth,
                    };
                })()`);
                const shot = await screenshot(`flow-nav-${surface}-${width}-${mode}`);
                results.flowVisual.push({ surface, width, mode, geometry, image: shot });
                check(`Flow navigation parity ${surface} ${width}px ${mode}`,
                    !geometry.overflow
                    && geometry.navKeys.join(',') === 'home,search,create,activity,messages'
                    && geometry.topbar?.position === 'fixed'
                    && Math.abs(geometry.topbar.x) <= 1
                    && Math.abs(geometry.topbar.width - geometry.layoutWidth) <= 2
                    && geometry.actions?.x >= 0
                    && geometry.actions.x + geometry.actions.width <= width + 2
                    && (width >= 1280
                        ? geometry.iconWidths.every(value => value !== null && Math.abs(value - 34) <= 1)
                            && geometry.rail?.position === 'sticky'
                            && geometry.rail.width >= 200 && geometry.rail.width <= 230
                            && geometry.search?.display === 'flex'
                        : geometry.mobileNav?.display === 'grid'
                            && geometry.mobileNav.x >= -1
                            && geometry.mobileNav.x + geometry.mobileNav.width <= width + 1),
                    geometry);
            }
        }
    }
    await login(2);
    for (const width of [1536,1280]) {
        await viewport(width, 900);
        for (const mode of ['dark','light']) {
            await theme(mode);
            await navigate('/feed');
            const inspect = () => cdp.evaluate(`(() => { const selectors=['.skuy-topbar','.skuy-app-rail','.skuy-feed-context','.skuy-post-actions']; return selectors.map(selector=>{const n=document.querySelector(selector);if(!n)return {selector,missing:true};const r=n.getBoundingClientRect(),s=getComputedStyle(n);return {selector,x:r.x,y:r.y,width:r.width,height:r.height,position:s.position,top:s.top,overflowY:s.overflowY,background:s.backgroundColor,color:s.color,gap:s.gap};});})()`);
            const start = await inspect();
            const startImage = await screenshot(`desktop-${width}-${mode}-start`);
            await cdp.evaluate('scrollTo(0, 500)');
            await cdp.evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
            const scroll = await inspect();
            const scrollImage = await screenshot(`desktop-${width}-${mode}-scroll`);
            results.desktop.push({width,mode,start,scroll,startImage,scrollImage});
            check(`desktop feed rails ${width} ${mode}`, scroll.some(item=>item.selector==='.skuy-app-rail' && item.position==='sticky' && item.y>=72 && !['auto','scroll'].includes(item.overflowY)) && scroll.some(item=>item.selector==='.skuy-feed-context' && item.position==='static' && !['auto','scroll'].includes(item.overflowY)), scroll);
        }
    }
    if (phase === 'after') {
        await viewport(390,844);
        await theme('dark');
        await navigate('/feed');
        const postUid=fixture.post.split('/').at(-1);
        const cardSelector=`[data-feed-post-uid="${postUid}"]`;
        const bookmarkSelector=cardSelector+' .skuy-bookmark-menu-action';
        const likeSelector=cardSelector+' form[action$="/like"]';
        const initialBookmark = await cdp.evaluate(`document.querySelector(${JSON.stringify(bookmarkSelector+' button')})?.getAttribute('aria-pressed') ?? null`);
        check('Browser social-action fixture starts unbookmarked',initialBookmark==='false',initialBookmark);
        const browserAttribute=await cdp.evaluate(`document.querySelector(${JSON.stringify(bookmarkSelector)}).getAttribute('x-on:submit.prevent')`);
        check('browser preserves Alpine bookmark attribute', browserAttribute==='toggleBookmark($event)',browserAttribute);
        await cdp.evaluate(`document.querySelector(${JSON.stringify(cardSelector)}).querySelector('[aria-haspopup="menu"]').click(); document.querySelector(${JSON.stringify(bookmarkSelector)}).requestSubmit()`);
        await until(()=>cdp.evaluate(`(() => { const button=document.querySelector(${JSON.stringify(bookmarkSelector+' button')}); return button?.getAttribute('aria-pressed')==='true' && button?.getAttribute('aria-busy')==='false'; })()`),'mobile bookmark saved and request settled');
        await cdp.evaluate(`document.querySelector(${JSON.stringify(likeSelector)}).requestSubmit()`);
        await until(()=>cdp.evaluate(`(() => { const button=document.querySelector(${JSON.stringify(likeSelector+' button')}); return button?.getAttribute('aria-pressed')==='true' && button?.getAttribute('aria-busy')==='false'; })()`),'like saved and request settled');
        const persisted = JSON.parse(await command(php, ['tests/Browser/fixtures/theme-navigation.php','social-state','2',postUid]));
        check('Like and Bookmark confirmed in isolated database',persisted.liked===true && persisted.bookmarked===true,persisted);
        await navigate(fixture.otherProfile);
        const profileActions = await cdp.evaluate(`(() => ({ bookmark:document.querySelector(${JSON.stringify(bookmarkSelector+' button')})?.getAttribute('aria-pressed')??null, like:document.querySelector(${JSON.stringify(likeSelector+' button')})?.getAttribute('aria-pressed')??null }))()`);
        check('Like and Mobile Bookmark persist on Profile',profileActions.bookmark==='true' && profileActions.like==='true',profileActions);
        await screenshot('profile-live-like-bookmark');

        await navigate(fixture.savedPage);
        const savedPostUid=fixture.savedPost.split('/').at(-1);
        const savedCardSelector=`[data-feed-post-uid="${savedPostUid}"]`;
        const savedBookmarkSelector=savedCardSelector+' .skuy-bookmark-menu-action';
        check('Saved displays the persisted bookmark state',await cdp.evaluate(`document.querySelector(${JSON.stringify(savedBookmarkSelector+' button')})?.getAttribute('aria-pressed')==='true'`),'seeded bookmark for synthetic account 2');
        await cdp.evaluate(`document.querySelector(${JSON.stringify(savedCardSelector)}).querySelector('[aria-haspopup="menu"]').click(); document.querySelector(${JSON.stringify(savedBookmarkSelector)}).requestSubmit()`);
        await until(()=>cdp.evaluate(`document.querySelector(${JSON.stringify(savedCardSelector)})===null`),'saved item removed from collection');
        await screenshot('saved-live-after-unsave');
        await navigate(fixture.savedPage);
        const savedActions = await cdp.evaluate(`(() => ({ removed:document.querySelector(${JSON.stringify(savedCardSelector)})===null, otherBookmark:document.querySelector(${JSON.stringify(cardSelector+' .skuy-bookmark-menu-action button')})?.getAttribute('aria-pressed')??null }))()`);
        check('Saved removal persists and preserves other saved posts',savedActions.removed && savedActions.otherBookmark==='true',savedActions);

        await navigate(fixture.searchResults);
        const searchCardSelector=`[data-feed-post-uid="${postUid}"]`;
        const searchBookmarkSelector=searchCardSelector+' .skuy-bookmark-menu-action';
        const sharedActions=await cdp.evaluate(`(() => {
            const card=document.querySelector(${JSON.stringify(searchCardSelector)});
            if(!card)return null;
            return {
                actions:['[data-feed-post-action="like"]','[data-post-appreciation]','[data-feed-comment-action]','[data-feed-post-action="repost"]','[data-feed-post-action="bookmark"]','[data-mobile-primary-action="share"]'].map(selector=>card.querySelectorAll(selector).length),
                liked:card.querySelector('[data-feed-post-action="like"]')?.getAttribute('aria-pressed'),
                bookmarked:card.querySelector('[data-feed-post-action="bookmark"]')?.getAttribute('aria-pressed'),
                hasRealAuthor:card.querySelector('.skuy-post__display-name')?.textContent.trim()==='QA Theme Author',
            };
        })()`);
        check('Search reuses the six Feed post actions and persisted state',sharedActions?.actions.every(count=>count===1) && sharedActions.liked==='true' && sharedActions.bookmarked==='true' && sharedActions.hasRealAuthor,sharedActions);
        await cdp.evaluate(`document.querySelector(${JSON.stringify(searchCardSelector)}).scrollIntoView({block:'start'})`);
        await cdp.evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
        await screenshot('search-live-actions-active');
        await cdp.evaluate(`document.querySelector(${JSON.stringify(searchCardSelector)}).querySelector('[aria-haspopup="menu"]').click(); document.querySelector(${JSON.stringify(searchBookmarkSelector)}).requestSubmit()`);
        await until(()=>cdp.evaluate(`document.querySelector(${JSON.stringify(searchBookmarkSelector+' button')})?.getAttribute('aria-pressed')==='false'`),'search bookmark toggled off');
        await navigate('/feed');
        const feedActions = await cdp.evaluate(`(() => ({ bookmark:document.querySelector(${JSON.stringify(bookmarkSelector+' button')})?.getAttribute('aria-pressed')??null, like:document.querySelector(${JSON.stringify(likeSelector+' button')})?.getAttribute('aria-pressed')??null }))()`);
        check('Search bookmark interaction persists to Feed',feedActions.bookmark==='false' && feedActions.like==='true',feedActions);

        await navigate(own.settings);
        await cdp.evaluate(`document.querySelector('.skuy-avatar-studio-launch').focus();document.querySelector('.skuy-avatar-studio-launch').click()`);
        await until(()=>cdp.evaluate(`getComputedStyle(document.querySelector('.skuy-avatar-studio-scrim')).display!=='none'`),'Avatar Studio opens');
        await cdp.evaluate(`[...document.querySelectorAll('.skuy-avatar-studio__modes button')].at(1).click()`);
        await until(()=>cdp.evaluate(`document.querySelectorAll('.skuy-avatar-studio__style').length===5`),'five Avatar Studio styles');
        await until(()=>cdp.evaluate(`document.querySelector('.skuy-avatar-studio__style img')?.classList.contains('is-failed')`),'controlled avatar preview failure');
        check('Avatar Studio retains failure fallback and disabled save',await cdp.evaluate(`document.querySelector('.skuy-avatar-studio__save').disabled && [...document.querySelectorAll('.skuy-avatar-studio__style')].length===5`),'preview responses intentionally return 503; provider is not contacted');
        await screenshot('avatar-studio-failure-fallback');
        await cdp.evaluate(`document.querySelector('[data-avatar-studio-close]').click(); new Promise(resolve=>requestAnimationFrame(resolve))`);
        check('Avatar Studio closes and returns focus',await cdp.evaluate(`getComputedStyle(document.querySelector('.skuy-avatar-studio-scrim')).display==='none' && document.activeElement.classList.contains('skuy-avatar-studio-launch') && document.body.style.overflow!=='hidden'`),'real Alpine dialog');
        await command(php,['tests/Browser/fixtures/theme-navigation.php','unread','2']);
        for (const system of ['dark','light']) {
            for (const value of [null,'invalid']) {
                await theme(value,system);
                await capture(own.profile,system,`preference-${system}-${value}`);
                await capture('/feed','dark',`feed-default-${system}-${value}`);
            }
        }
        await theme('dark');
        csp = true;
        await capture(own.profile,'dark','profile-enforced-csp');
        csp = false;
        const denied = await cdp.send('Page.addScriptToEvaluateOnNewDocument', { source: `Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('Synthetic storage denial', 'SecurityError'); } });` });
        await cdp.send('Emulation.setEmulatedMedia', { features:[{name:'prefers-color-scheme',value:'dark'}] });
        await capture(own.profile,'dark','profile-storage-denied');
        await cdp.evaluate(`[...document.querySelectorAll('button[aria-pressed]')].find(button=>button.getAttribute('@click')==='darkMode = !darkMode').click(); new Promise(resolve=>requestAnimationFrame(resolve))`);
        check('theme toggle works when storage is denied', await cdp.evaluate(`!document.documentElement.classList.contains('dark')`), 'storage setter is guarded');
        await cdp.send('Page.removeScriptToEvaluateOnNewDocument', {identifier:denied.identifier});
        await navigate('/feed');
        await theme('dark');
        await navigate('/feed');
        await cdp.evaluate(`[...document.querySelectorAll('button[aria-pressed]')].find(button=>button.getAttribute('@click')==='darkMode = !darkMode')?.click()`);
        const toggled = await cdp.evaluate(`({dark:document.documentElement.classList.contains('dark'),saved:localStorage.getItem('theme')})`);
        check('theme toggle persists light', toggled.dark === false && toggled.saved === 'light', toggled);
        await capture(own.profile,'light','profile-after-toggle');
    if (phase === 'after') {
        // Visual evidence for the redesigned authenticated screens (not a pixel-level approval).
        // Capture both themes at the narrowest supported mobile width and at desktop width.
        await login(2);
        for (const width of [320, 1280]) {
            await viewport(width, width === 320 ? 740 : 900);
            for (const mode of ['light', 'dark']) {
                await theme(mode);
                for (const { key, url, scope } of fixture.flowRoutes) {
                    await navigate(url);
                    // Collect diagnostic screenshot even when a route renders an HTTP error
                    // instead of throwing a blind timeout before evidence is captured.
                    await until(() => cdp.evaluate(`(document.querySelector('#main-content')?.innerText.trim().length ?? 0) > 0`).catch(() => false),
                        'rendered Flow route ' + key, 4000).catch(() => false);
                    await cdp.evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
                    const metrics = await cdp.evaluate(`(() => {
                        const main = document.querySelector('#main-content');
                        const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                        const nav = [...document.querySelectorAll('[data-mobile-nav-key]')].map(node => node.dataset.mobileNavKey);
                        const root = main?.querySelector('[data-flow-thread], [data-flow-messages], [data-flow-activity], .skuy-discovery-page, .skuy-dashboard-page, .skuy-points-page, .skuy-profile-page');
                        const panel = root ?? main;
                        return {
                            route: location.pathname, theme, scope: document.documentElement.dataset.flowPage,
                            documentTitle: document.title,
                            visibleText: document.body?.innerText.slice(0, 200) ?? '',
                            statusHeading: document.querySelector('h1')?.innerText ?? null,
                            viewportWidth: innerWidth, documentWidth: document.documentElement.scrollWidth,
                            mainTextLength: main?.innerText.trim().length ?? 0,
                            rootBackground: panel ? getComputedStyle(panel).backgroundColor : null,
                            nav,
                            hasErrorPage: !!document.querySelector('[data-flow-guest="error"]'),
                        };
                    })()`);
                    const image = await screenshot(`flow-qa-${key}-${width}-${mode}`);
                    results.flowVisual.push({ key, url, width, mode, scope, metrics, image });
                    if (key === 'conversation' && width <= 390) {
                        // Regression for the fixed five-item dock hiding Chat's send action.
                        // Scroll only within the isolated visual QA fixture (never real users).
                        await cdp.evaluate(`document.querySelector('[data-message-submit]')?.scrollIntoView({block:'center'})`);
                        await cdp.evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
                        const chatSend = await cdp.evaluate(`(() => {
                            const send = document.querySelector('[data-message-submit]');
                            const dock = document.querySelector('.skuy-mobile-nav');
                            if (!send || !dock) return null;
                            const rect = send.getBoundingClientRect();
                            const nav = dock.getBoundingClientRect();
                            return { height: rect.height, top: rect.top, bottom: rect.bottom, dockTop: nav.top };
                        })()`);
                        check(`Flow mobile Chat submit visible above dock ${width}px ${mode}`,
                            chatSend !== null && chatSend.height >= 44 &&
                            chatSend.top >= 0 && chatSend.bottom <= chatSend.dockTop - 8, chatSend);
                        await screenshot(`flow-qa-chat-submit-${width}-${mode}`);
                    }
                    check(`Flow ${key} ${width}px ${mode}: rendered theme, shell, safe viewport, real navigation`,
                        metrics.theme === mode && metrics.scope === scope && !metrics.hasErrorPage &&
                        metrics.mainTextLength > 0 && metrics.documentWidth <= width &&
                        metrics.nav.join(',') === 'home,search,create,activity,messages', metrics);
                }
            }
        }
        await viewport(390, 844);
    }
        const baseline = await fs.readFile(path.join(output,'../before/results.json'),'utf8').then(JSON.parse).catch(()=>null);
        if (baseline) {
            check('Feed badge geometry / colors unchanged from baseline', results.badges.filter(row=>row.route==='/feed').every(row=>{
                const before=baseline.badges.find(b=>b.route==='/feed'&&b.count===row.count&&b.width===row.width&&b.mode===row.mode);
                return JSON.stringify(signature(before?.measured.normal.activity))===JSON.stringify(signature(row.measured.normal.activity))&&JSON.stringify(signature(before?.measured.normal.messages))===JSON.stringify(signature(row.measured.normal.messages));
            }), 'compare all 16 Feed states against before/results.json');
            check('Desktop navigation / sticky geometry unchanged', JSON.stringify(results.desktop.map(({width,mode,start,scroll})=>({width,mode,start,scroll})))===JSON.stringify(baseline.desktop.map(({width,mode,start,scroll})=>({width,mode,start,scroll}))), 'compare 1536/1280 dark/light before/after');
        }
    }
    }
    check('request interception succeeded', !results.interceptionError, results.interceptionError ?? 'ok');
    results.externalRequestsBlocked = [...new Set(results.externalRequestsBlocked)];
    await fs.writeFile(path.join(output,'results.json'), JSON.stringify(results,null,2));
    const failed=results.checks.filter(check=>!check.pass);
    console.log(JSON.stringify({phase,checks:results.checks.length,failed:failed.length,output}));
    if (phase==='after' && failed.length) process.exitCode=1;
} catch(error) {
    if (cdp) {
        results.errorState = await cdp.evaluate(`({url:location.href,state:document.readyState,qa:window.__themeQA,body:document.body?.innerText.slice(0,500),alpine:!!window.Alpine})`).catch(()=>null);
        await fs.writeFile(path.join(output,'error-state.json'),JSON.stringify(results,null,2));
        const shot=await cdp.send('Page.captureScreenshot',{format:'png'}).catch(()=>null);
        if(shot) await fs.writeFile(path.join(output,'error.png'),Buffer.from(shot.data,'base64'));
    }
    await fs.writeFile(path.join(output,'runner-error.txt'),error.stack+'\n'+serverLog);
    console.error(error.stack);
    process.exitCode=1;
} finally {
    if(cdp) await cdp.send('Browser.close').catch(()=>{});
    browser?.kill();
    server?.kill();
    await pause(500);
    assert(path.dirname(temporary)===path.resolve(os.tmpdir()) && path.basename(temporary).startsWith('skuy-theme-browser-'));
    await fs.rm(temporary,{recursive:true,force:true,maxRetries:10,retryDelay:200}).catch(error=>console.error('Temporary browser cleanup: '+error.message));
}
