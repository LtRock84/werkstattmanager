<?php
use OCA\Werkstattmanager\Domain\CustomerMailText;
use OCA\Werkstattmanager\Service\{CustomerMailTemplates, CustomerReminders, Access, Journal, Catalog};
function customerMailSetup(): array {
    [$s,$u,$v,$library,$ledger,$book] = planSetup();
    $config = new Config(); $access = new Access($u,new Groups(),$config); $journal = new Journal($s,$access);
    [,,$company] = companyMailSetup();
    $templates = new CustomerMailTemplates($s,$access,$ledger,$journal,$company);
    return [$s,$u,$book,new Catalog($s,$access,$journal),$templates,new CustomerReminders($s,$templates,$config)];
}
test('plain mail templates reject headers and unknown tokens and never evaluate inserted customer data', function () {
    $templates=CustomerMailText::defaults(); $templates['confirm']['subject']="Test\r\nBcc: x";
    rejects(fn()=>CustomerMailText::validate($templates),'validation');
    $templates=CustomerMailText::defaults(); $templates['confirm']['body']='{password}';
    rejects(fn()=>CustomerMailText::validate($templates),'validation');
    $out=CustomerMailText::render(['subject'=>'{kundenname}','body'=>'{kundenname} / {fahrzeug}'],['kundenname'=>"Name\r\n{werkstatt}",'fahrzeug'=>'TEST','werkstatt'=>'NEVER']);
    same(false,str_contains($out['subject'],"\n")); same(true,str_contains($out['body'],'{werkstatt}')); same(false,str_contains($out['body'],'NEVER'));
    same(255,strlen(CustomerMailText::render(['subject'=>'{kundenname}','body'=>'Text'],['kundenname'=>str_repeat('a',400)])['subject']));
});
test('template settings enforce master rights optimistic versions and German dates', function () {
    [$s,$u,$book,$catalog,$templates]=customerMailSetup(); $initial=$templates->settings(); same(30,$initial['reminder_days']);same(7,count($initial['templates']));
    $saved=$templates->save($initial);same(1,$saved['version']);rejects(fn()=>$templates->save($initial),'conflict');
    foreach(['reception','tablet']as$role){$u->uid=$role;rejects(fn()=>$templates->settings(),'forbidden');rejects(fn()=>$templates->save($saved),'forbidden');}
    $out=$templates->render('confirm',['customer_name'=>'Erika Beispiel','vehicle_label'=>'TEST 12','arrival'=>'2026-10-25T01:30:00Z','title'=>'Inspektion']);
    same(true,str_contains($out['body'],'Erika Beispiel'));same(true,str_contains($out['body'],'25.10.2026 02:30 Uhr'));same(true,str_contains($out['subject'],'TEST 12'));
});
test('new customer reminders are selected while existing disabled settings survive edits and recurring deadlines use completion', function () {
    [$s,$u,$book,$catalog]=customerMailSetup();$c=$catalog->save('customers',['name'=>'Erika','email'=>'test@example.invalid']);same(true,$c['deadline_emails']);
    $c=$catalog->save('customers',['version'=>$c['version'],'name'=>'Erika','deadline_emails'=>false],$c['id']);
    $c=$catalog->save('customers',['version'=>$c['version'],'name'=>'Erika'], $c['id']);same(false,$c['deadline_emails']);
    rejects(fn()=>$catalog->save('customers',['name'=>'Invalid','email'=>'bad']),'validation');
    $v=$catalog->save('vehicles',['kind'=>'customer','plate'=>'TEST','customer_id'=>$c['id']]);
    $d=$catalog->save('deadlines',['vehicle_id'=>$v['id'],'title'=>'Inspektion','last_completed_date'=>'2024-02-29','repeat_months'=>12]);same('2025-02-28',$d['due_date']);
    $catalog->completeDeadline($d['id'],'2026-10-11',1000);
    $next=$s->find('deadlines',['status'=>'open'])[0];same('2026-10-11',$next['last_completed_date']);same('2027-10-11',$next['due_date']);
});
test('ready mail is explicit customer only refreshed before delivery and unique after sending',function(){
    [$s,$u,$book,$catalog]=customerMailSetup();$c=$catalog->save('customers',['name'=>'Erika','email'=>'e@example.invalid']);$v=$catalog->save('vehicles',['kind'=>'customer','plate'=>'TEST','customer_id'=>$c['id']]);
    $a=$book->save(appointment($v,['customer_email'=>$c['email'],'stage'=>'ready']));same(0,count($s->find('deliveries',['kind'=>'ready'])));
    $a=$book->save(payload(array_replace($a,['notify_ready'=>true])));$rows=$s->find('deliveries',['kind'=>'ready']);same(1,count($rows));same('Erika',$rows[0]['payload']['customer_name']);
    $a=$book->save(payload(array_replace($a,['title'=>'Aktuelle Arbeit'])));$row=$s->find('deliveries',['kind'=>'ready'])[0];same('Aktuelle Arbeit',$row['payload']['title']);
    $s->update('deliveries',$row,['status'=>'sent']);$a=$book->save(payload(array_replace($a,['stage'=>'arrived'])));$book->save(payload(array_replace($a,['stage'=>'ready'])));same(1,count($s->find('deliveries',['kind'=>'ready'])));
    $fleet=$catalog->save('vehicles',['kind'=>'fleet','plate'=>'FLEET']);$book->save(appointment($fleet,['stage'=>'ready','notify_ready'=>true,'customer_email'=>'f@example.invalid']));same(1,count($s->find('deliveries',['kind'=>'ready'])));
    rejects(fn()=>$book->save(appointment($v,['notify_ready'=>true])),'validation');
});
test('deadline reminder threshold recipients vehicle binding completion and optout are rechecked', function(){
    [$s,$u,$book,$catalog,$templates,$reminders]=customerMailSetup();$c=$catalog->save('customers',['name'=>'Erika','email'=>'e@example.invalid']);$v=$catalog->save('vehicles',['kind'=>'customer','plate'=>'TEST','customer_id'=>$c['id']]);
    $d=$catalog->save('deadlines',['vehicle_id'=>$v['id'],'title'=>'HU','due_date'=>'2026-11-10','repeat_months'=>24]);
    same(true,$reminders->eligible($d,$v,$c,'2026-10-11'));same(false,$reminders->eligible($d,$v,$c,'2026-10-10'));same(false,$reminders->eligible($d,$v,array_replace($c,['deadline_emails'=>false]),'2026-10-11'));
    $d=$s->update('deadlines',$d,['due_date'=>date('Y-m-d')]);$reminders->schedule();$reminders->schedule();$rows=$s->find('deliveries',['kind'=>'deadline']);same(1,count($rows));$row=$rows[0];same(null,$row['appointment_id']);same(true,$reminders->valid($row['payload']));
    $c=$s->update('customers',$c,['deadline_emails'=>false]);same(false,$reminders->valid($row['payload']));$c=$s->update('customers',$c,['deadline_emails'=>true,'email'=>'new@example.invalid']);same(false,$reminders->valid($row['payload']));
    $c=$s->update('customers',$c,['email'=>'e@example.invalid']);$d=$s->update('deadlines',$d,['status'=>'completed']);same(false,$reminders->valid($row['payload']));
    $km=array_replace($d,['status'=>'open','due_date'=>null,'due_km'=>1000]);same(false,$reminders->eligible($km,array_replace($v,['mileage'=>999]),$c,'2026-10-11'));same(true,$reminders->eligible($km,array_replace($v,['mileage'=>1000]),$c,'2026-10-11'));same(false,$reminders->eligible($km,array_replace($v,['mileage'=>1000,'kind'=>'fleet']),$c,'2026-10-11'));
});
test('ready and deadline provider mails have no calendar invitation attachment',function(){
    [$u,$config,$company,$sender,$providers]=companyMailSetup();$sender->configure(['mode'=>'mail','service'=>'1']);$send=$sender->prepare('e@example.invalid','Fertig','Text');same('submitted',$send());same(false,isset($providers->sent[0]['setAttachments']));
});
