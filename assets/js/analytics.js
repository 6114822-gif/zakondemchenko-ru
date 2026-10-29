(function () {
    'use strict';

    const COUNTER_ID = 110939208;
    const STORAGE_KEY = 'zakondemchenko_analytics_consent';

    function getConsent() {
        try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
    }

    function setConsent(value) {
        try { localStorage.setItem(STORAGE_KEY, value); } catch (e) {}
    }

    const FIELD_SELECTOR = 'input, textarea, select, [contenteditable]';

    function protectFields(root) {
        if (!root || root.nodeType !== 1) return;
        if (root.matches && root.matches(FIELD_SELECTOR)) root.classList.add('ym-disable-keys', 'ym-hide-content');
        root.querySelectorAll(FIELD_SELECTOR).forEach(function (el) {
            el.classList.add('ym-disable-keys', 'ym-hide-content');
        });
    }

    function loadMetrika() {
        if (window.ym && window.__pravkompasMetrikaLoaded) return;
        window.__pravkompasMetrikaLoaded = true;
        window.ym = window.ym || function () {
            (window.ym.a = window.ym.a || []).push(arguments);
        };
        window.ym.l = Date.now();

        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://mc.yandex.ru/metrika/tag.js?id=' + COUNTER_ID;
        document.head.appendChild(script);

        window.ym(COUNTER_ID, 'init', {
            ssr: true,
            clickmap: true,
            trackLinks: true,
            accurateTrackBounce: true,
            webvisor: false,
            referrer: document.referrer ? document.referrer.split(/[?#]/)[0] : '',
            url: location.origin + location.pathname
        });
    }

    function reachGoal(goal) {
        if (getConsent() === 'accepted' && window.ym) {
            window.ym(COUNTER_ID, 'reachGoal', goal);
        }
    }

    window.pravkompasReachGoal = reachGoal;

    function showNotice() {
        const style = document.createElement('style');
        style.textContent = '.analytics-consent{position:fixed;z-index:9999;left:50%;bottom:12px;width:calc(100% - 24px);max-width:960px;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:14px;transform:translateX(-50%);padding:11px 14px;border-radius:13px;background:#152b29;color:#fff;box-shadow:0 12px 34px rgba(21,43,41,.25);font:12px/1.42 system-ui,sans-serif}.analytics-consent p{margin:0;color:#fff}.analytics-consent a{color:#e7b998;text-decoration:underline}.analytics-consent>div{display:flex;gap:8px;flex-wrap:nowrap;margin:0}.analytics-consent button{white-space:nowrap;border:1px solid rgba(255,255,255,.35);border-radius:9px;padding:8px 11px;background:transparent;color:#fff;font-size:11px;font-weight:750;cursor:pointer}.analytics-consent button:first-child{background:#9f512e;border-color:#9f512e;color:#fff}@media(max-width:680px){.analytics-consent{bottom:8px;grid-template-columns:1fr;gap:8px;padding:10px 11px;font-size:11px}.analytics-consent>div{display:grid;grid-template-columns:1fr 1fr}.analytics-consent button{white-space:normal;padding:7px 8px}}';
        document.head.appendChild(style);
        const banner = document.createElement('section');
        banner.className = 'analytics-consent';
        banner.setAttribute('role', 'dialog');
        banner.setAttribute('aria-label', 'Уведомление об аналитике');
        banner.innerHTML = `
            <p>Сайт использует cookie и Яндекс Метрику, чтобы улучшать сайт. Аналитика включается только после вашего согласия. <a href="/privacy.html">Подробнее</a>.</p>
            <div>
                <button type="button" data-analytics="accept">Разрешить аналитику</button>
                <button type="button" data-analytics="reject">Отказаться</button>
            </div>`;
        document.body.appendChild(banner);

        banner.querySelector('[data-analytics="accept"]').addEventListener('click', function () {
            setConsent('accepted');
            loadMetrika();
            banner.remove();
        });
        banner.querySelector('[data-analytics="reject"]').addEventListener('click', function () {
            setConsent('rejected');
            banner.remove();
            location.reload();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const consent = getConsent();
        protectFields(document.documentElement);
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) { m.addedNodes.forEach(protectFields); });
        }).observe(document.documentElement, { childList: true, subtree: true });
        if (consent === 'accepted') loadMetrika();
        if (consent !== 'accepted' && consent !== 'rejected') showNotice();

        document.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');
            if (!link) return;
            const href = link.getAttribute('href') || '';
            const explicitGoal = link.dataset.goal;
            if (explicitGoal) reachGoal(explicitGoal);
            if (href.includes('t.me/') || href.includes('tg://')) reachGoal('messenger_telegram');
            else if (href.includes('vk.com/') || href.includes('vk.ru/')) reachGoal('messenger_vk');
            else if (href.includes('max.ru/')) reachGoal('messenger_max');
            else if (href.startsWith('mailto:')) reachGoal('contact_email');
            else if (href.startsWith('tel:')) reachGoal('contact_phone');
        });
    });
})();
