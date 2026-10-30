// Browser regression checks. Requires Playwright and Chromium in the development environment.
// API responses are mocked: this suite does not send real leads or exercise PHP/MySQL.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, headless: true, args: ['--no-sandbox'] });
  let checks = 0;
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.addInitScript(() => { window.goals = []; window.addEventListener('contractor:goal', e => window.goals.push(e.detail)); });
    for (const width of [320, 375, 390, 768, 1280]) {
      await page.setViewportSize({width, height: 900});
      await page.goto('http://127.0.0.1:18749/landing-podryad.html');
      assert.equal(await page.locator('h1').count(), 1);
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'horizontal overflow at ' + width);
      checks++;
    }
    const targets = await page.locator('a[href^="#"]').evaluateAll(links => links.map(a => !!document.querySelector(a.getAttribute('href'))));
    assert(targets.every(Boolean)); checks++;
    for (const path of ['assets/css/landing-podryad.css', 'assets/js/landing-podryad.js', 'assets/favicon/favicon.ico', 'privacy.html', 'consent.html']) {
      assert.equal((await page.request.get('http://127.0.0.1:18749/' + path)).status(), 200); checks++;
    }
    assert.equal(await page.locator('meta[name=robots]').getAttribute('content'), 'index,follow'); checks++;
    assert.equal(await page.locator('link[rel=canonical]').getAttribute('href'), 'https://zakondemchenko.ru/landing-podryad.html'); checks++;
    await page.locator('[data-service=claim]').click();
    assert.match(await page.locator('[name=question]').inputValue(), /неоплате/); checks++;
    let requests = 0;
    let mode = 'success';
    let release;
    await page.route('**/api/process_form.php', async route => {
      requests++;
      const payload = route.request().postDataJSON();
      assert.equal(payload.name, 'Тест Подрядчик');
      assert.equal(payload.contact, '@fixture_test');
      assert.equal(payload.consent, 'yes');
      assert.equal(payload.source, 'landing-podryad');
      assert.ok(payload.service === 'claim' || payload.service === undefined); // cleared after a saved lead
      if (mode === 'delay') await new Promise(resolve => { release = resolve; });
      if (mode === 'network') return route.abort();
      await route.fulfill({ status: mode === 'error' ? 500 : 200, contentType:'application/json', body:JSON.stringify(mode === 'error' ? {success:false} : {success:true, questionId:123, autoReply:'telegram_not_started', telegramDeepLink:'https://t.me/PravKompas_bot?start=q_123'}) });
    });
    async function fill() {
      await page.locator('[name=name]').fill('Тест Подрядчик');
      await page.locator('[name=contact]').fill('@fixture_test');
      await page.locator('[name=question]').fill('Нужна претензия: работы выполнены, заказчик не оплатил.');
    }
    await fill();
    await page.locator('button[type=submit]').click();
    assert.equal(requests, 0, 'consent must be required'); checks++;
    await page.locator('#lead-consent').check();
    await page.locator('[name=contact]').fill('123');
    await page.locator('button[type=submit]').click();
    assert.equal(requests, 0); checks++;
    await fill();
    mode = 'delay';
    await page.locator('button[type=submit]').click();
    await page.waitForFunction(() => document.querySelector('form').getAttribute('aria-busy') === 'true');
    await page.locator('form').evaluate(f => f.dispatchEvent(new Event('submit', {cancelable:true, bubbles:true})));
    await page.waitForTimeout(100);
    assert.equal(requests, 1); checks++;
    release();
    await page.waitForFunction(() => document.querySelector('#lead-status').textContent.includes('Заявка №123 сохранена'));
    assert.match(await page.locator('#lead-status').innerText(), /Повторно отправлять заявку не нужно/); checks++;
    assert.equal((await page.evaluate(() => window.goals.filter(g => g.event === 'contractor_lead_saved'))).length, 1); checks++;
    for (mode of ['error', 'network']) {
      await fill();
      await page.locator('#lead-consent').check();
      await page.locator('button[type=submit]').click();
      await page.waitForFunction(() => document.querySelector('#lead-status').dataset.error === 'true');
      assert.equal(await page.locator('[name=name]').inputValue(), 'Тест Подрядчик');
      assert.equal((await page.evaluate(() => window.goals.filter(g => g.event === 'contractor_lead_saved'))).length, 1);
      assert.equal(await page.locator('button[type=submit]').isEnabled(), true); checks++;
    }
    const goals = await page.evaluate(() => window.goals);
    assert(!JSON.stringify(goals).includes('fixture_test'));
    assert(goals.every(g => Object.keys(g).every(k => ['event','page','service'].includes(k)))); checks++;
    assert.deepEqual(errors, []); checks++;
    await page.setViewportSize({width:390,height:844});
    await page.screenshot({path:'/tmp/zakon-landing-mobile.png',fullPage:true});
    const nojs = await browser.newPage({javaScriptEnabled:false,viewport:{width:320,height:700}});
    await nojs.goto('http://127.0.0.1:18749/landing-podryad.html');
    assert(await nojs.locator('h1').isVisible());
    assert(await nojs.locator('noscript').isVisible()); checks++;
    console.log(`PASS: ${checks} checks; form API mocked; widths 320, 375, 390, 768, 1280.`);
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
