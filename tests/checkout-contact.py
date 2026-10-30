"""Isolated checkout validation; never submit to production or a gateway."""
import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,json,sys
base=sys.argv[1]
assert base.startswith('http://127.0.0.1:'), 'Loopback staging only'
jar=http.cookiejar.CookieJar()
c=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
page=c.open(base+'/oplata/').read().decode();csrf=re.search('name="csrf" value="([^"]+)"',page)[1]
# Local HTTP fixture only: production Secure cookie remains unchanged.
for cookie in jar: cookie.secure=False
def send(**extra):
 d=dict(name='Изолированный тест',email='fixture@example.invalid',contact='@fixture_test',csrf=csrf,offer='1',privacy='1',payer='person',service_slug='written-legal-review',source='isolated-checkout-contact',amount='1')
 d.update(extra)
 try:
  with c.open(urllib.request.Request(base+'/api/legal_create_order.php',data=urllib.parse.urlencode(d).encode())) as r:return r.status,json.load(r)
 except urllib.error.HTTPError as e:return e.code,json.load(e)
for contact in ['', 'only@example.invalid', '+70000000000']:
 status,d=send(contact=contact);assert status==422 and 'contact' in d['errors'],(status,d)
status,d=send(service_slug='vneshtatny-yurist');assert status==422 and 'payer' in d['errors'],(status,d)
status,d=send();assert status==200 and d['success'] and d['fields']['OutSum']=='1990.00' and d['fields']['IsTest']=='1',(status,d)
print('PASS 5 isolated checkout cases: mandatory Telegram, B2B retainer, authoritative price, test mode')
