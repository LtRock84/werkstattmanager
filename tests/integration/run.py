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
    def __init__(self, base, user, password):
        redirect = Redirect()
        redirect.base = self.base = base
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), redirect,
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        page = self.opener.open(base + '/login', timeout=30).read().decode()
        data = urllib.parse.urlencode({'user': user, 'password': password, 'requesttoken': self.token(page)}).encode()
        self.opener.open(urllib.request.Request(base + '/login', data=data, headers={'Origin': base}), timeout=30).read()
        page = self.opener.open(base + '/apps/werkstattmanager/', timeout=30).read().decode()
        assert 'initial-state-werkstattmanager-mode' in page
        self.csrf = self.token(page)

    @staticmethod
    def token(page):
        m = re.search(r'data-requesttoken="([^"]+)"', page)
        assert m, 'Missing Nextcloud request token'
        return html.unescape(m.group(1))

    def call(self, path, data=None, expected=200):
        request = urllib.request.Request(self.base + '/apps/werkstattmanager' + path,
            data=None if data is None else json.dumps(data).encode(),
            headers={'requesttoken': self.csrf, 'Content-Type': 'application/json'})
        try:
            response = self.opener.open(request, timeout=30)
        except urllib.error.HTTPError as e:
            response = e
        assert response.status in (expected if isinstance(expected, tuple) else (expected,)), f'{path}: HTTP {response.status}'
        self.status = response.status
        return json.loads(response.read())


def wait_for_http(base):
    # occ can report installed before the image entrypoint starts Apache.
    redirect = Redirect()
    redirect.base = base
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), redirect)
    deadline = time.monotonic() + 60
    while True:
        try:
            with opener.open(base + '/status.php', timeout=5) as response:
                status = json.load(response)
            if status.get('installed') and not status.get('maintenance') and not status.get('needsDbUpgrade'):
                return
        except (OSError, json.JSONDecodeError):
            pass
        if time.monotonic() >= deadline:
            raise RuntimeError('Disposable Nextcloud HTTP startup timed out')
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
    m.call('/api/v1/orders', {'data': {'vehicle_id': vehicle['id'], 'title': 'Invalid',
        'request_id': str(uuid.uuid4()), 'planned_start': '2026-10-05T12:00:00Z', 'due_at': '2026-10-05T11:00:00Z'}}, expected=400)
    print('PASS real SQL/API: competing starts, parallel orders, idempotent pause, takeover, finish, approval, roles and dates')


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
    passwords = {x: secrets.token_urlsafe(32) for x in ['db', 'master', 'tablet']}
    def occ(*args, env=None):
        extra = ['-e', 'OC_PASS'] if env else []
        return run('docker', 'exec', '-u', 'www-data', *extra, app, 'php', 'occ', *args, env=env)
    try:
        with tempfile.TemporaryDirectory(prefix=prefix) as stage:
            with tarfile.open(sys.argv[1]) as archive:
                archive.extractall(stage, filter='data')
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
                '-v', str(Path(stage) / 'werkstattmanager') + ':/var/www/html/custom_apps/werkstattmanager', *opts, NC)
            deadline = time.monotonic() + 240
            while True:
                try:
                    status = json.loads(occ('status', '--output=json'))
                    if status.get('installed'):
                        break
                except (RuntimeError, json.JSONDecodeError):
                    pass
                if time.monotonic() > deadline:
                    raise RuntimeError('Disposable Nextcloud installation timed out')
                time.sleep(2)
            occ('app:enable', 'werkstattmanager')
            assert json.loads(occ('app:list', '--output=json'))['enabled']['werkstattmanager'] == version
            occ('group:add', 'werkstatt-tablet')
            occ('user:add', '--password-from-env', 'ci-tablet', env=dict(os.environ, OC_PASS=passwords['tablet']))
            occ('group:adduser', 'werkstatt-tablet', 'ci-tablet')
            address = run('docker', 'inspect', '--format', '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}', app)
            assert ipaddress.ip_address(address).is_private
            occ('config:system:set', 'trusted_domains', '1', '--value=' + address)
            base = 'http://' + address
            wait_for_http(base)
            workflow(base, passwords)
            occ('app:disable', 'werkstattmanager')
            occ('app:enable', 'werkstattmanager')
            run('docker', 'restart', app)
            deadline = time.monotonic() + 60
            while True:
                try:
                    m = Client(base, 'ci-master', passwords['master'])
                    assert len(m.call('/api/v1/records/orders')['items']) == 2
                    assert len(m.call('/api/v1/records/templates')['items']) == 5
                    break
                except (OSError, AssertionError):
                    if time.monotonic() > deadline:
                        raise
                    time.sleep(2)
            print(f'PASS package installation {version}, {database}, disable/enable and restart with data preserved')
    finally:
        for name in [app, db]:
            subprocess.run(['docker', 'rm', '-f', name], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        subprocess.run(['docker', 'volume', 'rm', volume], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        subprocess.run(['docker', 'network', 'rm', network], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


if __name__ == '__main__':
    main()
