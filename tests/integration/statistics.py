"""Actual SQL/HTTP reporting, never accepts a productive endpoint."""
import concurrent.futures
import uuid

def check(base, passwords, Client):
    m=Client(base,'ci-master',passwords['master'])
    tablet=Client(base,'ci-tablet',passwords['tablet'])
    report='/api/v1/statistics?from=2026-10-12&to=2026-10-12'
    tablet.call(report,expected=403)
    tablet.call('/api/v1/statistics/revenue',{'data':{}},expected=403)
    customer=m.call('/api/v1/records/customers',{'data':{'name':'CI Umsatzkunde'}})
    vehicle=m.call('/api/v1/records/vehicles',{'data':{'kind':'customer','customer_id':customer['id'],'plate':'CI-REVENUE'}})
    employee=m.call('/api/v1/records/employees',{'data':{'name':'CI Revenue'}})
    a=m.call('/api/v1/appointments',{'data':{'vehicle_id':vehicle['id'],'title':'CI Umsatztermin','arrival':'2026-10-12T06:00:00Z','minutes':15,'status':'confirmed','request_id':str(uuid.uuid4())}})
    val=m.call('/api/v1/statistics/revenue',{'data':{'source_kind':'appointments','source_id':a['id'],'version':0,'planned_net_cents':10001,'reason':'Angebot'}})
    a=next(x for x in m.call('/api/v1/appointments?from=2026-10-12&to=2026-10-12')['items'] if x['id']==a['id'])
    o=m.call('/api/v1/appointments/'+a['id']+'/order',{'version':a['version']})
    before=m.call(report)
    assert before['summary']['planned_cents']==10001
    def action(op,**data):
        row=m.call('/api/v1/records/orders?id='+o['id'])['items'][0]
        return m.call('/api/v1/orders/'+o['id']+'/'+op,{'data':{'version':row['version'],'request_id':str(uuid.uuid4()),**data}})
    action('release')
    after=m.call(report)
    assert after['summary']['planned_cents']==10001
    assert len([j for j in after['jobs'] if j['source_id']==o['id']])==1
    clients=[Client(base,'ci-master',passwords['master']) for _ in range(2)]
    def race(args):
        client,amount=args
        value=client.call('/api/v1/statistics/revenue',{'data':{'source_kind':'orders','source_id':o['id'],'version':val['version'],'planned_net_cents':amount,'reason':'Umfang angepasst'}},expected=(200,409))
        return client.status,value
    with concurrent.futures.ThreadPoolExecutor(2) as pool:
        result=list(pool.map(race,zip(clients,[20000,30000])))
    assert sorted(s for s,v in result)==[200,409]
    winner=next(v for s,v in result if s==200)
    action('start',employee_id=employee['id']);action('finish',employee_id=employee['id'],note='Prüfung');action('approve')
    m.call('/api/v1/statistics/revenue',{'data':{'source_kind':'orders','source_id':o['id'],'version':winner['version'],'planned_net_cents':winner['planned_net_cents'],'actual_net_cents':18000,'revenue_date':'2026-10-12','reason':'Abrechnung'}})
    final=m.call(report)
    assert final['summary']['actual_cents']==18000 and final['summary']['planned_cents']==0
    assert m.call(report+'&scope=fleet')['summary']['actual_cents']==0
    m.call('/api/v1/statistics?from=2026-10-12&to=2026-10-11',expected=400)
    m.call('/api/v1/records/revenues',expected=404)
    tablet.call('/api/v1/export/revenues',expected=403)
    # A confirmed appointment prevents vehicle archival until cancellation.
    current=m.call('/api/v1/records/vehicles?id='+vehicle['id'])['items'][0]
    m.call('/api/v1/records/vehicles',{'id':current['id'],'data':dict(current,archived=True)},expected=409)
    current=m.call('/api/v1/appointments?from=2026-10-12&to=2026-10-12')['items']
    a=next(x for x in current if x['id']==a['id'])
    m.call('/api/v1/appointments',{'data':dict(a,status='cancelled',request_id=str(uuid.uuid4()))})
    current=m.call('/api/v1/records/vehicles?id='+vehicle['id'])['items'][0]
    m.call('/api/v1/records/vehicles',{'id':current['id'],'data':dict(current,archived=True)})
    # Appointment cancellation queues messages; erase deliberately cannot bypass pending delivery.

    print('PASS SQL reporting: master-only money, linked forecasts once, concurrent price edits one winner, actual-date revenue, zero fleet revenue, export/tablet protections')
    return 1
