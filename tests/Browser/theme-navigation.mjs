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
const results = { phase, engine: 'installed Chromium via CDP', checks: [], paints: [], badges: [], desktop: [], savedSearchVisual: [], externalRequestsBlocked: [] };
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
            this.pending.set(id, { resolve, reject });
            this.socket.send(JSON.stringify({ id, method, params }));
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
    browser = spawn(browserExecutable, ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--disable-component-update', '--disable-sync', '--remote-debugging-port=0', '--remote-debugging-address=127.0.0.1', `--user-data-dir=${path.join(temporary, 'browser')}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    const debuggingPort = await until(() => fs.readFile(path.join(temporary, 'browser/DevToolsActivePort'), 'utf8').then(text => text.split('\n')[0]).catch(() => false), 'browser CDP');
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
    await viewport(390, 844);
    await login(2);
    const own = fixture.accounts[2];
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
            check(`desktop sticky ${width} ${mode}`, scroll.filter(item=>['.skuy-app-rail','.skuy-feed-context'].includes(item.selector)).every(item=>item.position === 'sticky' && item.y >= 72 && !['auto','scroll'].includes(item.overflowY)), scroll);
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
        const browserAttribute=await cdp.evaluate(`document.querySelector(${JSON.stringify(bookmarkSelector)}).getAttribute('@submit.prevent')`);
        check('browser preserves Alpine bookmark attribute', browserAttribute==='toggleBookmark($event)',browserAttribute);
        await cdp.evaluate(`document.querySelector(${JSON.stringify(cardSelector)}).querySelector('[aria-haspopup="menu"]').click(); document.querySelector(${JSON.stringify(bookmarkSelector)}).requestSubmit()`);
        await until(()=>cdp.evaluate(`document.querySelector(${JSON.stringify(bookmarkSelector+' button')}).getAttribute('aria-pressed')==='true'`),'mobile bookmark saved');
        await cdp.evaluate(`document.querySelector(${JSON.stringify(likeSelector)}).requestSubmit()`);
        await until(()=>cdp.evaluate(`document.querySelector(${JSON.stringify(likeSelector+' button')}).getAttribute('aria-pressed')==='true'`),'like saved');
        await navigate(fixture.otherProfile);
        check('Like and Mobile Bookmark persist on Profile',await cdp.evaluate(`document.querySelector(${JSON.stringify(bookmarkSelector+' button')}).getAttribute('aria-pressed')==='true' && document.querySelector(${JSON.stringify(likeSelector+' button')}).getAttribute('aria-pressed')==='true'`),'real local controllers / synthetic post');
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
        check('Saved removal persists and preserves other saved posts',await cdp.evaluate(`document.querySelector(${JSON.stringify(savedCardSelector)})===null && document.querySelector(${JSON.stringify(cardSelector+' .skuy-bookmark-menu-action button')})?.getAttribute('aria-pressed')==='true'`),'real bookmark destroy controller / synthetic posts');

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
        check('Search bookmark interaction persists to Feed',await cdp.evaluate(`document.querySelector(${JSON.stringify(bookmarkSelector+' button')})?.getAttribute('aria-pressed')==='false' && document.querySelector(${JSON.stringify(likeSelector+' button')})?.getAttribute('aria-pressed')==='true'`),'real bookmark and like controllers / synthetic post');

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
        const baseline = await fs.readFile(path.join(output,'../before/results.json'),'utf8').then(JSON.parse).catch(()=>null);
        if (baseline) {
            check('Feed badge geometry / colors unchanged from baseline', results.badges.filter(row=>row.route==='/feed').every(row=>{
                const before=baseline.badges.find(b=>b.route==='/feed'&&b.count===row.count&&b.width===row.width&&b.mode===row.mode);
                return JSON.stringify(signature(before?.measured.normal.activity))===JSON.stringify(signature(row.measured.normal.activity))&&JSON.stringify(signature(before?.measured.normal.messages))===JSON.stringify(signature(row.measured.normal.messages));
            }), 'compare all 16 Feed states against before/results.json');
            check('Desktop navigation / sticky geometry unchanged', JSON.stringify(results.desktop.map(({width,mode,start,scroll})=>({width,mode,start,scroll})))===JSON.stringify(baseline.desktop.map(({width,mode,start,scroll})=>({width,mode,start,scroll}))), 'compare 1536/1280 dark/light before/after');
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
