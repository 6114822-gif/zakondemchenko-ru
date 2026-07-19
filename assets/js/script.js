(() => {
    'use strict';

    document.getElementById('year').textContent = new Date().getFullYear();

    // ---------- Открытие Telegram напрямую в приложении ----------
    // В России домен t.me часто заблокирован на уровне DNS у провайдера,
    // из-за чего обычная ссылка https://t.me/... не открывается в браузере,
    // хотя само приложение Telegram работает (у него свой канал связи).
    // tg:// — это не веб-адрес, а системная схема: её перехватывает сама ОС
    // и передаёт напрямую установленному приложению, без DNS-запроса.
    // Если приложения нет (или это десktop без ассоциации схемы) — через
    // короткий таймаут откатываемся на обычную ссылку как запасной вариант.
    window.openTelegramLink = function (event, domain) {
        event.preventDefault();
        const appUrl = 'tg://resolve?domain=' + domain;
        const webUrl = 'https://t.me/' + domain;

        let leftPage = false;
        const onBlur = () => { leftPage = true; };
        window.addEventListener('blur', onBlur, { once: true });

        window.location.href = appUrl;

        setTimeout(() => {
            window.removeEventListener('blur', onBlur);
            if (!leftPage) {
                window.open(webUrl, '_blank', 'noopener');
            }
        }, 1200);
    };

    // ---------- Шапка: тень при скролле + мобильное меню ----------
    const header = document.getElementById('site-header');
    window.addEventListener('scroll', () => {
        header.classList.toggle('scrolled', window.scrollY > 8);
    }, { passive: true });

    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    const iconBurger = document.getElementById('icon-burger');
    const iconClose = document.getElementById('icon-close');

    menuToggle.addEventListener('click', () => {
        const isOpen = !mobileMenu.classList.contains('hidden');
        mobileMenu.classList.toggle('hidden');
        iconBurger.classList.toggle('hidden', !isOpen);
        iconClose.classList.toggle('hidden', isOpen);
        menuToggle.setAttribute('aria-expanded', String(!isOpen));
    });

    mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.add('hidden');
            iconBurger.classList.remove('hidden');
            iconClose.classList.add('hidden');
        });
    });

    // ---------- Reveal-on-scroll ----------
    document.querySelectorAll('section > div, .news-card, .vk-post').forEach(el => el.classList.add('reveal'));
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    // Наблюдение навешивается динамически на подгружаемые карточки ниже (см. observeNew)
    function observeNew(el) {
        el.classList.add('reveal');
        observer.observe(el);
        // Форсируем показ на случай, если элемент уже во вьюпорте (напр. верх страницы)
        requestAnimationFrame(() => {
            const rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight) {
                el.classList.add('visible');
            }
        });
    }
    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

    // ---------- Новости (data/news.json) ----------
    const newsColumns = {
        business: document.getElementById('news-business'),
        citizens: document.getElementById('news-citizens'),
    };

    function formatDate(iso) {
        const d = new Date(iso);
        if (isNaN(d)) return '';
        return d.toLocaleDateString('ru-RU', { day: '2-digit', month: 'long', year: 'numeric' });
    }

    function renderNews(category, items) {
        const container = newsColumns[category];
        if (!container) return;
        container.innerHTML = '';

        if (!items || items.length === 0) {
            container.innerHTML = '<p class="text-navy/50">Скоро здесь появятся новости.</p>';
            return;
        }

        items.slice(0, 3).forEach(item => {
            const card = document.createElement('a');
            card.className = 'news-card';
            card.href = item.link || '#';
            card.target = '_blank';
            card.rel = 'noopener';
            card.innerHTML = `
                <time>${formatDate(item.date)}</time>
                <h4>${escapeHtml(item.title)}</h4>
                <p>${escapeHtml(item.excerpt)}</p>
            `;
            container.appendChild(card);
            observeNew(card);
        });
    }

    fetch('data/news.json', { cache: 'no-store' })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            renderNews('business', data.business);
            renderNews('citizens', data.citizens);
        })
        .catch(() => {
            newsColumns.business.innerHTML = '<p class="text-navy/50">Не удалось загрузить новости.</p>';
            newsColumns.citizens.innerHTML = '<p class="text-navy/50">Не удалось загрузить новости.</p>';
        });

    // ---------- Табы новостей (мобильная версия) ----------
    const tabButtons = document.querySelectorAll('.news-tab-btn');
    const tabPanels = document.querySelectorAll('[data-tab-panel]');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            tabButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const target = btn.dataset.tab;
            tabPanels.forEach(panel => {
                panel.classList.toggle('hidden', panel.dataset.tabPanel !== target);
                panel.classList.toggle('md:block', true);
            });
        });
    });

    // ---------- Лента VK (api/vk_feed.php) ----------
    const vkContainer = document.getElementById('vk-posts');

    fetch('api/vk_feed.php', { cache: 'no-store' })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            vkContainer.innerHTML = '';
            const posts = data.posts || [];

            if (posts.length === 0) {
                vkContainer.innerHTML = '<p class="text-navy/50 text-center">Лента временно недоступна — загляните в группу напрямую.</p>';
                return;
            }

            posts.forEach(post => {
                const el = document.createElement('div');
                el.className = 'vk-post';
                el.innerHTML = `
                    <time>${escapeHtml(post.date)}</time>
                    <p>${escapeHtml(post.text)}</p>
                    ${post.truncated ? `<a class="read-more" href="${post.url}" target="_blank" rel="noopener">Читать далее →</a>` : ''}
                `;
                vkContainer.appendChild(el);
                observeNew(el);
            });
        })
        .catch(() => {
            vkContainer.innerHTML = '<p class="text-navy/50 text-center">Лента временно недоступна — загляните в группу напрямую.</p>';
        });

    // ---------- Форма "Задать вопрос" ----------
    const form = document.getElementById('ask-form');
    const submitBtn = document.getElementById('ask-submit');
    const submitText = document.getElementById('ask-submit-text');
    const spinner = document.getElementById('ask-spinner');
    const successBox = document.getElementById('ask-success');
    const generalErrorBox = document.getElementById('ask-general-error');

    function setFieldError(fieldName, message) {
        const el = form.querySelector(`[data-error-for="${fieldName}"]`);
        const input = form.querySelector(`[name="${fieldName}"]`);
        if (el) el.textContent = message || '';
        if (input) input.classList.toggle('invalid', Boolean(message));
    }

    function clearErrors() {
        ['name', 'contact', 'question'].forEach(f => setFieldError(f, ''));
        generalErrorBox.classList.add('hidden');
        successBox.classList.add('hidden');
    }

    function validateClientSide(data) {
        const errors = {};
        if (!data.name || data.name.trim().length < 2) {
            errors.name = 'Укажите имя (минимум 2 символа)';
        }
        const contact = (data.contact || '').trim();
        const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contact);
        const isTelegram = /^@?[a-zA-Z0-9_]{5,32}$/.test(contact) || /t\.me\/[a-zA-Z0-9_]{5,32}/i.test(contact);
        const isVk = /vk\.com\/[a-zA-Z0-9_.]{2,64}/i.test(contact);
        if (!contact || (!isEmail && !isTelegram && !isVk)) {
            errors.contact = 'Укажите email (например, ivan@mail.ru) или Telegram-ник (например, @ivanov)';
        }
        if (!data.question || data.question.trim().length < 10) {
            errors.question = 'Опишите вопрос подробнее (минимум 10 символов)';
        }
        return errors;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();

        const consent = document.getElementById('f-consent');
        if (!consent.checked) {
            generalErrorBox.textContent = 'Необходимо согласие на обработку персональных данных';
            generalErrorBox.classList.remove('hidden');
            return;
        }

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        const clientErrors = validateClientSide(payload);
        if (Object.keys(clientErrors).length > 0) {
            Object.entries(clientErrors).forEach(([field, msg]) => setFieldError(field, msg));
            return;
        }

        submitBtn.disabled = true;
        submitText.textContent = 'Отправляем...';
        spinner.classList.remove('hidden');

        try {
            const res = await fetch('api/process_form.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const result = await res.json();

            if (!result.success) {
                if (result.errors) {
                    Object.entries(result.errors).forEach(([field, msg]) => {
                        if (field === 'general') {
                            generalErrorBox.textContent = msg;
                            generalErrorBox.classList.remove('hidden');
                        } else {
                            setFieldError(field, msg);
                        }
                    });
                }
                return;
            }

            form.reset();
            successBox.textContent = 'Спасибо! Ваш вопрос принят в работу.';
            successBox.classList.remove('hidden');

            if (result.autoReply === 'telegram_not_started') {
                showOptInModal('telegram');
            } else if (result.autoReply === 'vk_not_allowed') {
                showOptInModal('vk');
            }
        } catch (err) {
            generalErrorBox.textContent = 'Не удалось отправить вопрос. Попробуйте написать нам напрямую в Telegram или ВК.';
            generalErrorBox.classList.remove('hidden');
        } finally {
            submitBtn.disabled = false;
            submitText.textContent = 'Получить бесплатный ответ';
            spinner.classList.add('hidden');
        }
    });

    // ---------- Модалка про /start у бота / разрешение сообщений VK ----------
    const modal = document.getElementById('opt-in-modal');
    const modalText = document.getElementById('opt-in-text');
    const modalActions = document.getElementById('opt-in-actions');
    const modalClose = document.getElementById('opt-in-close');

    function showOptInModal(type) {
        if (type === 'telegram') {
            modalText.textContent = 'Чтобы мы могли ответить вам в Telegram, пожалуйста, сначала нажмите кнопку «Запустить» в нашем боте, а затем задайте вопрос ещё раз.';
            modalActions.innerHTML = `
                <a href="https://t.me/PravKompas_bot" onclick="openTelegramLink(event, 'PravKompas_bot')" target="_blank" rel="noopener"
                   class="bg-[#229ED9] hover:bg-[#1c87ba] text-white font-bold py-3 rounded-xl transition-colors">
                   Открыть @PravKompas_bot
                </a>
            `;
        } else {
            modalText.textContent = 'Чтобы мы могли ответить вам ВКонтакте, разрешите сообщения от нашего сообщества (напишите ему первым), а затем задайте вопрос ещё раз.';
            modalActions.innerHTML = `
                <a href="https://vk.com/prav_kompas" target="_blank" rel="noopener"
                   class="bg-[#0077FF] hover:bg-[#0066d9] text-white font-bold py-3 rounded-xl transition-colors">
                   Открыть сообщество ВК
                </a>
            `;
        }
        modal.classList.remove('hidden');
    }

    function hideOptInModal() {
        modal.classList.add('hidden');
    }

    modalClose.addEventListener('click', hideOptInModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) hideOptInModal();
    });

    // ---------- utils ----------
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }
})();
