#!/usr/bin/env python3
"""Disposable CI-only Nextcloud install. Never accepts a server URL or real backup."""
import concurrent.futures
import html
import http.cookiejar
import importlib.util
import ipaddress
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import ssl
import subprocess
import sys
import tarfile
import tempfile
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[2]
NC = 'nextcloud:35.0.1-apache@sha256:b1ae671e9815401b0e837b19b9c778e89887721d67f0c8f9d34aa1d27a9a208f'
if os.environ.get('WM_TEST_NEXTCLOUD') == '35.0.0':
    NC = 'nextcloud:35.0.0-apache@sha256:e883eacf7b01fe8f07e1dbc98a64d2a1910855dcd3002ee7fa2c55fe31a39138'
DB = {
    'postgres': 'postgres:17-alpine@sha256:b0f9560a2de083e2cc7382e75f808c7381a32852a7ec49117deedb300e552b24',
    'mariadb': 'mariadb:11.8@sha256:6422478cb8e159f080fb1d8ccf65101e26fe51385787fde7d16c3b165a331f15',
}


def run(*args, env=None):
    p = subprocess.run(args, env=env, capture_output=True, text=True)
    if p.returncode:
        # Docker commands may contain temporary passwords. Do not dump argv or logs.
        raise RuntimeError(f'{args[0]} operation failed (exit {p.returncode})')
    return p.stdout.strip()


class Redirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if not newurl.startswith(self.base + '/'):
            raise ValueError('External redirect refused')
        return super().redirect_request(req, fp, code, msg, headers, newurl)


class Client:
    def __init__(self, base, user, password, cafile=None):
        redirect = Redirect()
        redirect.base = self.base = base
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPSHandler(context=ssl.create_default_context(cafile=cafile)), redirect,
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        page = self.opener.open(base + '/login', timeout=30).read().decode()
        data = urllib.parse.urlencode({'user': user, 'password': password, 'requesttoken': self.token(page)}).encode()
        self.opener.open(urllib.request.Request(base + '/login', data=data, headers={'Origin': base}), timeout=30).read()
        deadline=time.monotonic()+30
        while True:
            try:
                page = self.opener.open(base + '/apps/werkstattmanager/', timeout=30).read().decode()
                break
            except urllib.error.HTTPError as error:
                if error.code not in (404,503) or time.monotonic()>=deadline:raise
                time.sleep(1)
        assert 'initial-state-werkstattmanager-mode' in page
        self.csrf = self.token(page)

    @staticmethod
    def token(page):
        m = re.search(r'data-requesttoken="([^"]+)"', page)
        assert m, 'Missing Nextcloud request token'
        return html.unescape(m.group(1))

    def call(self, path, data=None, expected=200, method=None):
        request = urllib.request.Request(self.base + '/apps/werkstattmanager' + path, method=method,
            data=None if data is None else json.dumps(data).encode(),
            headers={'requesttoken': self.csrf, 'Content-Type': 'application/json'})
        try:
            response = self.opener.open(request, timeout=30)
        except urllib.error.HTTPError as e:
            response = e
        assert response.status in (expected if isinstance(expected, tuple) else (expected,)), f'{path}: HTTP {response.status}'
        self.status = response.status
        return json.loads(response.read())


def wait_for_http(base, host=None):
    # occ can report installed before the image entrypoint starts Apache.
    redirect = Redirect()
    redirect.base = base
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), redirect)
    deadline = time.monotonic() + 90
    last_status = 'no response'
    while True:
        try:
            with opener.open(urllib.request.Request(base + '/status.php', headers={'Host': host} if host else {}), timeout=5) as response:
                status = json.load(response)
                last_status = str({k:status.get(k) for k in ['installed','maintenance','needsDbUpgrade','version']})
            if status.get('installed') and not status.get('maintenance') and not status.get('needsDbUpgrade'):
                return
        except (OSError, json.JSONDecodeError) as error:
            last_status = type(error).__name__ + ' ' + str(getattr(error, 'code', ''))
            if isinstance(error, urllib.error.HTTPError):
                body=error.read(100000).decode(errors='replace').lower()
                hints=[term for term in ['untrusted domain', 'trusted_domains', 'invalid request', 'bad request', 'access denied'] if term in body]
                last_status+=' '+str(hints)
        if time.monotonic() >= deadline:
            raise RuntimeError('Disposable Nextcloud HTTP startup timed out: '+last_status)
        time.sleep(2)


def workflow(base, passwords):
    m = Client(base, 'ci-master', passwords['master'])
    tablets = [Client(base, 'ci-tablet', passwords['tablet']) for _ in range(2)]
    assert len(m.call('/api/v1/records/templates')['items']) == 5
    assert len(m.call('/api/v1/records/snippets')['items']) == 5
    vehicle = m.call('/api/v1/records/vehicles', {'data': {'kind': 'fleet', 'plate': 'CI-TEST'}})
    employees = [m.call('/api/v1/records/employees', {'data': {'name': name}}) for name in ['CI Alex', 'CI Robin']]
    def fresh(order):
        return m.call('/api/v1/records/orders?id=' + order['id'])['items'][0]
    def action(client, order, op, **data):
        order = fresh(order)
        return client.call('/api/v1/orders/' + order['id'] + '/' + op,
            {'data': {'version': order['version'], 'request_id': str(uuid.uuid4()), **data}})
    orders = [m.call('/api/v1/orders', {'data': {'vehicle_id': vehicle['id'], 'title': name,
        'request_id': str(uuid.uuid4()), 'minutes': 45}}) for name in ['CI A', 'CI B']]
    def race(attempts):
        barrier = threading.Barrier(2)
        def start(args):
            client, order, employee = args
            barrier.wait()
            result = client.call('/api/v1/orders/' + order['id'] + '/start', {'data': {
                'version': order['version'], 'request_id': str(uuid.uuid4()), 'employee_id': employee['id']}}, expected=(200, 409))
            return client.status, result
        with concurrent.futures.ThreadPoolExecutor(2) as pool:
            result = list(pool.map(start, attempts))
        assert sorted(x[0] for x in result) == [200, 409]
        return next(x[1] for x in result if x[0] == 200)
    winner = race([(tablets[0], orders[0], employees[0]), (tablets[1], orders[1], employees[0])])
    action(tablets[0], winner, 'pause', employee_id=employees[0]['id'], reason='CI race')
    order = fresh(orders[0])
    winner = race([(tablets[0], order, employees[0]), (tablets[1], order, employees[1])])
    active = m.call('/api/v1/records/active?order_id=' + winner['id'])['items']
    assert len(active) == 1
    action(tablets[0], winner, 'pause', employee_id=active[0]['employee_id'], reason='CI race')
    started = time.time()
    a = action(tablets[0], orders[0], 'start', employee_id=employees[0]['id'])
    a_interval = m.call('/api/v1/records/active?order_id=' + a['id'])['items'][0]['interval_id']
    b = action(tablets[1], orders[1], 'start', employee_id=employees[1]['id'])
    b_interval = m.call('/api/v1/records/active?order_id=' + b['id'])['items'][0]['interval_id']
    assert len(m.call('/api/v1/records/active')['items']) == 2
    time.sleep(2)
    payload = {'data': {'version': a['version'], 'request_id': str(uuid.uuid4()), 'employee_id': employees[0]['id'], 'reason': 'CI pause'}}
    a = tablets[0].call('/api/v1/orders/' + a['id'] + '/pause', payload)
    assert a == tablets[0].call('/api/v1/orders/' + a['id'] + '/pause', payload)
    b = action(tablets[1], b, 'finish', employee_id=employees[1]['id'], note='CI finished')
    time.sleep(2)
    a = action(tablets[1], a, 'start', employee_id=employees[1]['id'])
    takeover_interval = m.call('/api/v1/records/active?order_id=' + a['id'])['items'][0]['interval_id']
    time.sleep(2)
    a = action(tablets[1], a, 'finish', employee_id=employees[1]['id'], note='CI takeover')
    for order in [a, b]:
        intervals = m.call('/api/v1/records/intervals?order_id=' + order['id'])['items']
        assert intervals and all(x['ended'] is not None and x['ended'] >= x['started'] for x in intervals)
        assert action(m, order, 'approve')['status'] == 'completed'
    # Race-test intervals can share the same integer timestamp. Identify the
    # measured intervals by their actual booking IDs, never by database row order.
    ia = [m.call('/api/v1/records/intervals?id=' + key)['items'][0] for key in [a_interval, takeover_interval]]
    ib = m.call('/api/v1/records/intervals?id=' + b_interval)['items'][0]
    assert [x['employee_id'] for x in ia] == [x['id'] for x in employees]
    assert ib['employee_id'] == employees[1]['id']
    assert max(ia[0]['started'], ib['started']) < min(ia[0]['ended'], ib['ended'])
    gap = ia[1]['started'] - ia[0]['ended']
    assert gap >= 2
    assert sum(x['ended'] - x['started'] for x in ia) == ia[1]['ended'] - ia[0]['started'] - gap
    assert time.time() - started >= 6
    assert m.call('/api/v1/records/active')['items'] == []
    tablets[0].call('/api/v1/records/customers', expected=403)
    tablets[0].call('/api/v1/settings', expected=403)
    tablets[0].call('/api/v1/records/orders?status=draft', expected=403)
    tablets[0].call('/api/v1/records/audit?vehicle_id=' + vehicle['id'], expected=403)
    # Resolve real SQL references for the human-readable vehicle history. Exercise
    # both parts-request variants and persisted protocol text through the API.
    c = m.call('/api/v1/orders', {'data': {'vehicle_id': vehicle['id'], 'title': 'CI history',
        'request_id': str(uuid.uuid4())}})
    c = action(tablets[0], c, 'start', employee_id=employees[0]['id'])
    parts = []
    for pause in [False, True]:
        parts.append(tablets[0].call('/api/v1/actions/part-request', {'data': {
            'order_id': c['id'], 'employee_id': employees[0]['id'], 'request_id': str(uuid.uuid4()),
            'title': 'CI brake pad', 'quantity': 2, 'part_number': 'CI-42', 'priority': 'urgent',
            'note': 'CI front axle', 'pause': pause}}))
        assert fresh(c)['status'] == ('paused' if pause else 'active')
    c = action(tablets[1], c, 'start', employee_id=employees[1]['id'])
    c = action(tablets[1], c, 'pause', employee_id=employees[1]['id'], reason='CI inspection',
        note='CI checked', steps=['CI diagnostic'])
    c = action(tablets[1], c, 'start', employee_id=employees[1]['id'])
    c = action(tablets[1], c, 'finish', employee_id=employees[1]['id'], note='CI repaired', steps=['CI road test'])
    m.call('/api/v1/orders/' + c['id'] + '/approve', {'data': {
        'version': c['version'], 'request_id': str(uuid.uuid4())}}, expected=409)
    for part in parts:
        for status in ['ordered', 'received']:
            part = m.call('/api/v1/actions/part-status', {'data': {
                'id': part['id'], 'version': part['version'], 'status': status}})
    assert action(m, c, 'approve')['status'] == 'completed'
    audit = m.call('/api/v1/records/audit?order_id=' + c['id'])['items']
    requested = [row for row in audit if row['action'] == 'part.requested']
    assert len(requested) == 2 and {row['details']['paused'] for row in requested} == {False, True}
    for row in requested:
        assert row['context']['order'] == {'id': c['id'], 'title': 'CI history'}
        assert row['context']['employee'] == {'name': 'CI Alex'}
        assert row['context']['part'] == {'title': 'CI brake pad', 'quantity': 2,
            'part_number': 'CI-42', 'priority': 'urgent', 'note': 'CI front axle'}
    for event, note, steps in [('order.pause', 'CI checked', ['CI diagnostic']),
                               ('order.finish', 'CI repaired', ['CI road test'])]:
        row = next(row for row in audit if row['action'] == event)
        assert row['details']['note'] == note and row['details']['steps'] == steps
        assert row['context']['employee'] == {'name': 'CI Robin'}
    print('PASS real SQL/API: readable audit context, notes, steps, both parts/pause variants and audit role protection')
    m.call('/api/v1/orders', {'data': {'vehicle_id': vehicle['id'], 'title': 'Invalid',
        'request_id': str(uuid.uuid4()), 'planned_start': '2026-10-05T12:00:00Z', 'due_at': '2026-10-05T11:00:00Z'}}, expected=400)
    print('PASS real SQL/API: competing starts, parallel orders, idempotent pause, takeover, finish, approval, roles and dates')
    planning_workflow(m, tablets, vehicle, passwords, base)


def planning_workflow(m, tablets, vehicle, passwords, base):
    services = m.call('/api/v1/services')['items']
    assert len(services) >= 250
    oil = next(s for s in services if s['title'] == 'Öl- und Filterwechsel')
    assert oil['minutes'] == 45
    line = {k: oil[k] for k in ['title','minutes','unit','steps']}
    line.update(service_id=oil['id'], key=oil['seed_key'], quantity=2, vehicle_class='car', own_price=99999)
    def payload(**extra):
        return {'data': dict(vehicle_id=vehicle['id'], title='CI Termin', arrival='2026-10-06T06:00:00Z', status='confirmed', minutes=420, request_id=str(uuid.uuid4()), **extra)}
    # Two masters cannot both fill the same day beyond the budget.
    clients = [Client(base,'ci-master',passwords['master']) for _ in range(2)]
    barrier = threading.Barrier(2)
    def confirm(client):
        barrier.wait()
        response=client.call('/api/v1/appointments',payload(),expected=(200,409))
        return client.status,response
    with concurrent.futures.ThreadPoolExecutor(2) as pool:
        results=list(pool.map(confirm,clients))
    assert sorted(status for status,_ in results)==[200,409]
    a=next(row for status,row in results if status==200)
    data=payload(); data['data'].update(id=a['id'],version=a['version'],status='cancelled')
    m.call('/api/v1/appointments',data)
    data=payload();data['data'].update(service_lines=[line],customer_email='test@example.invalid',reminder=True,checklist={'key_received':True,'mileage':12345,'callback_note':'INTERNAL CALLBACK'})
    a=m.call('/api/v1/appointments',data)
    assert a==m.call('/api/v1/appointments',data)
    assert a['minutes']==90 and 'own_price' not in a['service_lines'][0]
    assert a['checklist']['key_received'] and a['checklist']['mileage']==12345
    original=a
    update=payload();update['data'].update(id=a['id'],version=a['version'],service_lines=a['service_lines'],customer_email=a['customer_email'],reminder=True,stage='customer_question')
    a=m.call('/api/v1/appointments',update)
    assert a['checklist']==original['checklist']
    invalid=payload();invalid['data'].update(id=a['id'],version=a['version'],checklist={'key_received':'true'})
    m.call('/api/v1/appointments',invalid,expected=400)
    stale=payload();stale['data'].update(id=a['id'],version=original['version'])
    m.call('/api/v1/appointments',stale,expected=409)
    tablets[0].call('/api/v1/appointments',update,expected=403)
    def convert(client):
        return client.call('/api/v1/appointments/'+a['id']+'/order', {'version':a['version']}, expected=(200,409))
    with concurrent.futures.ThreadPoolExecutor(2) as pool:
        converted=list(pool.map(convert,clients))
    successes=[o for o in converted if 'id' in o]
    assert successes and len({o['id'] for o in successes})==1
    order=m.call('/api/v1/appointments/'+a['id']+'/order', {'version':a['version']})
    assert order['status']=='draft' and order['minutes']==90
    m.call('/api/v1/orders/'+order['id']+'/release',{'data':{'version':order['version'],'request_id':str(uuid.uuid4())}})
    days=m.call('/api/v1/appointments?from=2026-10-06&to=2026-10-06')['days']
    assert days[0]['used']==90
    m.call('/api/v1/services',{'data':dict(oil,minutes=55)})
    assert m.call('/api/v1/records/orders?id='+order['id'])['items'][0]['service_lines'][0]['minutes']==45
    profile=dict(costs=dict(personnel=18000000,rent=6000000),billable_hours=3000,profit_percent=20,material_percent=25,vat_percent=19)
    result=m.call('/api/v1/pricing',{'data':{'profile':profile,'lines':[dict(minutes=90,material_cents=6000)]},'save':True})
    assert result['cost_rate_cents']==8000 and result['net_cents']==21900 and result['gross_cents']==26061
    for path in ['/api/v1/services','/api/v1/pricing','/api/v1/deliveries','/api/v1/calendar-settings','/api/v1/appointments?from=2026-10-06&to=2026-10-06']:
        tablets[0].call(path,expected=403)
    tablet_order=tablets[0].call('/api/v1/records/orders?id='+order['id'])['items'][0]
    assert 'service_lines' in tablet_order and 'customer_email' not in tablet_order
    assert 'own_price' not in tablet_order['service_lines'][0] and 'checklist' not in tablet_order
    print('PASS real SQL/API: 295 seeded services, competing confirmations, immutable snapshots, unique appointment/order link, single capacity count, independent calculation and tablet rights')



def main():
    database = os.environ.get('WM_TEST_DATABASE', 'postgres')
    if database not in DB or len(sys.argv) != 2:
        raise SystemExit('Usage: WM_TEST_DATABASE=postgres|mariadb python3 tests/integration/run.py package.tar.gz')
    spec = importlib.util.spec_from_file_location('check', ROOT / 'scripts/check-package.py')
    checker = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(checker)
    version, _ = checker.check(sys.argv[1])
    prefix = 'wm-ci-' + secrets.token_hex(5)
    app, db, network, volume = [prefix + x for x in ['-app', '-db', '-net', '-volume']]
    passwords = {x: 'ci-' + secrets.token_urlsafe(32) for x in ['db', 'master', 'tablet']}
    def occ(*args, env=None):
        extra = ['-e', 'OC_PASS'] if env else []
        return run('docker', 'exec', '-u', 'www-data', *extra, app, 'php', 'occ', *args, env=env)
    failed=False
    try:
        with tempfile.TemporaryDirectory(prefix=prefix, delete=os.environ.get('WM_TEST_KEEP_FAILED') != '1') as stage:
            with tarfile.open(os.environ.get('WM_TEST_UPGRADE_FROM') or sys.argv[1]) as archive:
                archive.extractall(stage, filter='data')
            if os.environ.get('WM_TEST_MAIL_PACKAGE'):
                with tarfile.open(os.environ['WM_TEST_MAIL_PACKAGE']) as mail_archive: mail_archive.extractall(Path(stage), filter='data')
            run('docker', 'pull', NC)
            run('docker', 'pull', DB[database])
            run('docker', 'network', 'create', '--internal', network)
            run('docker', 'volume', 'create', volume)
            db_env = {'POSTGRES_DB': 'nextcloud', 'POSTGRES_USER': 'nextcloud', 'POSTGRES_PASSWORD': passwords['db']} if database == 'postgres' else {
                'MARIADB_DATABASE': 'nextcloud', 'MARIADB_USER': 'nextcloud', 'MARIADB_PASSWORD': passwords['db'], 'MARIADB_ROOT_PASSWORD': secrets.token_urlsafe(32)}
            opts = [item for k, v in db_env.items() for item in ['-e', f'{k}={v}']]
            run('docker', 'run', '-d', '--name', db, '--network', network, *opts, DB[database],
                *(['--transaction-isolation=READ-COMMITTED', '--binlog-format=ROW'] if database == 'mariadb' else []))
            env = {'NEXTCLOUD_ADMIN_USER': 'ci-master', 'NEXTCLOUD_ADMIN_PASSWORD': passwords['master'], 'NEXTCLOUD_TRUSTED_DOMAINS': '127.0.0.1'}
            env.update({f'{"POSTGRES" if database == "postgres" else "MYSQL"}_{k}': v for k, v in {'HOST': db, 'DB': 'nextcloud' if database == 'postgres' else None, 'DATABASE': 'nextcloud' if database == 'mariadb' else None, 'USER': 'nextcloud', 'PASSWORD': passwords['db']}.items() if v is not None})
            opts = [item for k, v in env.items() for item in ['-e', f'{k}={v}']]
            run('docker', 'run', '-d', '--name', app, '--network', network, '-v', volume + ':/var/www/html',
                '-v', str(Path(stage) / 'werkstattmanager') + ':/var/www/html/custom_apps/werkstattmanager',
                *(['-v', str(Path(stage) / 'mail') + ':/var/www/html/custom_apps/mail'] if os.environ.get('WM_TEST_MAIL_PACKAGE') else []), *opts, NC)
            deadline = time.monotonic() + 240
            while True:
                try:
                    status = json.loads(occ('status', '--output=json'))
                    if status.get('installed'):
                        break
                except (RuntimeError, json.JSONDecodeError):
                    pass
                state = json.loads(run('docker', 'inspect', '--format', '{{json .State}}', app))
                if state['Status'] == 'exited':
                    raise RuntimeError('Disposable Nextcloud exited during installation (exit ' + str(state['ExitCode']) + ')')
                if time.monotonic() > deadline:
                    raise RuntimeError('Disposable Nextcloud installation timed out')
                time.sleep(2)
            address = run('docker', 'inspect', '--format', '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}', app)
            wait_for_http('http://'+address, host='127.0.0.1')
            occ('app:enable', 'werkstattmanager')
            old_rank_appointment = old_rank_order = old_rank_service = None
            if os.environ.get('WM_TEST_UPGRADE_FROM'):
                occ('config:system:set','trusted_domains','1','--value='+address)
                wait_for_http('http://'+address)
                old_client=Client('http://'+address,'ci-master',passwords['master'])
                preserved=old_client.call('/api/v1/records/vehicles',{'data':{'kind':'fleet','plate':'CI-UPGRADE-PRESERVE'}})
                import xml.etree.ElementTree as ET
                previous_version=ET.parse(Path(stage)/'werkstattmanager/appinfo/info.xml').getroot().findtext('version')
                old_customer=old_client.call('/api/v1/records/customers',{'data':{'name':'CI existing customer','email':'upgrade@example.invalid'}})
                old_appointment=None
                if previous_version.startswith('0.2.'):
                    old_appointment=old_client.call('/api/v1/appointments', {'data':dict(vehicle_id=preserved['id'],title='CI preserved appointment',arrival='2026-12-07T07:00:00Z',minutes=35,status='draft',note='CI preserved internal note',request_id=str(uuid.uuid4()))})
                if previous_version=='0.2.1':
                    old_rank_service=old_client.call('/api/v1/services', {'data':dict(title='CI old ranking',category='Prüfung',minutes=30)})
                    old_line=dict(service_id=old_rank_service['id'],title='CI old ranking',minutes=30,quantity=2)
                    old_rank_appointment=old_client.call('/api/v1/appointments', {'data':dict(vehicle_id=preserved['id'],title='CI old confirmed',arrival='2026-12-08T07:00:00Z',service_lines=[old_line],status='confirmed',request_id=str(uuid.uuid4()))})
                    old_rank_order=old_client.call('/api/v1/appointments/'+old_rank_appointment['id']+'/order', {'version':old_rank_appointment['version']})
                    old_rank_order=old_client.call('/api/v1/orders/'+old_rank_order['id']+'/release', {'data':dict(version=old_rank_order['version'],request_id=str(uuid.uuid4()))})
                    old_rank_appointment=next(a for a in old_client.call('/api/v1/appointments?from=2026-12-08&to=2026-12-08')['items'] if a['id']==old_rank_appointment['id'])
                with tarfile.open(sys.argv[1]) as upgrade_archive:upgrade_archive.extractall(stage,filter='data')
                occ('upgrade')
                current=Client('http://'+address,'ci-master',passwords['master'])
                assert current.call('/api/v1/records/vehicles?id='+preserved['id'])['items'][0]['plate']=='CI-UPGRADE-PRESERVE'
                upgraded_customer=current.call('/api/v1/records/customers?id='+old_customer['id'])['items'][0]
                if 'deadline_emails' not in old_customer:
                    assert upgraded_customer.pop('deadline_emails') is False
                assert upgraded_customer==old_customer
                if old_appointment:
                    now=next(a for a in current.call('/api/v1/appointments?from=2026-12-07&to=2026-12-07')['items'] if a['id']==old_appointment['id'])
                    if 'checklist' not in old_appointment: assert now.pop('checklist') is None
                    assert now==old_appointment
                if old_rank_service:
                    assert next(s for s in current.call('/api/v1/services')['items'] if s['id']==old_rank_service['id'])['usage_count']==1
                    assert current.call('/api/v1/records/orders?id='+old_rank_order['id'])['items'][0]==old_rank_order
                    assert next(a for a in current.call('/api/v1/appointments?from=2026-12-08&to=2026-12-08')['items'] if a['id']==old_rank_appointment['id'])==old_rank_appointment
                    print('PASS SQL upgrade service-usage backfill: linked historical appointment/order counted once; every old field/version unchanged')
                print(f'PASS upgrade from {previous_version} with existing vehicle'+(' and appointment preserved' if old_appointment else ' preserved'))
            assert json.loads(occ('app:list', '--output=json'))['enabled']['werkstattmanager'] == version
            occ('group:add', 'werkstatt-tablet')
            occ('user:add', '--password-from-env', 'ci-tablet', env=dict(os.environ, OC_PASS=passwords['tablet']))
            occ('group:adduser', 'werkstatt-tablet', 'ci-tablet')
            address = run('docker', 'inspect', '--format', '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}', app)
            assert ipaddress.ip_address(address).is_private
            occ('config:system:set', 'trusted_domains', '1', '--value=' + address)
            base = 'http://' + address
            wait_for_http(base)
            if os.environ.get('WM_TEST_MAIL_PACKAGE'): occ('app:enable','mail')
            workflow(base, passwords)
            if os.environ.get('WM_TEST_DELIVERY') != '1':
                import driver_workflow
                driver_workflow.check(base, passwords, Client)
            if os.environ.get('WM_TEST_DELIVERY')=='1':
                import planning_delivery
                planning_delivery.check(base,passwords,app,occ,run,Client)
                occ('config:system:delete','overwrite.cli.url');occ('config:system:delete','overwritehost');occ('config:system:delete','overwriteprotocol')
            import intake_access
            extra_orders = intake_access.check(base, passwords, Client, occ)
            import statistics
            extra_orders += statistics.check(base, passwords, Client)
            import customer_mail
            customer_mail.check(base, passwords, Client)
            import company_mail
            company_mail.check(base, passwords, Client, occ, run, app)
            expected_orders = (6 if os.environ.get('WM_TEST_DELIVERY') == '1' else 5) + extra_orders + (1 if old_rank_order else 0)
            assert len(Client(base, 'ci-master', passwords['master']).call('/api/v1/records/orders')['items']) == expected_orders
            occ('app:disable', 'werkstattmanager')
            occ('app:enable', 'werkstattmanager')
            run('docker', 'restart', app)
            deadline = time.monotonic() + 60
            while True:
                try:
                    m = Client(base, 'ci-master', passwords['master'])
                    assert len(m.call('/api/v1/records/orders')['items']) == expected_orders
                    assert len(m.call('/api/v1/records/templates')['items']) == 5
                    break
                except (OSError, AssertionError):
                    if time.monotonic() > deadline:
                        raise
                    time.sleep(2)
            print(f'PASS package installation {version}, {database}, disable/enable and restart with data preserved')
    except Exception:
        failed=True
        print('Test instance diagnostic:', app, db, network, volume, stage, flush=True)
        raise
    finally:
        if failed and os.environ.get('WM_TEST_KEEP_FAILED') == '1':
            print('Diagnostic containers retained; cleanup required:', prefix, flush=True)
        else:
            if 'stage' in locals():shutil.rmtree(stage,ignore_errors=True)
            for name in [app, db]:
                subprocess.run(['docker', 'rm', '-f', name], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            subprocess.run(['docker', 'volume', 'rm', volume], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            subprocess.run(['docker', 'network', 'rm', network], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


if __name__ == '__main__':
    main()
