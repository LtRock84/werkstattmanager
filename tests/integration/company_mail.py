from pathlib import Path
"""Company image validation/role boundaries and real optional Mail provider discovery."""
import base64, json, urllib.request, urllib.error, uuid, re

def check(base, passwords, Client, occ, run, app):
    m = Client(base, 'ci-master', passwords['master'])
    tablet = Client(base, 'ci-tablet', passwords['tablet'])
    original = m.call('/public/v1/company')
    assert m.call('/api/v1/company', {'name': 'CI Werkstatt'})['name'] == 'CI Werkstatt'
    tablet.call('/api/v1/company', {'name': 'Denied'}, expected=403)
    tablet.call('/api/v1/mail-settings', expected=403)
    m.call('/api/v1/company', {'name': 'Injection\r\nX'}, expected=400)
    def upload(content, mime, expected):
        boundary = 'wm-' + uuid.uuid4().hex
        data = (f'--{boundary}\r\nContent-Disposition: form-data; name="name"\r\n\r\nCI Werkstatt\r\n--{boundary}\r\nContent-Disposition: form-data; name="logo"; filename="company.png"\r\nContent-Type: {mime}\r\n\r\n').encode() + content + f'\r\n--{boundary}--\r\n'.encode()
        req = urllib.request.Request(base + '/apps/werkstattmanager/api/v1/company', data=data, headers={'requesttoken':m.csrf,'Content-Type':'multipart/form-data; boundary='+boundary})
        try: response = m.opener.open(req, timeout=30)
        except urllib.error.HTTPError as error: response = error
        assert response.status == expected, ('logo upload', response.status)
        return json.loads(response.read())
    # Valid PNG; server decodes and re-encodes, never serves the uploaded bytes blindly.
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4P1PwPwAG7QKpo2YDvAAAAABJRU5ErkJggg==')
    profile = upload(png, 'image/png', 200)
    assert re.fullmatch('[a-f0-9]{64}',profile['logo'])
    response = urllib.request.urlopen(base + '/apps/werkstattmanager/public/v1/company/logo/' + profile['logo'])
    assert response.headers['Content-Type'].startswith('image/png') and response.read().startswith(b'\x89PNG')
    upload(b'<svg onload="alert(1)"></svg>', 'image/png', 400)
    assert m.call('/public/v1/company') == profile
    assert m.call('/api/v1/company', {'name':'CI Werkstatt','remove_logo':True})['logo'] == ''
    try: urllib.request.urlopen(base + '/apps/werkstattmanager/public/v1/company/logo/' + profile['logo'])
    except urllib.error.HTTPError as error: assert error.code == 404
    else: raise AssertionError('Removed logo still publicly available')
    m.call('/api/v1/company', {'name':original['name']})
    print('PASS company profile: normalized name, public-only identity, real image upload/re-encoding, fake SVG rejected, removal and role guards')
    settings = m.call('/api/v1/mail-settings')
    assert settings['selection']['mode']=='system'
    m.call('/api/v1/mail-settings',{'data':{'mode':'mail','service':'foreign'}},expected=400,method='PUT')
    m.call('/api/v1/mail-settings',{'data':{'mode':'system'}},method='PUT')
    print('PASS workshop sender: explicit system default, account selection/admin-only and no foreign-service delegation')
    import os
    if os.environ.get('WM_TEST_MAIL_PACKAGE'):
        def create(user, address, host='127.0.0.1', imap='9', smtp='9'):
            output = occ('mail:account:create-imap',user,'CI Werkstatt',address,host,imap,'none',address,'ci-dummy',host,smtp,'none',address,'ci-dummy')
            return re.search(r'Account (\d+)',output).group(1)
        own = create('ci-master','werkstatt@example.test'); foreign=create('ci-tablet','other@example.test')
        settings=m.call('/api/v1/mail-settings');assert [a['id'] for a in settings['accounts']]==[own]
        selected=m.call('/api/v1/mail-settings',{'data':{'mode':'mail','service':own}},method='PUT')['selection']
        assert selected['address']=='werkstatt@example.test' and selected['owner']=='ci-master'
        m.call('/api/v1/mail-settings',{'data':{'mode':'mail','service':foreign}},expected=400,method='PUT')
        assert m.call('/api/v1/mail-settings')['selection']==selected
        m.call('/api/v1/mail-settings',{'data':{'mode':'system'}},method='PUT')
        print('PASS Nextcloud Mail 5.12.3 real provider: own account discovery/delegation, foreign account rejected, no credential exposure')
        if os.environ.get('WM_TEST_MAIL_TRANSPORT'):
            import tempfile, time, imaplib, email
            network=next(iter(json.loads(run('docker','inspect',app))[0]['NetworkSettings']['Networks']))
            server=app+'-test-mail'
            try:
                run('docker','run','-d','--name',server,'--network',network,'--memory','384m','-e','JAVA_OPTS=-Xmx128m','greenmail/standalone:2.1.8@sha256:d556b8c7df01ba04a0e0f67dacab2c5f75a61207a51774e0654c179b83a671e1')
                address=run('docker','inspect','--format','{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',server)
                deadline=time.monotonic()+60
                while True:
                    try:
                        inbox=imaplib.IMAP4(address,3143);inbox.login('kunde@example.test','ci-dummy');inbox.select('INBOX');break
                    except (OSError,imaplib.IMAP4.error):
                        if time.monotonic()>deadline:raise
                        time.sleep(1)
                sent=imaplib.IMAP4(address,3143);sent.login('test-werkstatt@example.test','ci-dummy');sent.create('Sent');sent.subscribe('Sent');sent.logout()
                account=create('ci-master','test-werkstatt@example.test',server,'3143','3025')
                occ('mail:account:sync',account)
                m.call('/api/v1/mail-settings',{'data':{'mode':'mail','service':account}},method='PUT')
                with tempfile.TemporaryDirectory() as d:
                    script=Path(d)/'send.php'
                    script.write_text(r"<?php require '/var/www/html/lib/base.php'; $send = \OCP\Server::get(\OCA\Werkstattmanager\Service\WorkshopMailer::class)->prepare('kunde@example.test', 'CI Workshop Mail', 'CI no private workshop data', base64_decode('QkVHSU46VkNBTEVOREFSDQpWRVJTSU9OOjIuMA0KUFJPRElEOi0vL0NJLy9FTg0KRU5EOlZDQUxFTkRBUg0K')); echo $send();")
                    run('docker','cp',str(script),app+':/tmp/wm-mail-provider-test.php')
                    assert run('docker','exec','-u','www-data',app,'php','/tmp/wm-mail-provider-test.php').endswith('submitted')
                inbox.select('INBOX');ids=inbox.search(None,'ALL')[1][0].split();assert len(ids)==1
                data=inbox.fetch(ids[0],'(RFC822)')[1][0][1];received=email.message_from_bytes(data)
                assert email.utils.parseaddr(received['From'])[1]=='test-werkstatt@example.test'
                assert received['Subject']=='CI Workshop Mail'
                attachments=[part for part in received.walk() if part.get_filename()=='werkstatttermin.ics'];assert len(attachments)==1 and b'BEGIN:VCALENDAR' in attachments[0].get_payload(decode=True)
                inbox.logout();m.call('/api/v1/mail-settings',{'data':{'mode':'system'}},method='PUT')
                print('PASS actual Nextcloud Mail provider IMAP/SMTP: dedicated account sender, recipient, message and one ICS attachment; no external mail')
            finally:
                run('docker','rm','-f',server)
