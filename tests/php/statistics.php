<?php
use OCA\Werkstattmanager\Domain\WorkshopReport;
use OCA\Werkstattmanager\Service\Statistics;
function reportFixture(): array {
    return ['vehicles'=>[['id'=>'v','kind'=>'customer','plate'=>'KUNDE'],['id'=>'f','kind'=>'fleet','plate'=>'FLOTTE']], 'employees'=>[['id'=>'e','name'=>'Alex']], 'orders'=>[], 'appointments'=>[], 'intervals'=>[], 'revenues'=>[], 'days'=>[['date'=>'2026-10-09','minutes'=>480,'used'=>120]]];
}
function reportOrder(array $extra=[]): array {
    return $extra+['id'=>'o','vehicle_id'=>'v','title'=>'Bremsen','status'=>'open','minutes'=>60,'planned_start'=>'2026-10-09T06:00:00Z','planning_days'=>[],'completed_at'=>null];
}
test('report does not invent revenues and distinguishes missing from explicit zero', function() {
    $d=reportFixture(); $d['orders']=[reportOrder(),reportOrder(['id'=>'zero'])];
    $d['revenues']=[['source_kind'=>'orders','source_id'=>'zero','planned_net_cents'=>0,'actual_net_cents'=>null,'revenue_date'=>null]];
    $r=WorkshopReport::build($d,'2026-10-09','2026-10-09','all',strtotime('2026-10-09T12:00:00Z'));
    same(2,$r['summary']['planned_jobs']);same(1,$r['summary']['missing_plan']);same(0,$r['summary']['planned_cents']);
});
test('report linked appointment is counted once before and after release and prices migrate by lookup', function() {
    $d=reportFixture();$d['orders']=[reportOrder(['status'=>'draft'])];
    $d['appointments']=[['id'=>'a','order_id'=>'o','vehicle_id'=>'v','title'=>'Termin','status'=>'confirmed','arrival'=>'2026-10-09T06:00:00Z','minutes'=>60,'allocations'=>[['date'=>'2026-10-09','minutes'=>60]]]];
    $d['revenues']=[['source_kind'=>'appointments','source_id'=>'a','planned_net_cents'=>10000,'actual_net_cents'=>null,'revenue_date'=>null]];
    foreach(['draft','open'] as $status){$d['orders'][0]['status']=$status;$r=WorkshopReport::build($d,'2026-10-09','2026-10-09','all',time());same(1,$r['summary']['planned_jobs']);same(10000,$r['summary']['planned_cents']);same(1,count($r['jobs']));}
    $d['orders'][0]['status']='completed';$d['orders'][0]['completed_at']='2026-10-09T09:00:00Z';
    $r=WorkshopReport::build($d,'2026-10-09','2026-10-09','all',time());same(0,$r['summary']['planned_jobs']);same(0,$r['summary']['planned_cents']);same(1,$r['summary']['missing_actual']);
});
test('multiday money split preserves cents across month and arbitrary date windows',function(){
    $d=reportFixture();$d['orders']=[reportOrder(['planning_days'=>[['date'=>'2026-11-01','minutes'=>60],['date'=>'2026-10-31','minutes'=>30]]])];
    $d['revenues']=[['source_kind'=>'orders','source_id'=>'o','planned_net_cents'=>10001,'actual_net_cents'=>null,'revenue_date'=>null]];
    $a=WorkshopReport::build($d,'2026-10-31','2026-10-31','all',time());$b=WorkshopReport::build($d,'2026-11-01','2026-11-01','all',time());
    same(3334,$a['summary']['planned_cents']);same(6667,$b['summary']['planned_cents']);same(10001,$a['summary']['planned_cents']+$b['summary']['planned_cents']);
});
test('actual revenue follows recorded date not planned day, complete jobs exclude forecasts',function(){
    $d=reportFixture();$d['orders']=[reportOrder(['status'=>'completed','completed_at'=>'2026-10-08T08:00:00Z'])];
    $d['revenues']=[['source_kind'=>'orders','source_id'=>'o','planned_net_cents'=>20000,'actual_net_cents'=>18000,'revenue_date'=>'2026-10-09']];
    $r=WorkshopReport::build($d,'2026-10-09','2026-10-09','all',time());same(18000,$r['summary']['actual_cents']);same(0,$r['summary']['planned_cents']);same(0,$r['summary']['completed']);
});
test('Berlin DST boundaries clipped correctly, running intervals use server now, pauses absent',function(){
    $d=reportFixture();$d['orders']=[reportOrder(['status'=>'active','vehicle_id'=>'f'])];
    $d['intervals']=[['order_id'=>'o','employee_id'=>'e','started'=>strtotime('2026-10-24T21:00:00Z'),'ended'=>strtotime('2026-10-26T00:00:00Z')]];
    $r=WorkshopReport::build($d,'2026-10-25','2026-10-25','all',strtotime('2026-10-27T00:00:00Z'));same(90000,$r['summary']['work_seconds']);same(90000,$r['summary']['fleet_seconds']);same(0,$r['summary']['planned_cents']);same(1,$r['employees'][0]['orders']);
    $d['intervals'][0]=['order_id'=>'o','employee_id'=>'e','started'=>strtotime('2026-10-25T09:00:00Z'),'ended'=>null];
    $r=WorkshopReport::build($d,'2026-10-25','2026-10-25','fleet',strtotime('2026-10-25T10:00:00Z'));same(3600,$r['summary']['work_seconds']);same(1,$r['employees'][0]['running']);
    $r=WorkshopReport::build($d,'2026-10-25','2026-10-25','customer',time());same(0,$r['summary']['work_seconds']);
});
test('cancelled drafts and undated work do not inflate money; completed comparison uses full lifetime',function(){
    $d=reportFixture();$d['orders']=[reportOrder(['status'=>'completed','completed_at'=>'2026-10-09T06:00:00Z']),reportOrder(['id'=>'c','status'=>'cancelled']),reportOrder(['id'=>'d','status'=>'draft']),reportOrder(['id'=>'n','planned_start'=>null])];
    $d['intervals']=[['order_id'=>'o','employee_id'=>'e','started'=>strtotime('2026-10-08T08:00:00Z'),'ended'=>strtotime('2026-10-08T09:30:00Z')]];
    $r=WorkshopReport::build($d,'2026-10-09','2026-10-09','all',time());same(2,count($r['jobs']));same(1,$r['summary']['undated_jobs']);same(5400,$r['summary']['completed_work_seconds']);same(0,$r['summary']['work_seconds']);same(60,$r['summary']['completed_planned_minutes']);
});
function statisticsSetup(): array {
    [$s,$u,$v,$lib,$ledger]=planSetup();$a=new \OCA\Werkstattmanager\Service\Access($u,new Groups(),new Config());$j=new \OCA\Werkstattmanager\Service\Journal($s,$a);
    $s->update('vehicles',$s->get('vehicles',$v['id']),['kind'=>'customer']);
    $o=$s->insert('orders',reportOrder(['id'=>str_repeat('a',32),'vehicle_id'=>$v['id']]));
    return [$s,$u,$o,new Statistics($s,$a,$ledger,$j)];
}
test('revenue save rights validation version conflict and audit preserve original amount',function(){
    [$s,$u,$o,$service]=statisticsSetup();$input=['source_kind'=>'orders','source_id'=>$o['id'],'planned_net_cents'=>12345,'reason'=>'Angebot'];
    foreach(['tablet','reception'] as $role){$u->uid=$role;rejects(fn()=>$service->save($input),'forbidden');rejects(fn()=>$service->view('2026-10-09','2026-10-09'),'forbidden');}
    $u->uid='master';$r=$service->save($input);same(12345,$r['planned_net_cents']);
    rejects(fn()=>$service->save($input),'conflict');rejects(fn()=>$service->save(array_replace($input,['version'=>1,'actual_net_cents'=>500,'revenue_date'=>'2026-10-09'])),'validation');
    rejects(fn()=>$service->save(array_replace($input,['version'=>1,'planned_net_cents'=>-1])),'validation');
    $s->update('orders',$s->get('orders',$o['id']),['status'=>'completed','completed_at'=>'2026-10-09T12:00:00Z']);
    $r=$service->save(array_replace($input,['version'=>1,'actual_net_cents'=>0,'revenue_date'=>'2026-10-09','planned_net_cents'=>12000]));same(0,$r['actual_net_cents']);same(2,$r['version']);
    $audit=$s->find('audit',['action'=>'revenue.saved']);same(2,count($audit));same(12345,$audit[1]['details']['before']['planned_net_cents']);
    rejects(fn()=>$service->view('2026-10-09','2026-10-08'),'validation');rejects(fn()=>$service->view('2026-10-09','2028-10-09'),'validation');
});
test('linked appointment valuation is transferred atomically to order and fleet rejects money',function(){
    [$s,$u,$o,$service]=statisticsSetup();$a=$s->insert('appointments',['vehicle_id'=>$o['vehicle_id'],'status'=>'confirmed','order_id'=>$o['id']]);
    $s->insert('revenues',['source_kind'=>'appointments','source_id'=>$a['id'],'vehicle_id'=>$o['vehicle_id'],'planned_net_cents'=>1000,'actual_net_cents'=>null,'revenue_date'=>null]);
    $r=$service->save(['source_kind'=>'orders','source_id'=>$o['id'],'version'=>1,'planned_net_cents'=>2000,'reason'=>'Neuer Umfang']);same('orders',$r['source_kind']);same(1,count($s->find('revenues')));
    $s->update('vehicles',$s->get('vehicles',$o['vehicle_id']),['kind'=>'fleet']);rejects(fn()=>$service->save(['source_kind'=>'orders','source_id'=>$o['id'],'version'=>2,'reason'=>'Flotte']),'state');
});
test('report service loads more than one page with no silent row loss',function(){
    [$s,$u,$o,$service]=statisticsSetup();
    for($i=0;$i<503;$i++){$s->insert('orders',reportOrder(['id'=>str_pad((string)$i,32,'0',STR_PAD_LEFT),'vehicle_id'=>$o['vehicle_id']]));}
    $r=$service->view('2026-10-09','2026-10-09');same(504,$r['summary']['planned_jobs']);same(504,count($r['jobs']));
});
test('financial export and administrative vehicle erasure include the new revenue table',function(){
    [$s,$u,$o,$service]=statisticsSetup();
    $r=$service->save(['source_kind'=>'orders','source_id'=>$o['id'],'planned_net_cents'=>100,'reason'=>'Testangebot']);
    $access=new \OCA\Werkstattmanager\Service\Access($u,new Groups(),new Config());$j=new \OCA\Werkstattmanager\Service\Journal($s,$access);$privacy=new \OCA\Werkstattmanager\Service\Privacy($s,$access,$j);
    same(true,str_contains($privacy->csv('revenues'),'planned_net_cents'));
    $u->uid='tablet';rejects(fn()=>$privacy->csv('revenues'),'forbidden');$u->uid='admin';
    $s->update('orders',$s->get('orders',$o['id']),['status'=>'completed']);$s->update('vehicles',$s->get('vehicles',$o['vehicle_id']),['archived'=>true]);
    same(['erased'=>true],$privacy->eraseVehicle($o['vehicle_id'],$o['vehicle_id']));same([],$s->find('revenues'));rejects(fn()=>$s->get('revenues',$r['id']),'not_found');
});
test('rework plan edits preserve recorded actual values and do not forecast the invoice twice',function(){
    [$s,$u,$o,$service]=statisticsSetup();$s->update('orders',$s->get('orders',$o['id']),['status'=>'completed','completed_at'=>'2026-10-09T12:00:00Z']);
    $r=$service->save(['source_kind'=>'orders','source_id'=>$o['id'],'planned_net_cents'=>10000,'actual_net_cents'=>9000,'revenue_date'=>'2026-10-09','reason'=>'Abrechnung']);
    $s->update('orders',$s->get('orders',$o['id']),['status'=>'open','completed_at'=>null]);
    $r=$service->save(['source_kind'=>'orders','source_id'=>$o['id'],'planned_net_cents'=>11000,'version'=>$r['version'],'reason'=>'Nacharbeit']);same(9000,$r['actual_net_cents']);same('2026-10-09',$r['revenue_date']);
    $report=$service->view('2026-10-09','2026-10-09');same(0,$report['summary']['planned_cents']);same(0,$report['summary']['actual_cents']);
});
