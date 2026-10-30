(() => {
    'use strict';
    const form = document.getElementById('legalPaymentForm');
    if (!form) return;
    const button = document.getElementById('paymentButton');
    const errorBox = document.getElementById('paymentError');
    const companyFields = document.getElementById('companyFields');
    const companyName = document.getElementById('companyName');
    const companyInn = document.getElementById('companyInn');
    const salesEnabled = document.body.dataset.salesEnabled === '1';
    const priceLabel = document.body.dataset.servicePrice || '1 990 ₽';

    const updatePayer = () => {
        const isCompany = form.elements.payer.value === 'company';
        companyFields.hidden = !isCompany;
        companyName.required = isCompany;
        companyInn.required = isCompany;
        button.disabled = !salesEnabled && !isCompany;
        button.querySelector('span').textContent = isCompany ? 'Отправить запрос счёта' : (salesEnabled ? `Перейти к оплате ${priceLabel}` : 'Оплата подключается');
    };
    form.querySelectorAll('input[name="payer"]').forEach(input => input.addEventListener('change', updatePayer));
    updatePayer();

    const clearErrors = () => {
        errorBox.textContent = '';
        form.querySelectorAll('.field-error').forEach(node => { node.textContent = ''; });
    };
    const showFieldErrors = errors => Object.entries(errors || {}).forEach(([field, message]) => {
        const node = form.querySelector(`[data-error-for="${field}"]`);
        if (node) node.textContent = message;
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        clearErrors();
        if (!form.reportValidity()) return;
        const original = button.querySelector('span').textContent;
        button.disabled = true;
        button.querySelector('span').textContent = form.elements.payer.value === 'company' ? 'Отправляем запрос…' : 'Открываем Robokassa…';
        try {
            const response = await fetch('/api/legal_create_order.php', {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const result = await response.json();
            if (!response.ok || !result.success) {
                if (result.errors) showFieldErrors(result.errors);
                throw new Error(result.error || 'validation_error');
            }
            if (result.mode === 'invoice') {
                form.innerHTML = `<div class="pay-success"><strong>Запрос счёта №${result.request_number} принят</strong><p>Проверьте email. Сергей уточнит реквизиты и направит счёт. После оплаты письменный ответ будет подготовлен в срок, указанный в оферте.</p><a href="/">Вернуться на ПравКомпас</a></div>`;
                return;
            }
            sessionStorage.setItem('legal_order', result.order);
            if (window.pravkompasReachGoal) window.pravkompasReachGoal('legal_checkout_start');
            const paymentForm = document.createElement('form');
            paymentForm.method = 'POST';
            paymentForm.action = result.payment_url;
            Object.entries(result.fields).forEach(([name, value]) => {
                const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; paymentForm.appendChild(input);
            });
            document.body.appendChild(paymentForm);
            paymentForm.submit();
        } catch (error) {
            const messages = {payment_not_configured:'Онлайн-оплата ещё подключается. Напишите Сергею на info@zakondemchenko.ru.',sales_pending_activation:'Robokassa проверяет юридический магазин. Оплата станет доступна после проверки.',invalid_session:'Страница была открыта слишком давно. Обновите её и повторите попытку.',rate_limited:'Слишком много попыток. Подождите 15 минут или напишите Сергею.',temporary_error:'Сервис временно недоступен. Попробуйте позже или напишите Сергею.',validation_error:'Проверьте обязательные поля формы.'};
            errorBox.textContent = messages[error.message] || 'Не удалось продолжить. Повторите попытку или напишите Сергею.';
            button.disabled = !salesEnabled && form.elements.payer.value !== 'company';
            button.querySelector('span').textContent = original;
            if (window.pravkompasReachGoal) window.pravkompasReachGoal('legal_checkout_error');
        }
    });
})();
