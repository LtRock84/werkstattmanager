"""Actual anonymous HTTP, uploads and master/tablet flow on disposable Nextcloud only."""
import html
import http.cookiejar
import json
import ssl
import struct
import time
import urllib.error
import urllib.request
import uuid
import zlib


def check(base, passwords, Client, cafile=None):
    class Public(Client):
        def __init__(self):
            self.base = base
            self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}),
                urllib.request.HTTPSHandler(context=ssl.create_default_context(cafile=cafile)),
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            page = self.opener.open(base + '/apps/werkstattmanager/fahrer', timeout=30).read().decode()
            assert 'initial-state-werkstattmanager-mode' in page
            self.csrf = self.token(page)

        def raw(self, path, data=None, content_type='application/json', csrf=True):
            headers = {'Content-Type': content_type, 'Accept': 'application/json'}
            if csrf: headers['requesttoken'] = self.csrf
            try:
                r = self.opener.open(urllib.request.Request(base + '/apps/werkstattmanager' + path,
                    data=data, headers=headers), timeout=30)
            except urllib.error.HTTPError as error:
                r = error
            return r.status, r.headers, r.read()

        def photo(self, defect, image, expected=200, mime='image/png'):
            boundary = uuid.uuid4().hex
            body = (f'--{boundary}\r\nContent-Disposition: form-data; name="defect_id"\r\n\r\n{defect}\r\n'
                f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="test.png"\r\nContent-Type: {mime}\r\n\r\n').encode()
            body += image + f'\r\n--{boundary}--\r\n'.encode()
            status, _, response = self.raw('/public/v1/photos', body, 'multipart/form-data; boundary=' + boundary)
            assert status == expected, f'Photo upload: HTTP {status}, expected {expected}'
            return json.loads(response)

    def png(colour):
        def chunk(kind, value):
            return struct.pack('>I', len(value)) + kind + value + struct.pack('>I', zlib.crc32(kind + value))
        return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 16, 16, 8, 2, 0, 0, 0)) + \
            chunk(b'tEXt', b'Comment\x00PRIVATE-METADATA-REMOVE') + \
            chunk(b'IDAT', zlib.compress((b'\x00' + bytes(colour) * 16) * 16)) + chunk(b'IEND', b'')

    m = Client(base, 'ci-master', passwords['master'], cafile=cafile)
    t = Client(base, 'ci-tablet', passwords['tablet'], cafile=cafile)
    prefix = 'TLS' if cafile else 'HTTP'
    v = m.call('/api/v1/records/vehicles', {'data': {'kind': 'fleet', 'plate': 'CI-DRIVER-' + prefix, 'notes': 'PRIVATE VEHICLE NOTE'}})
    other = m.call('/api/v1/records/vehicles', {'data': {'kind': 'fleet', 'plate': 'CI-OTHER-' + prefix}})
    m.call('/api/v1/actions/mileage', {'data': dict(vehicle_id=v['id'], value=10000, date='2026-10-01')})
    customer = m.call('/api/v1/records/customers', {'data': dict(name='=CI fictitious customer', email='customer@example.invalid')})
    customer_vehicle = m.call('/api/v1/records/vehicles', {'data': dict(kind='customer', plate='CI-CUSTOMER-' + prefix, customer_id=customer['id'])})
    m.call('/api/v1/actions/vehicle-link', {'data': {'vehicle_id': customer_vehicle['id']}}, expected=400)
    t.call('/api/v1/actions/vehicle-link', {'data': {'vehicle_id': v['id']}}, expected=403)
    deadline = m.call('/api/v1/records/deadlines', {'data': dict(vehicle_id=v['id'], title='CI repeating service', due_date='2026-10-06', due_km=10100, repeat_months=1, repeat_km=1000)})
    link = m.call('/api/v1/actions/vehicle-link', {'data': {'vehicle_id': v['id']}})['token']
    other_link = m.call('/api/v1/actions/vehicle-link', {'data': {'vehicle_id': other['id']}})['token']
    d, outsider = Public(), Public()
    d.call('/public/v1/vehicle', expected=401)
    d.call('/public/v1/session', {'token': 'invalid'}, expected=404)
    status, _, _ = d.raw('/public/v1/session', json.dumps({'token': link}).encode(), csrf=False)
    assert status == 412, 'Public session exchange must retain CSRF protection'
    view = d.call('/public/v1/session', {'token': link})
    assert view['vehicle']['plate'] == v['plate'] and view['items'] == []
    assert set(view['vehicle']) == {'plate', 'number', 'make', 'model'}
    outsider.call('/public/v1/session', {'token': other_link})
    data = {'data': dict(request_id=str(uuid.uuid4()), category='lights', description='CI internal driver detail',
        priority='urgent', reporter='CI fictitious driver', mileage=10123, vehicle_id=other['id'])}
    report = d.call('/public/v1/defects', data)
    assert report == d.call('/public/v1/defects', data), 'Retry creates no second report'
    d.call('/public/v1/defects', {'data': dict(data['data'], mileage=10124)}, expected=409)
    for bad in [-1, 10000001]:
        d.call('/public/v1/defects', {'data': dict(data['data'], request_id=str(uuid.uuid4()), mileage=bad)}, expected=400)
    row = m.call('/api/v1/records/defects?id=' + report['id'])['items'][0]
    assert row['vehicle_id'] == v['id'] and row['status'] == 'new' and not row['published'] and not row['mileage_confirmed']
    assert row['mileage'] == 10123 and row['reporter'] == 'CI fictitious driver'
    assert m.call('/api/v1/records/vehicles?id=' + v['id'])['items'][0]['mileage'] == 10000
    assert d.call('/public/v1/vehicle')['items'] == []
    outsider.photo(row['id'], png([200, 0, 0]), expected=403)
    d.photo(row['id'], b'<script>bad</script>', expected=400)
    images = [png([n * 35, 20, 200]) for n in range(6)]
    first = d.photo(row['id'], images[0])
    assert first == d.photo(row['id'], images[0]), 'Photo retry creates no duplicate'
    for image in images[1:5]: d.photo(row['id'], image)
    d.photo(row['id'], images[5], expected=400)
    attachments = m.call('/api/v1/records/attachments?vehicle_id=' + v['id'])['items']
    assert len(attachments) == 5 and all(a['ready'] and a['mime'] == 'image/jpeg' for a in attachments)
    for a in attachments:
        status, _, body = d.raw('/api/v1/attachments/' + a['id'])
        assert not body.startswith(b'\xff\xd8'), 'Driver must not download internal images'
        t.call('/api/v1/attachments/' + a['id'], expected=403)
        r = m.opener.open(urllib.request.Request(base + '/apps/werkstattmanager/api/v1/attachments/' + a['id'],
            headers={'requesttoken': m.csrf}), timeout=30)
        raw = r.read()
        assert raw.startswith(b'\xff\xd8') and b'PRIVATE-METADATA-REMOVE' not in raw
        assert r.headers['X-Content-Type-Options'] == 'nosniff'
    duplicate = d.call('/public/v1/defects', {'data': dict(data['data'], request_id=str(uuid.uuid4()))})
    foreign = outsider.call('/public/v1/defects', {'data': dict(data['data'], request_id=str(uuid.uuid4()))})
    duplicate = m.call('/api/v1/records/defects?id=' + duplicate['id'])['items'][0]
    m.call('/api/v1/actions/defect-update', {'data': dict(duplicate, status='duplicate', duplicate_id=foreign['id'])}, expected=400)
    duplicate = m.call('/api/v1/actions/defect-update', {'data': dict(duplicate, status='duplicate', duplicate_id=row['id'])})
    assert duplicate['duplicate_id'] == row['id'] and not duplicate['published']
    t.call('/api/v1/actions/mileage', {'data': dict(vehicle_id=v['id'], value=10123, date='2026-10-06', defect_id=row['id'])}, expected=403)
    m.call('/api/v1/actions/mileage', {'data': dict(vehicle_id=other['id'], value=10123, date='2026-10-06', defect_id=row['id'])}, expected=400)
    m.call('/api/v1/actions/mileage', {'data': dict(vehicle_id=v['id'], value=10123, date='2026-10-06', defect_id=row['id'])})
    m.call('/api/v1/actions/mileage', {'data': dict(vehicle_id=v['id'], value=10123, date='2026-10-06', defect_id=row['id'])}, expected=400)
    assert m.call('/api/v1/records/vehicles?id=' + v['id'])['items'][0]['mileage'] == 10123
    history = m.call('/api/v1/records/mileage?vehicle_id=' + v['id'])['items']
    assert len(history) == 2 and sum(x['source'] == 'driver-confirmed' for x in history) == 1
    completed = m.call('/api/v1/actions/deadline-complete', {'data': dict(id=deadline['id'], date='2026-10-06', km=10123)})
    assert completed['status'] == 'completed'
    m.call('/api/v1/actions/deadline-complete', {'data': dict(id=deadline['id'], date='2026-10-06', km=10123)}, expected=409)
    deadlines = m.call('/api/v1/records/deadlines?vehicle_id=' + v['id'])['items']
    assert len(deadlines) == 2
    next_deadline = next(x for x in deadlines if x['status'] == 'open')
    assert next_deadline['due_date'] == '2026-11-06' and next_deadline['due_km'] == 11123
    row = m.call('/api/v1/records/defects?id=' + row['id'])['items'][0]
    row = m.call('/api/v1/actions/defect-update', {'data': dict(row, status='open', published=True, public_text='CI light repair scheduled')})
    visible = d.call('/public/v1/vehicle')
    assert len(visible['items']) == 1 and visible['items'][0]['text'] == 'CI light repair scheduled'
    assert set(visible['items'][0]) == {'text', 'status', 'created_at'}
    assert outsider.call('/public/v1/vehicle')['items'] == []
    order = m.call('/api/v1/orders', {'data': dict(vehicle_id=v['id'], title='CI Driver repair ' + prefix,
        defect_id=row['id'], minutes=15, request_id=str(uuid.uuid4()))})
    assert m.call('/api/v1/records/defects?id=' + row['id'])['items'][0]['order_id'] == order['id']
    employee = m.call('/api/v1/records/employees')['items'][0]
    def action(client, op, **data):
        current = m.call('/api/v1/records/orders?id=' + order['id'])['items'][0]
        return client.call('/api/v1/orders/' + order['id'] + '/' + op,
            {'data': dict(version=current['version'], request_id=str(uuid.uuid4()), **data)})
    action(t, 'start', employee_id=employee['id']); time.sleep(1)
    part = t.call('/api/v1/actions/part-request', {'data': dict(order_id=order['id'], employee_id=employee['id'],
        title='CI replacement bulb', quantity=1, pause=True, request_id=str(uuid.uuid4()))})
    assert m.call('/api/v1/records/orders?id=' + order['id'])['items'][0]['status'] == 'paused'
    for status in ['ordered', 'received']:
        part = m.call('/api/v1/actions/part-status', {'data': dict(id=part['id'], version=part['version'], status=status)})
    action(t, 'start', employee_id=employee['id']);time.sleep(1)
    action(t, 'finish', employee_id=employee['id'], steps=['CI bulb replaced'], note='CI repaired')
    action(m, 'rework', reason='CI repeat test')
    action(t, 'start', employee_id=employee['id']);time.sleep(1)
    action(t, 'finish', employee_id=employee['id'], note='CI repeat test passed')
    action(m, 'approve', resolved_defects=[row['id']])
    assert d.call('/public/v1/vehicle')['items'] == []
    row = m.call('/api/v1/records/defects?id=' + row['id'])['items'][0]
    assert row['status'] == 'resolved' and row['mileage_confirmed'] and not row['published']
    intervals = m.call('/api/v1/records/intervals?order_id=' + order['id'])['items']
    assert len(intervals) == 3 and all(x['ended'] >= x['started'] + 1 for x in intervals)
    assert m.call('/api/v1/records/active')['items'] == []
    interval = intervals[0]
    correction = dict(id=interval['id'], version=interval['version'], started=interval['started'], ended=interval['ended'], reason='CI verified measurement')
    m.call('/api/v1/actions/time-correct', {'data': dict(correction, reason='')}, expected=400)
    t.call('/api/v1/actions/time-correct', {'data': correction}, expected=403)
    corrected = m.call('/api/v1/actions/time-correct', {'data': correction})
    assert corrected['started'] == interval['started'] and corrected['ended'] == interval['ended']
    audit = m.call('/api/v1/records/audit?order_id=' + order['id'])['items']
    change = next(x for x in audit if x['action'] == 'time.corrected')
    assert change['details']['before'] == change['details']['after'] == dict(started=interval['started'], ended=interval['ended'])
    assert change['details']['reason'] == correction['reason']
    for kind in ['customers', 'vehicles', 'orders', 'intervals', 'parts', 'defects', 'deadlines', 'mileage', 'audit', 'appointments']:
        r = m.opener.open(urllib.request.Request(base + '/apps/werkstattmanager/api/v1/export/' + kind,
            headers={'requesttoken': m.csrf}), timeout=30)
        csv = r.read()
        assert csv.startswith(b'\xef\xbb\xbf') and 'text/csv' in r.headers['Content-Type']
        if kind == 'customers': assert b"'=CI fictitious customer" in csv
        t.call('/api/v1/export/' + kind, expected=403)
    print('PASS ' + prefix + ' connected administration: customer/vehicle relation, QR role/fleet restriction, same-vehicle duplicate merge, recurring deadline once, reasoned time correction with originals, ten CSV exports and tablet denial')
    rotated = m.call('/api/v1/actions/vehicle-link', {'data': {'vehicle_id': v['id']}})['token']
    d.call('/public/v1/vehicle', expected=403)
    d.call('/public/v1/defects', {'data': dict(data['data'], request_id=str(uuid.uuid4()))}, expected=403)
    d.photo(row['id'], images[0], expected=403)
    d.call('/public/v1/session', {'token': link}, expected=404)
    d.call('/public/v1/session', {'token': rotated})
    m.call('/api/v1/actions/vehicle-link', {'data': {'vehicle_id': v['id'], 'revoke': True}})
    d.call('/public/v1/vehicle', expected=403)
    d.call('/public/v1/session', {'token': rotated}, expected=404)
    assert outsider.call('/public/v1/vehicle')['vehicle']['plate'] == other['plate']
    print('PASS ' + prefix + ' anonymous driver: CSRF, token exchange, vehicle binding, idempotent defect/km report, five real photos, metadata removal, sixth/invalid/cross-vehicle upload denied, private download protection')
    print('PASS ' + prefix + ' driver/master/tablet: confirmed mileage history once, reviewed public summary, repair order, parts/pause, rework, times, approval resolves defect; rotation/revocation denies existing sessions and reporting/uploads')
