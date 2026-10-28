(() => {
  'use strict';
  // Only fixed event names and service codes; never contact data, question text or URL parameters.
  function goal(name, service) {
    const detail = { event: 'contractor_' + name, page: 'landing-podryad' };
    if (['contract', 'claim', 'arbitration'].includes(service)) detail.service = service;
    window.dispatchEvent(new CustomEvent('contractor:goal', { detail }));
  }
  document.querySelectorAll('[data-goal]').forEach(link => {
    link.addEventListener('click', () => goal(link.dataset.goal, link.dataset.service));
  });
  const form = document.getElementById('contractor-form');
  const status = document.getElementById('lead-status');
  const button = form.querySelector('button[type=submit]');
  let sending = false;
  let started = false;
  form.addEventListener('input', () => {
    if (!started) { started = true; goal('form_start'); }
  });
  document.querySelectorAll('[data-service]').forEach(link => {
    link.addEventListener('click', () => {
      const question = form.elements.question;
      if (!question.value.trim()) {
        question.value = {contract: 'Нужна проверка договора подряда. ', claim: 'Нужна претензия по неоплате работ. ', arbitration: 'Нужны документы для арбитражного спора. '}[link.dataset.service];
      }
    });
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (sending || !form.reportValidity()) return;
    status.textContent = '';
    status.dataset.error = 'false';
    const payload = Object.fromEntries(new FormData(form));
    payload.name = payload.name.trim();
    payload.contact = payload.contact.trim();
    payload.question = payload.question.trim();
    if (payload.website) return;
    const validContact = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.contact) || /^@?[a-zA-Z0-9_]{5,32}$/.test(payload.contact) || /^https?:\/\/t\.me\/[a-zA-Z0-9_]{5,32}\/?$/i.test(payload.contact);
    if (payload.name.length < 2 || payload.question.length < 10 || !validContact) {
      status.dataset.error = 'true';
      status.textContent = 'Проверьте имя, email или Telegram-ник и описание задачи (минимум 10 символов).';
      status.focus();
      return;
    }
    sending = true;
    button.disabled = true;
    button.textContent = 'Отправляем…';
    form.setAttribute('aria-busy', 'true');
    goal('form_submit');
    try {
      const response = await fetch(form.action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const result = await response.json();
      if (!response.ok || result.success !== true) throw new Error('submission_failed');
      // The existing endpoint confirms persistence, not successful notification or paid conversion.
      goal('lead_saved');
      form.reset();
      started = false;
      status.textContent = 'Заявка сохранена. Отправка заявки не означает оплату услуги.';
      if (result.autoReply === 'telegram_not_started') status.textContent += ' Для ответа в Telegram нажмите «Запустить» у @PravKompas_bot по ссылке выше. Повторно отправлять заявку не нужно.';
      if (result.autoReply === 'email_failed') status.textContent += ' Автоответ по email не отправлен. Заявка сохранена; при срочном сроке свяжитесь напрямую.';
      if (result.autoReply === 'vk_not_allowed') status.textContent += ' Разрешите сообщения сообщества ВКонтакте. Заявка уже сохранена.';
    } catch {
      goal('form_error');
      status.dataset.error = 'true';
      status.textContent = 'Не удалось подтвердить отправку. Данные оставлены в форме. Если соединение прервалось, заявка могла сохраниться — уточните в Telegram или по email, прежде чем отправлять повторно.';
    } finally {
      sending = false;
      button.disabled = false;
      button.textContent = 'Отправить заявку на обсуждение';
      form.removeAttribute('aria-busy');
      status.focus();
    }
  });
})();
