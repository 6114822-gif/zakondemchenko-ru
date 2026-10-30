"""Run only against isolated staging with fake keys and notification sinks.
Usage: python3 tests/payment-sandbox.py http://127.0.0.1:18750 /private/staging-orders.sqlite
Never use a production database. No gateway requests or real payments occur.
"""
import hashlib
import json
import sqlite3
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

base, database = sys.argv[1:]
assert base.startswith('http://127.0.0.1:'), 'Loopback staging only'
assert Path(database).name == 'staging-orders.sqlite', 'Explicit staging fixture required'
db = sqlite3.connect(database)
checks = 0


def request(invoice, public, amount='1990.00', test=False, signature=None):
    password = 'fake-test-two' if test else 'fake-two'
    expected = hashlib.md5(f'{amount}:{invoice}:{password}:Shp_order={public}'.encode()).hexdigest()
    data = {'OutSum': amount, 'InvId': str(invoice), 'Shp_order': public,
            'SignatureValue': signature or expected}
    if test:
        data['IsTest'] = '1'
    req = urllib.request.Request(base + '/api/legal_payment_result.php',
                                 data=urllib.parse.urlencode(data).encode())
    try:
        with urllib.request.urlopen(req) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()


def seed(index, test):
    public = f'{index:032x}'
    db.execute('''INSERT INTO legal_orders
      (id,public_id,status,service_slug,service_name,amount,customer_name,email,created_at,is_test)
      VALUES (?,?,'pending','written-legal-review','ТЕСТ',1990,'Тест','test@example.invalid',datetime('now'),?)''',
               (index, public, test))
    db.commit()
    return public


def state(index):
    return db.execute('SELECT status FROM legal_orders WHERE id=?', (index,)).fetchone()[0]


def ok(condition):
    global checks
    assert condition
    checks += 1


p = seed(1001, 1)
ok(request(1001, p, signature='bad')[0] == 403 and state(1001) == 'pending')
ok(request(1001, p, amount='1.00', test=True)[0] == 404 and state(1001) == 'pending')
ok(request(1001, p, test=True) == (200, 'OK1001') and state(1001) == 'test_paid')
before = db.execute('SELECT COUNT(*),MAX(revision) FROM legal_crm_relay_outbox').fetchone()
ok(request(1001, p, test=True) == (200, 'OK1001'))
ok(db.execute('SELECT COUNT(*),MAX(revision) FROM legal_crm_relay_outbox').fetchone() == before)
ok(request(1001, p) == (200, 'OK1001') and state(1001) == 'test_paid')
p = seed(1002, 0)
ok(request(1002, p) == (200, 'OK1002') and state(1002) == 'paid')
p = seed(1003, 0)
ok(request(1003, p, test=True) == (200, 'OK1003') and state(1003) == 'test_paid')
ok(request(9999, 'f' * 32, test=True)[0] == 404)
ok(db.execute('PRAGMA integrity_check').fetchone()[0] == 'ok')
print(json.dumps({'passed': checks, 'scope': 'isolated copied callback, fake signatures, SQLite; no gateway'}, ensure_ascii=False))
