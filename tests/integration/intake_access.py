"""Real Nextcloud role grants, intake booking, ranking immediate revocation and user-deletion grant cleanup."""
import os
import secrets
import time
import uuid
import urllib.error


def check(base, passwords, Client, occ):
    m = Client(base, 'ci-master', passwords['master'])
    original = m.call('/api/v1/access-settings')
    settings = {k: [u['id'] for u in v] if k.endswith('_users') else v for k, v in original.items()}
    occ('group:add', 'werkstatt-meister')
    occ('group:add', 'ci-reception')
    password = 'ci-' + secrets.token_urlsafe(32)
    for user in ['ci-assistant', 'ci-director', 'ci-group-reception']:
        occ('user:add', '--password-from-env', user, env=dict(os.environ, OC_PASS=password))
    occ('group:adduser', 'ci-reception', 'ci-group-reception')
    grants = dict(settings, master_users=['ci-director'], reception_users=['ci-assistant'], reception_group='ci-reception')
    m.call('/api/v1/access-settings', {'data': grants}, method='PUT')
    assistant = Client(base, 'ci-assistant', password)
    director = Client(base, 'ci-director', password)
    grouped = Client(base, 'ci-group-reception', password)
    director.call('/api/v1/pricing'); grouped.call('/api/v1/appointments?from=2026-11-02&to=2026-11-08')
    search = m.call('/api/v1/access-users?q=ci-assistant')['items']
    assert any(u['id'] == 'ci-assistant' for u in search)
    assert all(set(u) == {'id', 'name'} for u in search)
    for c in [assistant, director]:
        c.call('/api/v1/access-settings', expected=403)
        c.call('/api/v1/access-users?q=ci', expected=403)
        c.call('/api/v1/access-settings', {'data': grants}, method='PUT', expected=403)
    m.call('/api/v1/access-settings', {'data': dict(grants, reception_users=['does-not-exist'])}, method='PUT', expected=400)
    m.call('/api/v1/access-settings', {'data': dict(grants, reception_group=grants['tablet_group'])}, method='PUT', expected=400)
    assert m.call('/api/v1/access-settings') == dict(original, master_users=[{'id':'ci-director','name':'ci-director'}], reception_users=[{'id':'ci-assistant','name':'ci-assistant'}], reception_group='ci-reception')
    vehicle = m.call('/api/v1/records/vehicles', {'data': dict(kind='fleet', plate='CI-INTAKE', notes='SECRET VEHICLE')})
    service = m.call('/api/v1/services', {'data':dict(title='CI rank operation', category='Prüfung', minutes=30, own_price=12345)})
    line = dict(service_id=service['id'], key=service['seed_key'], title=service['title'], minutes=30, quantity=2, unit='Leistung', steps=['Prüfen'], vehicle_class='car')
    getcount = lambda: next(s['usage_count'] for s in m.call('/api/v1/services')['items'] if s['id'] == service['id'])
    rows = assistant.call('/api/v1/records/vehicles?id='+vehicle['id'])['items']
    assert 'notes' not in rows[0]
    svc = next(s for s in assistant.call('/api/v1/services')['items'] if s['id'] == service['id'])
    assert 'own_price' not in svc and 'seed_snapshot' not in svc
    for path in ['/api/v1/records/'+k for k in ['orders','parts','employees','active','intervals','defects','audit','attachments','outbox','deadlines','mileage']]:
        assistant.call(path, expected=403)
    for path in ['/api/v1/services/x/prices','/api/v1/pricing','/api/v1/deliveries','/api/v1/calendar-settings','/api/v1/settings','/api/v1/export/appointments','/api/v1/attachments/'+'a'*32]:
        assistant.call(path, expected=403)
    assistant.call('/api/v1/records/customers', {'data':dict(name='Forbidden')}, expected=403)
    assistant.call('/api/v1/capacity', {'data':{}}, expected=403)
    assistant.call('/api/v1/orders', {'data':{}}, expected=403)
    oldorder = m.call('/api/v1/records/orders')['items'][0]
    for action in ['start','pause','finish','release','approve','cancel']:
        assistant.call('/api/v1/orders/'+oldorder['id']+'/'+action, {'data':dict(version=oldorder['version'], request_id=str(uuid.uuid4()))}, expected=403)
    assistant.call('/api/v1/actions/part-request', {'data':dict(order_id=oldorder['id'], request_id=str(uuid.uuid4()))}, expected=403)
    payload = dict(vehicle_id=vehicle['id'], title='CI confirmed intake', arrival='2026-11-02T07:00:00Z', minutes=60, service_lines=[line], request_id=str(uuid.uuid4()))
    a = assistant.call('/api/v1/appointments', {'data':payload})
    assert a['status'] == 'confirmed' and getcount() == 1
    assert assistant.call('/api/v1/appointments', {'data':payload}) == a and getcount() == 1
    assistant.call('/api/v1/appointments/'+a['id']+'/order', {'version':a['version']}, expected=403)
    o = m.call('/api/v1/appointments/'+a['id']+'/order', {'version':a['version']})
    assert getcount() == 1
    o = m.call('/api/v1/orders/'+o['id']+'/release', {'data':dict(version=o['version'],request_id=str(uuid.uuid4()))})
    assert getcount() == 1
    current = next(x for x in m.call('/api/v1/appointments?from=2026-11-02&to=2026-11-02')['items'] if x['id'] == a['id'])
    current = m.call('/api/v1/appointments', {'data':dict(current,note='SECRET APPOINTMENT',checklist=dict(condition_note='SECRET CHECKLIST'),request_id=str(uuid.uuid4()))})
    visible = next(x for x in assistant.call('/api/v1/appointments?from=2026-11-02&to=2026-11-02')['items'] if x['id'] == a['id'])
    assert visible['note'] == '' and visible['checklist'] is None
    changed = assistant.call('/api/v1/appointments', {'data':dict(visible,title='CI changed intake', request_id=str(uuid.uuid4()))})
    assert changed['status']=='confirmed' and getcount()==1
    current = next(x for x in m.call('/api/v1/appointments?from=2026-11-02&to=2026-11-02')['items'] if x['id'] == a['id'])
    assert current['note']=='SECRET APPOINTMENT' and current['checklist']['condition_note']=='SECRET CHECKLIST'
    assistant.call('/api/v1/appointments', {'data':dict(changed,override_reason='Forbidden',request_id=str(uuid.uuid4()))}, expected=403)
    assistant.call('/api/v1/appointments', {'data':dict(changed,stage='ready',request_id=str(uuid.uuid4()))}, expected=403)
    cancelled = assistant.call('/api/v1/appointments', {'data':dict(changed,status='cancelled',request_id=str(uuid.uuid4()))})
    assert cancelled['status']=='cancelled' and getcount()==1  # released linked order still counts
    m.call('/api/v1/orders/'+o['id']+'/cancel', {'data':dict(version=o['version'],reason='CI closed',request_id=str(uuid.uuid4()))})
    assert getcount()==0
    direct = m.call('/api/v1/orders', {'data':dict(vehicle_id=vehicle['id'],title='CI direct rank',service_lines=[line,line],request_id=str(uuid.uuid4()))})
    assert getcount()==1
    m.call('/api/v1/orders/'+direct['id']+'/cancel', {'data':dict(version=direct['version'],reason='CI closed',request_id=str(uuid.uuid4()))})
    assert getcount()==0
    m.call('/api/v1/access-settings', {'data':settings}, method='PUT')
    for c in [assistant, director, grouped]: c.call('/api/v1/appointments?from=2026-11-02&to=2026-11-02', expected=403)
    m.call('/api/v1/access-settings', {'data':grants}, method='PUT')
    occ('user:delete', 'ci-director')
    # Nextcloud's CLI APCu cache differs from HTTP; appconfig expires after 3s.
    deadline = time.monotonic() + 6
    while m.call('/api/v1/access-settings')['master_users'] and time.monotonic() < deadline:
        time.sleep(0.25)
    assert m.call('/api/v1/access-settings')['master_users'] == []
    occ('user:add', '--password-from-env', 'ci-director', env=dict(os.environ, OC_PASS=password))
    assert m.call('/api/v1/access-settings')['master_users'] == []
    m.call('/api/v1/access-settings', {'data':settings}, method='PUT')
    for c in [assistant, grouped]: c.call('/api/v1/appointments?from=2026-11-02&to=2026-11-02', expected=403)
    try:
        Client(base, 'ci-director', password)
    except urllib.error.HTTPError as e:
        assert e.code == 403
    else:
        raise AssertionError('Recreated user must not regain app access')
    print('PASS explicit master/reception users, group grants, directory isolation immediate revocation and user-deletion grant cleanup')
    print('PASS reception confirmed booking, edits, cancellation, capacity and work-only redaction; forbidden APIs denied')
    print('PASS SQL service ranking: request retries, quantity, linked order and cancellation never double count')
    return 2
