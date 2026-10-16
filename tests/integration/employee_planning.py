"""Real SQL/API coverage for optional planning, separate from active worker ownership."""
import uuid


def check(base, passwords, Client):
    master = Client(base, 'ci-master', passwords['master'])
    tablet = Client(base, 'ci-tablet', passwords['tablet'])
    vehicle = master.call('/api/v1/records/vehicles', {'data': {'kind': 'fleet', 'plate': 'CI-PLAN'}})
    employees = [master.call('/api/v1/records/employees', {'data': {'name': name}}) for name in ['CI Planned Alex', 'CI Actual Robin']]

    def action(client, order, operation, expected=200, **values):
        return client.call('/api/v1/orders/' + order['id'] + '/' + operation,
            {'data': dict(version=order['version'], request_id=str(uuid.uuid4()), **values)}, expected=expected)

    order = master.call('/api/v1/orders', {'data': dict(vehicle_id=vehicle['id'], title='CI Optional planning',
        planned_employee_id=employees[0]['id'], status='open', request_id=str(uuid.uuid4()))})
    hidden = master.call('/api/v1/orders', {'data': dict(vehicle_id=vehicle['id'], title='CI Hidden planning',
        planned_employee_id=employees[0]['id'], status='draft', request_id=str(uuid.uuid4()))})
    assert master.call('/api/v1/records/intervals?order_id=' + order['id'])['items'] == []
    assert not any(a['order_id'] == order['id'] for a in master.call('/api/v1/records/active')['items'])
    visible = tablet.call('/api/v1/records/orders?employee_id=' + employees[0]['id'])['items']
    assert [o['id'] for o in visible] == [order['id']]
    assert visible[0]['planned_employee_id'] == employees[0]['id']
    assert hidden['id'] in {o['id'] for o in master.call('/api/v1/records/orders?employee_id=' + employees[0]['id'])['items']}
    action(tablet, order, 'plan', 403, planned_employee_id=employees[1]['id'])
    action(master, order, 'plan', 404, planned_employee_id='0' * 32)
    changed = action(master, order, 'plan', planned_employee_id=employees[1]['id'])
    action(master, order, 'plan', 409, planned_employee_id=None)
    request = {'data': dict(version=changed['version'], request_id=str(uuid.uuid4()), planned_employee_id=employees[0]['id'])}
    planned = master.call('/api/v1/orders/' + order['id'] + '/plan', request)
    assert master.call('/api/v1/orders/' + order['id'] + '/plan', request) == planned
    active = action(tablet, planned, 'start', employee_id=employees[1]['id'])
    assert active['last_employee_id'] == employees[1]['id']
    assert active['planned_employee_id'] == employees[0]['id']
    action(master, active, 'plan', 409, planned_employee_id=None)
    paused = action(tablet, active, 'pause', employee_id=employees[1]['id'], reason='CI plan takeover')
    cleared = action(master, paused, 'plan', planned_employee_id=None)
    assert cleared['planned_employee_id'] is None and cleared['status'] == 'paused'
    assert len(master.call('/api/v1/records/intervals?order_id=' + order['id'])['items']) == 1
    current_employee = master.call('/api/v1/records/employees?id=' + employees[0]['id'])['items'][0]
    master.call('/api/v1/records/employees?id=' + current_employee['id'], {'data': dict(current_employee, archived=True)})
    action(master, cleared, 'plan', 409, planned_employee_id=employees[0]['id'])
    entries = master.call('/api/v1/records/audit?order_id=' + order['id'])['items']
    changes = [e for e in entries if e['action'] == 'order.plan']
    assert len(changes) == 4
    assert all(set(e['details']) == {'before_employee_id', 'after_employee_id'} for e in changes)
    print('PASS optional employee planning: SQL filter/roles/draft privacy, conflicts/retry, colleague takeover, clear/archival and audit')
    return 2
