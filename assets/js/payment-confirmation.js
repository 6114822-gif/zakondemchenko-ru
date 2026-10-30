(() => {
  'use strict';
  let confirmed = null;
  function report() {
    if (!confirmed || typeof window.pravkompasReachGoal !== 'function') return;
    try {
      if (localStorage.getItem('zakondemchenko_analytics_consent') !== 'accepted') return;
      const key = 'legal_payment_goal:' + confirmed.id;
      if (sessionStorage.getItem(key) === 'sent') return;
      window.pravkompasReachGoal(confirmed.test ? 'legal_payment_test_confirmed' : 'legal_payment_confirmed', confirmed.test ? {test: true} : {order_price: confirmed.amount, currency: 'RUB'});
      sessionStorage.setItem(key, 'sent');
    } catch {}
  }
  window.addEventListener('pravkompas:payment-confirmed', ({detail}) => {
    if (!detail || !Number.isInteger(detail.id) || !['paid', 'test_paid'].includes(detail.status)) return;
    confirmed = {id: detail.id, test: detail.status === 'test_paid', amount: Number(detail.amount)};
    if (!Number.isFinite(confirmed.amount) || confirmed.amount <= 0) { confirmed = null; return; }
    report();
  });
  document.addEventListener('pravkompas:analytics-consent', report);
})();
