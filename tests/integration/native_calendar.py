"""Native read-only calendar provider on a disposable Nextcloud only."""
import base64
import json
from pathlib import Path
import tempfile
import urllib.error
import urllib.request
import uuid


def check(base, passwords, Client, app, run, preserved=None):
    master = Client(base, 'ci-master', passwords['master'])
    vehicle = master.call('/api/v1/records/vehicles', {'data': {'kind': 'fleet', 'plate': 'CI-CALENDAR'}})
    data = {'data': {'vehicle_id': vehicle['id'], 'title': 'CI native calendar',
        'arrival': '2026-11-02T07:00:00Z', 'work_start': '2026-11-02T08:00:00Z',
        'pickup': '2026-11-05T15:00:00Z', 'minutes': 60,
        'allocations': [{'date': '2026-11-02', 'minutes': 60}], 'status': 'confirmed',
        'customer_email': 'calendar-private@example.invalid', 'note': 'INTERNAL-CALENDAR-NOTE',
        'request_id': str(uuid.uuid4())}}
    appointment = master.call('/api/v1/appointments', data)
    draft = dict(data['data'], title='CI DRAFT MUST STAY HIDDEN', status='draft',
        request_id=str(uuid.uuid4()), customer_email='')
    master.call('/api/v1/appointments', {'data': draft})
    midnight = master.call('/api/v1/appointments', {'data': dict(data['data'],
        title='CI midnight calendar', arrival='2026-11-09T22:55:00Z',
        work_start='2026-11-09T22:55:00Z', pickup=None, minutes=30,
        allocations=[{'date': '2026-11-09', 'minutes': 30}], customer_email='',
        request_id=str(uuid.uuid4()))})
    settings = master.call('/api/v1/calendar-settings')
    assert settings['mode'] == 'native' and settings['native_enabled'] is True
    assert settings['calendar_url'] == '' and settings['has_password'] is False

    php = r'''<?php
require '/var/www/html/lib/base.php';
$manager = \OCP\Server::get(\OCP\Calendar\IManager::class);
$master = $manager->getCalendarsForPrincipal('principals/users/ci-master', ['werkstattmanager']);
$tablet = $manager->getCalendarsForPrincipal('principals/users/ci-tablet', ['werkstattmanager']);
$query = $manager->newQuery('principals/users/ci-master');
$query->addSearchCalendar('werkstattmanager');
$query->addType('VEVENT');
$query->setTimerangeStart(new \DateTimeImmutable('2026-11-05T00:00:00Z'));
$query->setTimerangeEnd(new \DateTimeImmutable('2026-11-06T00:00:00Z'));
$events = $manager->searchForPrincipal($query);
$query->setTimerangeStart(new \DateTimeImmutable('2026-11-10T00:00:00+01:00'));
$query->setTimerangeEnd(new \DateTimeImmutable('2026-11-10T00:10:00+01:00'));
$midnight = $manager->searchForPrincipal($query);
echo json_encode(['master' => array_map(fn($c) => [
    'uri' => $c->getUri(), 'name' => $c->getDisplayName(), 'permissions' => $c->getPermissions()
], $master), 'tablet' => count($tablet), 'events' => $events, 'midnight' => $midnight], JSON_THROW_ON_ERROR);
'''
    with tempfile.TemporaryDirectory(prefix='wm-native-calendar-') as directory:
        script = Path(directory) / 'native-calendar.php'
        script.write_text(php)
        run('docker', 'cp', str(script), app + ':/tmp/native-calendar.php')
        output = run('docker', 'exec', '-u', 'www-data', app, 'php', '/tmp/native-calendar.php')
        run('docker', 'exec', app, 'rm', '-f', '/tmp/native-calendar.php')
    result = json.loads(output)
    assert result['master'] == [{'uri': 'werkstattmanager', 'name': 'Werkstatt', 'permissions': 1}]
    assert result['tablet'] == 0 and len(result['events']) == 1
    assert len(result['midnight']) == 2, 'Arrival and work overlapping midnight must remain visible'
    encoded = json.dumps(result, ensure_ascii=False)
    assert appointment['id'] in encoded and 'Abholung: CI-CALENDAR · CI native calendar' in encoded
    assert 'calendar-private@example.invalid' not in encoded and 'INTERNAL-CALENDAR-NOTE' not in encoded
    assert 'CI DRAFT MUST STAY HIDDEN' not in encoded

    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    def dav(method, path, body=b'', expected=(200, 207), user='master'):
        auth = base64.b64encode(('ci-' + user + ':' + passwords[user]).encode()).decode()
        request = urllib.request.Request(base + path, data=body, method=method, headers={
            'Authorization': 'Basic ' + auth,
            'Content-Type': 'application/xml; charset=utf-8',
            'Depth': '1',
        })
        try:
            response = opener.open(request, timeout=30)
        except urllib.error.HTTPError as error:
            response = error
        assert response.status in expected, (method, path, response.status)
        return response.read()
    listing = dav('PROPFIND', '/remote.php/dav/calendars/ci-master/',
        b'<d:propfind xmlns:d="DAV:"><d:prop><d:displayname/></d:prop></d:propfind>')
    assert b'werkstattmanager' in listing and b'Werkstatt' in listing
    # Nextcloud's public app-calendar bridge deliberately prefixes virtual DAV collections.
    event_path = '/remote.php/dav/calendars/ci-master/app-generated--dav-wrapper--werkstattmanager/werkstatt-' + appointment['id'] + '-pickup.ics'
    ics = dav('GET', event_path)
    assert b'BEGIN:VEVENT' in ics and b'CI-CALENDAR' in ics
    assert b'calendar-private@example.invalid' not in ics and b'INTERNAL-CALENDAR-NOTE' not in ics
    dav('PUT', event_path, ics, expected=(403, 405))
    dav('GET', event_path, expected=(403, 404), user='tablet')
    dav('PROPFIND', '/remote.php/dav/calendars/ci-tablet/app-generated--dav-wrapper--werkstattmanager/',
        expected=(403, 404), user='tablet')
    query = b'''<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
      <d:prop><c:calendar-data/></d:prop>
      <c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT">
        <c:time-range start="20261105T000000Z" end="20261106T000000Z"/>
      </c:comp-filter></c:comp-filter></c:filter></c:calendar-query>'''
    collection = event_path.rsplit('/', 1)[0] + '/'
    if preserved and preserved['status'] == 'confirmed':
        legacy_event = dav('GET', collection + 'werkstatt-' + preserved['id'] + '-arrival.ics')
        assert b'CI preserved appointment' in legacy_event and b'CI preserved internal note' not in legacy_event
        print('PASS existing confirmed appointment visible in native calendar after upgrade without copying')
    report = dav('REPORT', collection, query)
    assert b'CI native calendar' in report and b'CI DRAFT MUST STAY HIDDEN' not in report
    updated = master.call('/api/v1/appointments', {'data': dict(data['data'],
        id=appointment['id'], version=appointment['version'], title='CI updated calendar',
        request_id=str(uuid.uuid4()))})
    assert b'CI updated calendar' in dav('GET', event_path)
    master.call('/api/v1/appointments', {'data': dict(data['data'],
        id=updated['id'], version=updated['version'], status='cancelled',
        request_id=str(uuid.uuid4()))})
    dav('GET', event_path, expected=(404,))
    assert b'CI-CALENDAR' not in dav('REPORT', collection, query)
    master.call('/api/v1/appointments', {'data': dict(midnight,
        status='cancelled', request_id=str(uuid.uuid4()))})
    print('PASS native master calendar: zero credentials, role isolation, pickup window, CalDAV read-only, private fields withheld and immediate update/cancellation')

