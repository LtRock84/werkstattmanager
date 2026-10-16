<?php
/** Optional employee planning never grants or reserves an active work interval. */
declare(strict_types=1);

test('planned employee on creation is visible on tablet without starting time', function () {
    [$s,$u,$a,$c,$j,$o,$v,$e] = setup();
    $request = payload(['vehicle_id'=>$v['id'],'title'=>'Plan test','planned_employee_id'=>$e['id']]);
    $order = $o->create($request);
    same($order, $o->create($request));
    same($e['id'], $order['planned_employee_id']);
    same([], $s->find('active'));
    same([], $s->find('intervals'));
    same(null, $order['last_employee_id']);
    same(1, count($s->find('audit', ['action'=>'order.plan'])));
    $u->uid='tablet';
    same($e['id'], $c->list('orders')['items'][0]['planned_employee_id']);
    same($order['id'], $c->list('orders',0,['employee_id'=>$e['id']])['items'][0]['id']);
});
test('master plans changes clears and safely retries with readable audit references', function () {
    [$s,$u,$a,$c,$j,$o,$v,$e,$f] = setup();
    $order=create($o,$v);
    $request=payload(['version'=>$order['version'],'planned_employee_id'=>$e['id']]);
    $planned=$o->action($order['id'],'plan',$request);
    same($planned,$o->action($order['id'],'plan',$request));
    same('open',$planned['status']);
    rejects(fn()=>$o->action($order['id'],'plan',payload(['version'=>$order['version'],'planned_employee_id'=>$f['id']])),'conflict');
    $changed=$o->action($planned['id'],'plan',payload(['version'=>$planned['version'],'planned_employee_id'=>$f['id']]));
    $entry=$j->withContext($s->find('audit',['action'=>'order.plan']))[1];
    same('A',$entry['context']['previous_employee']['name']);
    same('B',$entry['context']['employee']['name']);
    same(['before_employee_id'=>$e['id'],'after_employee_id'=>$f['id']],$entry['details']);
    $cleared=$o->action($changed['id'],'plan',payload(['version'=>$changed['version'],'planned_employee_id'=>null]));
    same(null,$cleared['planned_employee_id']);
    same([], $s->find('intervals'));
    same(3,count($s->find('audit',['action'=>'order.plan'])));
});
test('planning validates references and is forbidden to tablet and reception', function () {
    [$s,$u,$a,$c,$j,$o,$v,$e] = setup();
    $order=create($o,$v);
    foreach (['tablet','reception'] as $role) {
        $u->uid=$role;
        rejects(fn()=>$o->action($order['id'],'plan',payload(['version'=>$order['version'],'planned_employee_id'=>$e['id']])),'forbidden');
    }
    $u->uid='master';
    rejects(fn()=>$o->action($order['id'],'plan',payload(['version'=>$order['version']])),'validation');
    rejects(fn()=>$o->action($order['id'],'plan',payload(['version'=>$order['version'],'planned_employee_id'=>str_repeat('0',32)])),'not_found');
    $c->save('employees',['version'=>$e['version'],'name'=>'Archived','archived'=>true],$e['id']);
    rejects(fn()=>$o->action($order['id'],'plan',payload(['version'=>$order['version'],'planned_employee_id'=>$e['id']])),'archived');
    rejects(fn()=>$o->create(payload(['vehicle_id'=>$v['id'],'title'=>'Invalid','planned_employee_id'=>$e['id']])),'archived');
    same($order,$s->get('orders',$order['id']));
});
test('colleagues can start a planned order and active work blocks planning edits', function () {
    [$s,$u,$a,$c,$j,$o,$v,$e,$f] = setup();
    $order=$o->create(payload(['vehicle_id'=>$v['id'],'title'=>'Plan takeover','planned_employee_id'=>$e['id']]));
    $u->uid='tablet';
    $active=$o->action($order['id'],'start',payload(['version'=>$order['version'],'employee_id'=>$f['id']]));
    same($f['id'],$active['last_employee_id']);
    same($e['id'],$active['planned_employee_id']);
    same(1,count($s->find('active')));
    $u->uid='master';
    rejects(fn()=>$o->action($active['id'],'plan',payload(['version'=>$active['version'],'planned_employee_id'=>null])),'state');
    $paused=$o->action($active['id'],'pause',payload(['version'=>$active['version'],'employee_id'=>$f['id'],'reason'=>'Pause']));
    $cleared=$o->action($paused['id'],'plan',payload(['version'=>$paused['version'],'planned_employee_id'=>null]));
    same(null,$cleared['planned_employee_id']);
    same('paused',$cleared['status']);
    same(1,count($s->find('intervals')));
});
