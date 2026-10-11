"""Real TLS/CalDAV and SMTP in a disposable test network. Never sends to the Internet."""
import base64
import contextlib
import http.client
import http.server
import ipaddress
import json
import os
from pathlib import Path
import socketserver
import ssl
import subprocess
import tempfile
import threading
import urllib.request
import uuid
import time
import datetime
import email
from email import policy


def check(base, passwords, app, occ, run, Client):
    address=base.removeprefix('http://')
    network=json.loads(run('docker','inspect',app))[0]['NetworkSettings']['Networks']
    gateway=next(iter(network.values()))['Gateway']
    if not gateway:
        info=json.loads(run('docker','network','inspect',next(iter(network))))[0]['IPAM']['Config'][0]
        gateway=info.get('Gateway') or str(ipaddress.ip_network(info['Subnet']).network_address+1)
    assert ipaddress.ip_address(gateway).is_private
    messages=[]
    reject_mail=[False]
    reject_calendar=[False]
    class SMTP(socketserver.StreamRequestHandler):
        def handle(self):
            self.wfile.write(b'220 CI SMTP\r\n')
            while True:
                line=self.rfile.readline()
                if not line:break
                verb=line.split(b' ',1)[0].strip().upper()
                if verb in (b'EHLO',b'HELO'):self.wfile.write(b'250 localhost\r\n')
                elif verb in (b'MAIL',b'RCPT',b'RSET',b'NOOP'):self.wfile.write(b'250 OK\r\n')
                elif verb==b'DATA':
                    if reject_mail[0]:
                        self.wfile.write(b'451 Temporary test outage\r\n');continue
                    self.wfile.write(b'354 End with dot\r\n');body=[]
                    while True:
                        row=self.rfile.readline()
                        if row in (b'.\r\n',b''):break
                        body.append(row)
                    messages.append(b''.join(body));self.wfile.write(b'250 queued\r\n')
                elif verb==b'QUIT':self.wfile.write(b'221 bye\r\n');break
                else:self.wfile.write(b'502 unsupported\r\n')
    class TCP(socketserver.ThreadingTCPServer):allow_reuse_address=True
    class Proxy(http.server.BaseHTTPRequestHandler):
        def log_message(self,*args):pass
        def relay(self):
            if reject_calendar[0] and self.path.startswith('/remote.php/dav/'):
                self.send_response(503); self.send_header('Content-Length','0'); self.end_headers(); return
            connection=http.client.HTTPConnection(address,80,timeout=30)
            size=int(self.headers.get('Content-Length','0'))
            assert size<2_000_000
            headers={k:v for k,v in self.headers.items() if k.lower() not in ['connection','transfer-encoding']}
            headers['X-Forwarded-Proto']='https'
            connection.request(self.command,self.path,body=self.rfile.read(size),headers=headers)
            response=connection.getresponse();body=response.read()
            self.send_response(response.status)
            for k,v in response.getheaders():
                if k.lower() not in ['connection','transfer-encoding','content-length']:self.send_header(k,v)
            self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body);connection.close()
        do_GET=do_POST=do_PUT=do_DELETE=do_PROPFIND=do_MKCALENDAR=do_REPORT=do_PROPPATCH=relay
    with tempfile.TemporaryDirectory(prefix='wm-ci-delivery-') as stage:
        cert=Path(stage)/'test.crt';key=Path(stage)/'test.key'
        subprocess.run(['openssl','req','-x509','-nodes','-newkey','rsa:2048','-days','1','-keyout',str(key),'-out',str(cert),'-subj','/CN=Werkstattmanager-CI','-addext','subjectAltName=IP:'+gateway,'-addext','basicConstraints=critical,CA:TRUE'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        os.chmod(key,0o600)
        smtp=TCP((gateway,0),SMTP);proxy=http.server.ThreadingHTTPServer((gateway,0),Proxy)
        ctx=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);ctx.load_cert_chain(cert,key);proxy.socket=ctx.wrap_socket(proxy.socket,server_side=True)
        for server in [smtp,proxy]:threading.Thread(target=server.serve_forever,daemon=True).start()
        public='https://'+gateway+':'+str(proxy.server_port)
        try:
            run('docker','cp',str(cert),app+':/usr/local/share/ca-certificates/wm-ci.crt');run('docker','exec',app,'update-ca-certificates')
            for field,value in [('overwrite.cli.url',public),('overwritehost',gateway+':'+str(proxy.server_port)),('overwriteprotocol','https'),('mail_smtpmode','smtp'),('mail_smtphost',gateway),('mail_smtpport',str(smtp.server_address[1])),('mail_from_address','werkstatt'),('mail_domain','example.invalid')]:occ('config:system:set',field,'--value='+value)
            occ('config:system:set','allow_local_remote_servers','--type=boolean','--value=true')
            occ('config:system:set','trusted_domains','2','--value='+gateway+':'+str(proxy.server_port))
            calendar_pass=uuid.uuid4().hex
            occ('user:add','--password-from-env','ci-calendar',env=dict(os.environ,OC_PASS=calendar_pass))
            opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPSHandler(context=ssl.create_default_context(cafile=cert)))
            def dav(method,path,body=None):
                auth=base64.b64encode(('ci-calendar:'+calendar_pass).encode()).decode()
                with opener.open(urllib.request.Request(public+path,data=body,method=method,headers={'Authorization':'Basic '+auth,'Content-Type':'application/xml'}),timeout=30) as response:return response.read()
            calendar_path='/remote.php/dav/calendars/ci-calendar/werkstatt/'
            dav('MKCALENDAR',calendar_path,b'<?xml version="1.0"?><c:mkcalendar xmlns:c="urn:ietf:params:xml:ns:caldav"/>')
            master=Client(public,'ci-master',passwords['master'],cafile=cert)
            import driver_workflow
            driver_workflow.check(public, passwords, Client, cafile=cert)
            master.call('/api/v1/calendar-settings',{'data':{'calendar_url':public+calendar_path,'calendar_user':'ci-calendar','password':calendar_pass}},method='PUT')
            settings=master.call('/api/v1/calendar-settings');assert settings['has_password'] and 'password' not in settings
            vehicle=master.call('/api/v1/records/vehicles')['items'][0]
            data=dict(vehicle_id=vehicle['id'],title='CI TLS Termin',arrival='2026-10-08T06:00:00Z',work_start='2026-10-08T07:00:00Z',pickup='2026-10-08T13:00:00Z',minutes=90,status='confirmed',customer_email='client@example.invalid',reminder=True,note='INTERNAL-DO-NOT-SEND',checklist={'condition_note':'INTERNAL-DO-NOT-SEND','callback_note':'INTERNAL-DO-NOT-SEND'},request_id=str(uuid.uuid4()))
            a=master.call('/api/v1/appointments',{'data':data})
            # Conversion and a non-customer stage change must not discard the already queued calendar events.
            master.call('/api/v1/appointments/'+a['id']+'/order',{'version':a['version']})
            a=next(x for x in master.call('/api/v1/appointments?from=2026-10-08&to=2026-10-08')['items'] if x['id']==a['id'])
            data.update(id=a['id'],version=a['version'],stage='arrived',request_id=str(uuid.uuid4()))
            a=master.call('/api/v1/appointments',{'data':data})
            script=Path(stage)/'dispatch.php';script.write_text("<?php require '/var/www/html/lib/base.php'; \\OCP\\Server::get(\\OCA\\Werkstattmanager\\Service\\AppointmentDelivery::class)->run();")
            run('docker','cp',str(script),app+':/tmp/wm-dispatch.php')
            def dispatch():run('docker','exec','-u','www-data',app,'php','/tmp/wm-dispatch.php')
            # Exercise the actual Nextcloud cron scheduler, not just the delivery service.
            occ('background:cron')
            run('docker','exec','-u','www-data',app,'php','-f','/var/www/html/cron.php')
            failures=master.call('/api/v1/deliveries')['items']
            actual=[{k:d[k] for k in ['channel','kind','status','last_error']} for d in failures if d['appointment_id']==a['id'] and d['kind']!='reminder']
            if actual:
                print('Delivery diagnostics:',actual)
                script.write_text("<?php require '/var/www/html/lib/base.php'; $rows=\\OCP\\Server::get(\\OCA\\Werkstattmanager\\Db\\Store::class)->find('deliveries',['appointment_id'=>'"+a['id']+"','channel'=>'calendar'],1);try{\\OCP\\Server::get(\\OCA\\Werkstattmanager\\Service\\CalendarDelivery::class)->send($rows[0]['payload'],$rows[0]['kind']);}catch(\\Throwable $e){echo get_class($e).' '.$e->getMessage();}")
                run('docker','cp',str(script),app+':/tmp/wm-cal-probe.php')
                diagnostic=run('docker','exec','-u','www-data',app,'php','/tmp/wm-cal-probe.php')
                for secret in [calendar_pass,*passwords.values()]:diagnostic=diagnostic.replace(secret,'[redacted]')
                print('Calendar diagnostics:',diagnostic)
            for suffix in ['arrival','pickup','work-2026-10-08']:
                ics=dav('GET',calendar_path+'werkstatt-'+a['id']+'-'+suffix+'.ics');assert ics.count(b'BEGIN:VEVENT')==1 and b'METHOD:' not in ics and b'INTERNAL-DO-NOT-SEND' not in ics
            own=[m for m in messages if b'client@example.invalid' in m];assert len(own)==1 and b'INTERNAL-DO-NOT-SEND' not in own[0]
            dispatch();assert len([m for m in messages if b'client@example.invalid' in m])==1
            data.update(id=a['id'],version=a['version'],pickup='2026-10-08T14:00:00Z',request_id=str(uuid.uuid4()))
            a=master.call('/api/v1/appointments',{'data':data});dispatch()
            assert b'20261008T140000Z' in dav('GET',calendar_path+'werkstatt-'+a['id']+'-pickup.ics')
            data.update(version=a['version'],pickup=None,request_id=str(uuid.uuid4()))
            a=master.call('/api/v1/appointments',{'data':data});dispatch()
            try:dav('GET',calendar_path+'werkstatt-'+a['id']+'-pickup.ics');raise AssertionError('Removed pickup remains')
            except urllib.error.HTTPError as e:assert e.code==404
            attachments=[]
            for m in messages:
                parsed=email.message_from_bytes(m,policy=policy.default)
                attachments.extend(part.get_payload(decode=True) for part in parsed.walk() if part.get_filename()=='werkstatttermin.ics')
            assert any(b'METHOD:CANCEL' in value and value.count(b'BEGIN:VEVENT')==1 and ('werkstatt-'+a['id']+'-pickup@').encode() in value for value in attachments)
            data.update(version=a['version'],status='cancelled',request_id=str(uuid.uuid4()))
            master.call('/api/v1/appointments',{'data':data});dispatch()
            listing=dav('PROPFIND',calendar_path,b'<d:propfind xmlns:d="DAV:"><d:prop><d:getetag/></d:prop></d:propfind>')
            # Depth is omitted above: verify deleted resources directly.
            for suffix in ['arrival','pickup','work-2026-10-08']:
                try:dav('GET',calendar_path+'werkstatt-'+a['id']+'-'+suffix+'.ics');raise AssertionError('Cancelled event remains')
                except urllib.error.HTTPError as e:assert e.code==404
            assert len([m for m in messages if b'client@example.invalid' in m])==5
            reject_mail[0]=True
            failed_data=dict(data,id=None,version=None,status='confirmed',customer_email='outage@example.invalid',reminder=False,request_id=str(uuid.uuid4()))
            failed=master.call('/api/v1/appointments',{'data':failed_data});dispatch()
            pending=master.call('/api/v1/deliveries')['items']
            delivery=next(d for d in pending if d['appointment_id']==failed['id'] and d['channel']=='email')
            assert delivery['status']=='uncertain' and delivery['attempts']==1
            dispatch();assert not any(b'outage@example.invalid' in m for m in messages)
            reject_mail[0]=False
            master.call('/api/v1/deliveries/'+delivery['id']+'/retry',{'version':delivery['version']});dispatch()
            assert len([m for m in messages if b'outage@example.invalid' in m])==1
            # Controlled fixture moves the reminder to its 24-hour due boundary; system clock is unchanged.
            tomorrow=datetime.datetime.now(datetime.timezone.utc)+datetime.timedelta(days=2)
            reminder_data=dict(vehicle_id=vehicle['id'],title='CI Reminder',arrival=tomorrow.strftime('%Y-%m-%dT%H:%M:%SZ'),minutes=30,status='confirmed',customer_email='reminder@example.invalid',reminder=True,override_reason='Isolated fixture',request_id=str(uuid.uuid4()))
            reminder=master.call('/api/v1/appointments',{'data':reminder_data});dispatch()
            before=len([m for m in messages if b'reminder@example.invalid' in m]);assert before==1
            fixture=Path(stage)/'fixture.php'
            def fixture_run(code):
                fixture.write_text("<?php require '/var/www/html/lib/base.php'; $s=\\OCP\\Server::get(\\OCA\\Werkstattmanager\\Db\\Store::class);"+code)
                run('docker','cp',str(fixture),app+':/tmp/wm-fixture.php')
                return run('docker','exec','-u','www-data',app,'php','/tmp/wm-fixture.php')
            fixture_run("$a=$s->get('appointments','"+reminder['id']+"');$rows=$s->find('deliveries',['appointment_id'=>$a['id'],'kind'=>'reminder']);assert(count($rows)===1);$d=$rows[0];if($d['next_try']!==strtotime($a['arrival'])-86400)throw new \\RuntimeException('Reminder boundary');$arrival=gmdate('Y-m-d\\TH:i:s\\Z',time()+86400-5);$s->update('appointments',$a,['arrival'=>$arrival]);$p=$d['payload'];$p['arrival']=$arrival;$s->update('deliveries',$d,['payload'=>$p,'next_try'=>time()-5]);")
            dispatch();dispatch();assert len([m for m in messages if b'reminder@example.invalid' in m])==2
            # Revoked reminder must be discarded at dispatch time.
            reminder_data.update(customer_email='revoked@example.invalid',request_id=str(uuid.uuid4()))
            revoked=master.call('/api/v1/appointments',{'data':reminder_data});dispatch()
            fixture_run("$a=$s->get('appointments','"+revoked['id']+"');$s->update('appointments',$a,['reminder'=>false]);foreach($s->find('deliveries',['appointment_id'=>$a['id'],'kind'=>'reminder'])as$d)$s->update('deliveries',$d,['next_try'=>time()-1]);")
            dispatch();assert len([m for m in messages if b'revoked@example.invalid' in m])==1
            # Real CalDAV HTTP outage, bounded backoff and recovery.
            reject_calendar[0]=True
            cal_data=dict(reminder_data,customer_email='',reminder=False,title='CI Calendar outage',request_id=str(uuid.uuid4()))
            cal=master.call('/api/v1/appointments',{'data':cal_data});dispatch()
            failures=[d for d in master.call('/api/v1/deliveries')['items'] if d['appointment_id']==cal['id']]
            assert failures and all(d['status']=='pending' and d['attempts']==1 for d in failures)
            assert all(d['context']['title']=='CI Calendar outage' for d in failures)
            reject_calendar[0]=False
            fixture_run("foreach($s->find('deliveries',['appointment_id'=>'"+cal['id']+"','status'=>'pending'])as$d)$s->update('deliveries',$d,['next_try'=>time()-1]);")
            dispatch();assert b'BEGIN:VEVENT' in dav('GET',calendar_path+'werkstatt-'+cal['id']+'-arrival.ics')
            # Removing credentials blocks delivery; restoring them recovers the queued job.
            master.call('/api/v1/calendar-settings',{'data':{'calendar_url':public+calendar_path,'calendar_user':'ci-calendar','remove_password':True}},method='PUT')
            cal_data.update(title='CI Calendar revoked',request_id=str(uuid.uuid4()))
            revoked_cal=master.call('/api/v1/appointments',{'data':cal_data});dispatch()
            assert any(d['appointment_id']==revoked_cal['id'] and d['attempts']==1 for d in master.call('/api/v1/deliveries')['items'])
            master.call('/api/v1/calendar-settings',{'data':{'calendar_url':public+calendar_path,'calendar_user':'ci-calendar','password':calendar_pass}},method='PUT')
            fixture_run("foreach($s->find('deliveries',['appointment_id'=>'"+revoked_cal['id']+"','status'=>'pending'])as$d)$s->update('deliveries',$d,['next_try'=>time()-1]);")
            dispatch();assert b'BEGIN:VEVENT' in dav('GET',calendar_path+'werkstatt-'+revoked_cal['id']+'-arrival.ics')
            # Customer messages share the actual SMTP queue but ready/deadline send no second calendar invitation.
            customer=master.call('/api/v1/records/customers',{'data':{'name':'CI Mailkunde','contact':'Erika Beispiel','email':'personal@example.invalid'}})
            assert customer['deadline_emails'] is True
            cv=master.call('/api/v1/records/vehicles',{'data':{'kind':'customer','customer_id':customer['id'],'plate':'CI-MAIL'}})
            templates=master.call('/api/v1/mail-templates')
            templates['templates']['ready']['subject']='Abholbereit {fahrzeug}'
            templates['templates']['ready']['body']='Hallo {kundenname}: {fahrzeug}, {termin}, {leistung}. {werkstatt}'
            saved_templates=master.call('/api/v1/mail-templates',{'data':templates},method='PUT')
            master.call('/api/v1/mail-templates',{'data':templates},method='PUT',expected=409)
            arrival=(datetime.datetime.now(datetime.timezone.utc)+datetime.timedelta(days=4)).strftime('%Y-%m-%dT%H:%M:%SZ')
            ready_data=dict(vehicle_id=cv['id'],title='CI Fertigmeldung',arrival=arrival,minutes=15,status='confirmed',customer_email=customer['email'],notify_ready=True,override_reason='Isolated fixture',request_id=str(uuid.uuid4()))
            ready=master.call('/api/v1/appointments',{'data':ready_data});dispatch()
            def mails(to):return [email.message_from_bytes(raw,policy=policy.default) for raw in messages if to.encode() in raw]
            assert len(mails(customer['email']))==1
            ready_data.update(id=ready['id'],version=ready['version'],stage='ready',request_id=str(uuid.uuid4()))
            ready=master.call('/api/v1/appointments',{'data':ready_data});dispatch();dispatch()
            personal=mails(customer['email']);assert len(personal)==2
            ready_message=next(m for m in personal if str(m['Subject'])=='Abholbereit CI-MAIL')
            body=ready_message.get_body(preferencelist=('plain',)).get_content()
            assert 'Erika Beispiel' in body and 'CI-MAIL' in body and 'CI Fertigmeldung' in body and '{kundenname}' not in body
            assert not list(ready_message.iter_attachments())
            ready_data.update(version=ready['version'],stage='arrived',request_id=str(uuid.uuid4()))
            ready=master.call('/api/v1/appointments',{'data':ready_data})
            ready_data.update(version=ready['version'],stage='ready',request_id=str(uuid.uuid4()))
            ready=master.call('/api/v1/appointments',{'data':ready_data});dispatch();assert len(mails(customer['email']))==2
            # Revoke the customer's wish while another ready message is pending.
            revoked_data=dict(ready_data,id=None,version=None,customer_email='ready-revoked@example.invalid',request_id=str(uuid.uuid4()))
            revoked_ready=master.call('/api/v1/appointments',{'data':revoked_data})
            revoked_data.update(id=revoked_ready['id'],version=revoked_ready['version'],notify_ready=False,request_id=str(uuid.uuid4()))
            master.call('/api/v1/appointments',{'data':revoked_data});dispatch()
            assert not any(str(m['Subject']).startswith('Abholbereit') for m in mails('ready-revoked@example.invalid'))
            due=(datetime.date.today()+datetime.timedelta(days=10)).isoformat()
            dl=master.call('/api/v1/records/deadlines',{'data':{'vehicle_id':cv['id'],'title':'HU / TÜV','due_date':due,'repeat_months':24}})
            fixture_run("\\OCP\\Server::get(\\OCA\\Werkstattmanager\\Service\\CustomerReminders::class)->schedule();")
            dispatch();dispatch(); personal=mails(customer['email']);assert len(personal)==3
            dm=next(m for m in personal if str(m['Subject']).startswith('Bitte Werkstatttermin vereinbaren'))
            body=dm.get_body(preferencelist=('plain',)).get_content()
            assert 'Erika Beispiel' in body and 'CI-MAIL' in body and 'HU / TÜV' in body
            assert not list(dm.iter_attachments())
            # A second due deadline is cancelled at delivery after the customer opts out.
            master.call('/api/v1/records/deadlines',{'data':{'vehicle_id':cv['id'],'title':'Inspektion','due_date':due,'repeat_months':12}})
            fixture_run("\\OCP\\Server::get(\\OCA\\Werkstattmanager\\Service\\CustomerReminders::class)->schedule();")
            master.call('/api/v1/records/customers',{'id':customer['id'],'data':dict(customer,deadline_emails=False)})
            dispatch();assert len(mails(customer['email']))==3
            templates['version']=saved_templates['version']
            from_templates=master.call('/api/v1/mail-templates')
            assert from_templates['templates']['ready']['subject']=='Abholbereit {fahrzeug}'
            print('PASS personalized editable SMTP templates; explicit ready once, optout cancellation, customer deadline once and recheck; no ICS attached to ready/deadline')
            print('PASS actual cron scheduler; due reminder once, revoked reminder discarded; real CalDAV outage/backoff/recovery and credential removal/restore; removed pickup single-UID customer cancellation')
            print('PASS real SMTP outage: visible uncertain delivery, no automatic duplicate, explicit retry succeeds')
            print('PASS real TLS/CalDAV/SMTP: trusted isolated HTTPS, three single-UID events, conversion/stage edits, repeatable delivery, update, cancellation and no internal-note leakage')
        finally:
            for server in [smtp,proxy]:server.shutdown();server.server_close()
