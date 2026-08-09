(function () {
    'use strict';

    const COUNTER_ID = 110939208;
    const STORAGE_KEY = 'zakondemchenko_analytics_consent';

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
            clickmap: true,
            trackLinks: true,
            accurateTrackBounce: true,
            webvisor: false
        });
    }

    function reachGoal(goal) {
        if (localStorage.getItem(STORAGE_KEY) === 'accepted' && window.ym) {
            window.ym(COUNTER_ID, 'reachGoal', goal);
        }
    }

    window.pravkompasReachGoal = reachGoal;

    function showConsent() {
        const style = document.createElement('style');
        style.textContent = '.analytics-consent{position:fixed;z-index:9999;right:16px;bottom:16px;width:calc(100% - 32px);max-width:540px;padding:18px 20px;border-radius:16px;background:#152b29;color:#fff;box-shadow:0 16px 45px rgba(21,43,41,.28);font:14px/1.55 system-ui,sans-serif}.analytics-consent p{margin:0;color:#fff}.analytics-consent a{color:#e7b998;text-decoration:underline}.analytics-consent>div{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.analytics-consent button{border:1px solid rgba(255,255,255,.35);border-radius:10px;padding:9px 14px;background:transparent;color:#fff;font-weight:700;cursor:pointer}.analytics-consent button:first-child{background:#9f512e;border-color:#9f512e;color:#fff}@media(max-width:560px){.analytics-consent{left:16px;right:16px;max-width:none}.analytics-consent button{width:100%}}';
        document.head.appendChild(style);
        const banner = document.createElement('section');
        banner.className = 'analytics-consent';
        banner.setAttribute('role', 'dialog');
        banner.setAttribute('aria-label', 'Настройки аналитики');
        banner.innerHTML = `
            <p>Мы используем Яндекс Метрику, чтобы понимать, какие материалы полезны посетителям. Запись действий и рекламное профилирование отключены. Подробнее — в <a href="/privacy.html">политике конфиденциальности</a>.</p>
            <div>
                <button type="button" data-analytics="accept">Разрешить аналитику</button>
                <button type="button" data-analytics="reject">Только необходимые</button>
            </div>`;
        document.body.appendChild(banner);

        banner.querySelector('[data-analytics="accept"]').addEventListener('click', function () {
            localStorage.setItem(STORAGE_KEY, 'accepted');
            banner.remove();
            loadMetrika();
        });
        banner.querySelector('[data-analytics="reject"]').addEventListener('click', function () {
            localStorage.setItem(STORAGE_KEY, 'rejected');
            banner.remove();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const consent = localStorage.getItem(STORAGE_KEY);
        if (consent === 'accepted') loadMetrika();
        else if (consent !== 'rejected') showConsent();

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
        });
    });
})();
