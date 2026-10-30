const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/payment-confirmation.js'), 'utf8');
function fixture() {
  const handlers = {}, goals = [], saved = new Map();
  let consent = null;
  const context = {
    window: {addEventListener: (n, fn) => {handlers[n] = fn;}, pravkompasReachGoal: (...args) => goals.push(args)},
    document: {addEventListener: (n, fn) => {handlers[n] = fn;}},
    localStorage: {getItem: () => consent},
    sessionStorage: {getItem: k => saved.get(k), setItem: (k, v) => saved.set(k, v)},
  };
  vm.runInNewContext(source, context);
  return {goals, setConsent: v => {consent = v;}, emit: d => handlers['pravkompas:payment-confirmed']({detail: d}), accept: () => handlers['pravkompas:analytics-consent']()};
}
let checks = 0;
const f = fixture();
f.emit({id: 5, status: 'pending', amount: 1990}); assert.equal(f.goals.length, 0); checks++;
f.emit({id: 5, status: 'test_paid', amount: 1990}); assert.equal(f.goals.length, 0); checks++;
f.setConsent('rejected'); f.accept(); assert.equal(f.goals.length, 0); checks++;
f.setConsent('accepted'); f.accept(); assert.equal(f.goals[0][0], 'legal_payment_test_confirmed');
assert(!JSON.stringify(f.goals[0]).includes('1990')); checks++;
f.emit({id: 5, status: 'test_paid', amount: 1990}); assert.equal(f.goals.length, 1); checks++;
f.emit({id: 6, status: 'paid', amount: 39000}); assert.equal(f.goals[1][0], 'legal_payment_confirmed');
assert.equal(f.goals[1][1].order_price, 39000); checks++;
f.emit({id: 7, status: 'paid', amount: 'invalid'}); assert.equal(f.goals.length, 2); checks++;
assert(!JSON.stringify(f.goals).includes('example.ru')); checks++;
console.log(`PASS ${checks} payment analytics invariants: confirmed state, explicit consent, test isolation, deduplication`);

const html = fs.readFileSync(require('node:path').join(__dirname, '../oplata/uspeshno/index.html'), 'utf8');
const inline = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)][0][1];
const sequence = [], state = {};
vm.runInNewContext(inline, {
  URLSearchParams, location:{search:'?Shp_order=isolated_reference&SignatureValue=never_send',pathname:'/oplata/uspeshno/'},
  history:{state:null,replaceState:(s,_,url)=>{sequence.push('clean');state.value=s;assert.equal(url,'/oplata/uspeshno/');}},
  sessionStorage:{getItem:()=>null},document:{getElementById:()=>({})},
  fetch:()=>{sequence.push('status');return new Promise(()=>{});}
});
assert.deepEqual(sequence,['clean','status']);assert.equal(state.value.legalOrder,'isolated_reference');
console.log('PASS payment return URL is cleaned before status lookup and deferred analytics; reload reference preserved in history state');
