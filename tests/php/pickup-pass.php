<?php
use OCA\Werkstattmanager\Service\PickupPasses;
require_once __DIR__ . '/../../lib/ThirdPartyAutoload.php';

function pickupSetup(): array {
    $s = new MemoryStore(); $u = new UserSession(); $config = new Config();
    $access = new OCA\Werkstattmanager\Service\Access($u, new Groups(), $config);
    $journal = new OCA\Werkstattmanager\Service\Journal($s, $access);
    $catalog = new OCA\Werkstattmanager\Service\Catalog($s, $access, $journal);
    $session = new Session();
    [,,$company] = companyMailSetup();
    $templates = new OCA\Werkstattmanager\Service\CustomerMailTemplates($s, $access, new OCA\Werkstattmanager\Service\PlanningLedger($s), $journal, $company);
    $passes = new PickupPasses($s, $access, $journal, new Crypto(), new Urls(), $session, $templates);
    $orders = new OCA\Werkstattmanager\Service\Orders($s, $access, $catalog, $journal, null, null, $passes);
    $customer = $catalog->save('customers', ['name'=>'Beispiel GmbH','contact'=>'Erika Beispiel','email'=>'erika@example.invalid']);
    $vehicle = $catalog->save('vehicles', ['kind'=>'customer','customer_id'=>$customer['id'],'plate'=>'B-AB 123','make'=>'Volkswagen','model'=>'Golf']);
    return [$s,$u,$access,$catalog,$journal,$passes,$orders,$vehicle];
}

test('order creates one encrypted revocable pickup pass and public view is minimal', function () {
    [$s,$u,$access,$catalog,$journal,$passes,$orders,$vehicle] = pickupSetup();
    $order = create($orders, $vehicle);
    same(1, count($s->find('pickup_links', ['order_id'=>$order['id']])));
    $admin = $passes->masterView($order['id']);
    same('Erika Beispiel', $admin['customer_name']); same(true, str_contains($admin['url'], '/nextcloud/index.php/apps/werkstattmanager/abholung#'));
    same(true, str_contains($admin['print_title'], 'B-AB 123')); same(true, str_contains($admin['print_body'], 'Erika Beispiel'));
    same(true, str_contains($admin['print_body'], 'WM-' . strtoupper(substr($order['id'], 0, 8))));
    same(true, str_contains($admin['print_body'], 'erst möglich'));
    $token = explode('#', $admin['url'], 2)[1];
    $stored = $s->find('pickup_links', ['order_id'=>$order['id']])[0];
    same(false, str_contains(json_encode($stored), $token));
    $public = $passes->exchange($token);
    same(['customer_name','vehicle','ready'], array_keys($public));
    same('B-AB 123', $public['vehicle']['plate']); same(false, isset($public['vehicle']['vin'])); same(false, $public['ready']);
    same($public, $passes->publicView());
});

test('pickup email opt-in resends same code on ready and collection invalidates it', function () {
    [$s,$u,$access,$catalog,$journal,$passes,$orders,$vehicle] = pickupSetup();
    $order = create($orders, $vehicle); $admin = $passes->masterView($order['id']); $token = explode('#', $admin['url'], 2)[1];
    $passes->action($order['id'], 'send', payload(['version'=>$admin['version']]));
    same(1, count($s->find('deliveries', ['kind'=>'pickup_code'])));
    $delivery = $passes->deliveryData($admin['id'], 'erika@example.invalid', false);
    same('erika@example.invalid', $delivery['customer_email']); same('Erika Beispiel', $delivery['customer_name']);
    same('WM-' . strtoupper(substr($order['id'], 0, 8)), $delivery['order_number']);
    $admin = $passes->masterView($order['id']); $passes->action($order['id'], 'ready', payload(['version'=>$admin['version']]));
    same(1, count($s->find('deliveries', ['kind'=>'pickup_ready']))); same(true, $passes->exchange($token)['ready']);
    $admin = $passes->masterView($order['id']); $passes->action($order['id'], 'collected', payload(['version'=>$admin['version']]));
    rejects(fn()=>$passes->exchange($token), 'pickup_link'); same(true, $passes->masterView($order['id'])['collected']);
});

test('rotating and cancelling revoke old pickup tokens and tablet has no management access', function () {
    [$s,$u,$access,$catalog,$journal,$passes,$orders,$vehicle] = pickupSetup();
    $order = create($orders, $vehicle); $admin = $passes->masterView($order['id']); $old = explode('#', $admin['url'], 2)[1];
    $passes->exchange($old);
    $passes->action($order['id'], 'rotate', payload(['version'=>$admin['version']]));
    rejects(fn()=>$passes->publicView(), 'pickup_link');
    rejects(fn()=>$passes->exchange($old), 'pickup_link'); $new = explode('#', $passes->masterView($order['id'])['url'], 2)[1];
    $u->uid='tablet'; rejects(fn()=>$passes->masterView($order['id']), 'forbidden');
    $u->uid='master'; $current=$s->get('orders',$order['id']); $orders->action($order['id'],'cancel',payload(['version'=>$current['version'],'reason'=>'Kunde abgesagt']));
    rejects(fn()=>$passes->exchange($new), 'pickup_link');
});

test('pickup QR renderer creates a bounded PNG without an external service', function () {
    $png = (new OCA\Werkstattmanager\Service\PickupQrCode())->png('https://werkstatt.example/apps/werkstattmanager/abholung#' . str_repeat('a', 64));
    same("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
    same(true, strlen($png) > 500); same(true, strlen($png) < 2000000);
});

test('appointment pass survives conversion and rotates when the recipient changes', function () {
    [$s,$u,$access,$catalog,$journal,$passes,$orders,$vehicle] = pickupSetup();
    $week=[]; for($i=1;$i<=7;$i++) { $week[$i]=['minutes'=>480,'max_vehicles'=>null,'closed'=>false]; }
    $s->insert('planning',['id'=>OCA\Werkstattmanager\Service\PlanningLedger::ID,'settings'=>['weekdays'=>$week]]);
    $ledger=new OCA\Werkstattmanager\Service\PlanningLedger($s);
    $library=new OCA\Werkstattmanager\Service\ServiceLibrary($s,$access,$journal);
    $book=new OCA\Werkstattmanager\Service\AppointmentBook($s,$access,$catalog,$journal,$library,$ledger,new Config(),$passes);
    $arrival=gmdate('Y-m-d\TH:i:s\Z',time()+3*86400);
    $a=$book->save(appointment($vehicle,['arrival'=>$arrival,'customer_email'=>'erika@example.invalid','reminder'=>true,'notify_ready'=>true]));
    $first=$passes->appointmentDeliveryData($a)['pickup_url']; $token=explode('#',$first,2)[1];
    same(false,$passes->exchange($token)['ready']);
    same(1,count($s->find('deliveries',['appointment_id'=>$a['id'],'kind'=>'confirm'])));
    same(1,count($s->find('deliveries',['appointment_id'=>$a['id'],'kind'=>'reminder'])));
    $order=$book->convert($a['id'],$a['version']); same($first,$passes->masterView($order['id'])['url']);
    $a=$s->get('appointments',$a['id']);
    $a=$book->save(payload(array_replace($a,['stage'=>'ready','notify_ready'=>true])));
    same(true,$passes->exchange($token)['ready']); same(1,count($s->find('deliveries',['appointment_id'=>$a['id'],'kind'=>'ready'])));
    $a=$book->save(payload(array_replace($a,['customer_email'=>'new@example.invalid'])));
    rejects(fn()=>$passes->exchange($token),'pickup_link');
    $replacement=$passes->appointmentDeliveryData($a)['pickup_url']; same(false,$first===$replacement);
    $a=$book->save(payload(array_replace($a,['status'=>'cancelled'])));
    rejects(fn()=>$passes->exchange(explode('#',$replacement,2)[1]),'pickup_link');
});

test('order and appointment ready actions do not queue duplicate notifications', function () {
    [$s,$u,$access,$catalog,$journal,$passes,$orders,$vehicle] = pickupSetup();
    $week=[]; for($i=1;$i<=7;$i++) { $week[$i]=['minutes'=>480,'max_vehicles'=>null,'closed'=>false]; }
    $s->insert('planning',['id'=>OCA\Werkstattmanager\Service\PlanningLedger::ID,'settings'=>['weekdays'=>$week]]);
    $ledger=new OCA\Werkstattmanager\Service\PlanningLedger($s);
    $library=new OCA\Werkstattmanager\Service\ServiceLibrary($s,$access,$journal);
    $book=new OCA\Werkstattmanager\Service\AppointmentBook($s,$access,$catalog,$journal,$library,$ledger,new Config(),$passes);
    $a=$book->save(appointment($vehicle,['arrival'=>gmdate('Y-m-d\TH:i:s\Z',time()+3*86400),'customer_email'=>'erika@example.invalid']));
    $order=$book->convert($a['id'],$a['version']);
    $passes->markReady($order['id']); same(1,count($s->find('deliveries',['kind'=>'pickup_ready'])));
    $a=$s->get('appointments',$a['id']); $book->save(payload(array_replace($a,['stage'=>'ready'])));
    same(0,count($s->find('deliveries',['kind'=>'ready'])));
});

test('changing a booked vehicle invalidates the old pass and refreshes the pending reminder', function () {
    [$s,$u,$access,$catalog,$journal,$passes,$orders,$vehicle] = pickupSetup();
    $week=[]; for($i=1;$i<=7;$i++) { $week[$i]=['minutes'=>480,'max_vehicles'=>null,'closed'=>false]; }
    $s->insert('planning',['id'=>OCA\Werkstattmanager\Service\PlanningLedger::ID,'settings'=>['weekdays'=>$week]]);
    $ledger=new OCA\Werkstattmanager\Service\PlanningLedger($s);
    $library=new OCA\Werkstattmanager\Service\ServiceLibrary($s,$access,$journal);
    $book=new OCA\Werkstattmanager\Service\AppointmentBook($s,$access,$catalog,$journal,$library,$ledger,new Config(),$passes);
    $a=$book->save(appointment($vehicle,['arrival'=>gmdate('Y-m-d\TH:i:s\Z',time()+3*86400),'customer_email'=>'erika@example.invalid','reminder'=>true]));
    $token=explode('#',$passes->appointmentDeliveryData($a)['pickup_url'],2)[1];
    $other=$catalog->save('vehicles',['kind'=>'customer','customer_id'=>$vehicle['customer_id'],'plate'=>'B-NEW 123']);
    $a=$book->save(payload(array_replace($a,['vehicle_id'=>$other['id']])));
    rejects(fn()=>$passes->exchange($token),'pickup_link');
    $fresh=explode('#',$passes->appointmentDeliveryData($a)['pickup_url'],2)[1];
    same('B-NEW 123',$passes->exchange($fresh)['vehicle']['plate']);
    $reminder=$s->find('deliveries',['appointment_id'=>$a['id'],'kind'=>'reminder']);
    same(1,count($reminder)); same($other['id'],$reminder[0]['payload']['vehicle_id']);
});

test('appointment opt-out preserves an unsent manual pickup message', function () {
    [$s,$u,$access,$catalog,$journal,$passes,$orders,$vehicle] = pickupSetup();
    $week=[]; for($i=1;$i<=7;$i++) { $week[$i]=['minutes'=>480,'max_vehicles'=>null,'closed'=>false]; }
    $s->insert('planning',['id'=>OCA\Werkstattmanager\Service\PlanningLedger::ID,'settings'=>['weekdays'=>$week]]);
    $ledger=new OCA\Werkstattmanager\Service\PlanningLedger($s);
    $library=new OCA\Werkstattmanager\Service\ServiceLibrary($s,$access,$journal);
    $book=new OCA\Werkstattmanager\Service\AppointmentBook($s,$access,$catalog,$journal,$library,$ledger,new Config(),$passes);
    $a=$book->save(appointment($vehicle,['arrival'=>gmdate('Y-m-d\TH:i:s\Z',time()+3*86400),'customer_email'=>'erika@example.invalid']));
    $order=$book->convert($a['id'],$a['version']); $info=$passes->masterView($order['id']);
    $passes->action($order['id'],'send',payload(['version'=>$info['version']]));
    $a=$s->get('appointments',$a['id']); $book->save(payload(array_replace($a,['stage'=>'ready'])));
    $initial=$s->find('deliveries',['kind'=>'pickup_code']); same('pending',$initial[0]['status']);
    same(0,count($s->find('deliveries',['kind'=>'ready','status'=>'pending'])));
});
