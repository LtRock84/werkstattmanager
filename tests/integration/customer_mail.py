"""Master/reception/tablet boundaries and current SQL template versions."""
import uuid

def check(base, passwords, Client):
    m=Client(base,'ci-master',passwords['master'])
    tablet=Client(base,'ci-tablet',passwords['tablet'])
    for path,method,data in [('/api/v1/mail-templates','GET',None),('/api/v1/mail-templates','PUT',{'data':{}})]:
        tablet.call(path,data,method=method,expected=403)
    original=m.call('/api/v1/mail-templates')
    bad=dict(original,templates={**original['templates'],'confirm':{**original['templates']['confirm'],'body':'Unknown {secret}'}})
    m.call('/api/v1/mail-templates',{'data':bad},method='PUT',expected=400)
    bad=dict(original,templates={**original['templates'],'confirm':{**original['templates']['confirm'],'subject':'Hello\r\nBcc: injection'}})
    m.call('/api/v1/mail-templates',{'data':bad},method='PUT',expected=400)
    saved=m.call('/api/v1/mail-templates',{'data':dict(original,reminder_days=31)},method='PUT')
    m.call('/api/v1/mail-templates',{'data':original},method='PUT',expected=409)
    assert saved['reminder_days']==31
    m.call('/api/v1/mail-templates',{'data':dict(original,version=saved['version'])},method='PUT')
    customer=m.call('/api/v1/records/customers',{'data':{'name':'CI Reminder Rights','email':'rights@example.invalid'}})
    assert customer['deadline_emails'] is True
    customer=m.call('/api/v1/records/customers',{'id':customer['id'],'data':dict(customer,deadline_emails=False)})
    assert customer['deadline_emails'] is False
    vehicle=m.call('/api/v1/records/vehicles',{'data':{'kind':'customer','customer_id':customer['id'],'plate':'CI-DEADLINE'}})
    deadline=m.call('/api/v1/records/deadlines',{'data':{'vehicle_id':vehicle['id'],'title':'HU','last_completed_date':'2024-02-29','repeat_months':24}})
    assert deadline['due_date']=='2026-02-28'
    tablet.call('/api/v1/records/deadlines',{'data':dict(deadline)},expected=403)
    print('PASS SQL customer-mail settings: master authorization, header/token validation, stale versions, existing preference preservation and leap-year deadline computation')
