<?php
use OCA\Werkstattmanager\Domain\{Pricing, AppointmentCalendar, CatalogueUpdate};
use OCA\Werkstattmanager\Service\{ServiceLibrary, PlanningLedger, AppointmentBook, Orders, Access, Catalog, Journal};
function planSetup(?Config $config = null): array
{
    [$s, $u, $access, $c, $j, $o, $v] = setup();
    $week = [];
    for ($i = 1; $i <= 7; $i++) {
        $week[$i] = ['minutes' => $i <= 5 ? 480 : 0, 'max_vehicles' => null, 'closed' => $i >= 6];
    }
    $s->insert('planning', ['id' => PlanningLedger::ID, 'settings' => ['weekdays' => $week]]);
    $lib = new ServiceLibrary($s, $access, $j);
    $ledger = new PlanningLedger($s);
    $book = new AppointmentBook($s, $access, $c, $j, $lib, $ledger, $config ?? new Config());
    $o = new Orders($s, $access, $c, $j, $lib, $ledger);
    return [$s, $u, $v, $lib, $ledger, $book, $o];
}
function appointment(array $v, array $extra = []): array
{
    return payload(
        $extra + [
            'vehicle_id' => $v['id'],
            'title' => 'Ölwechsel',
            'arrival' => '2026-10-06T06:00:00Z',
            'minutes' => 60,
            'status' => 'confirmed',
        ],
    );
}
test('catalogue field merge updates untouched content but preserves edits prices preferences and own time', function () {
    $baseline = ['title' => 'Alt', 'description' => 'Alttext', 'minutes' => 45, 'time_kind' => 'research', 'favorite' => false, 'own_price' => null];
    $incoming = array_replace($baseline, ['title' => 'Neu', 'description' => 'Neutext', 'minutes' => 60, 'favorite' => true]);
    $row = $baseline + ['version' => 2, 'seed_snapshot' => $baseline];
    $row['description'] = 'Eigener Text'; $row['favorite'] = true; $row['own_price'] = 10000; $row['minutes'] = 90; $row['time_kind'] = 'own';
    $changes = CatalogueUpdate::changes($row, $incoming);
    same('Neu', $changes['title']); same(false, isset($changes['description'])); same(false, isset($changes['minutes'])); same(false, isset($changes['own_price'])); same(false, isset($changes['favorite']));
    $updated = array_replace($row, $changes); same([], CatalogueUpdate::changes($updated, $incoming));
    same('Neu', CatalogueUpdate::changes($baseline + ['version' => 1], $incoming)['title']);
    same(['seed_snapshot' => $incoming], CatalogueUpdate::changes($baseline + ['version' => 3], $incoming));
});
test(
    'catalogue includes 295 distinct operations without variants as independent services',
    function () {
        $data = json_decode(
            file_get_contents(__DIR__ . '/../../lib/Data/services.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        )['services'];
        same(295, count($data));
        same(295, count(array_unique(array_column($data, 'key'))));
        same(15, count(array_unique(array_column($data, 'category'))));
        foreach ($data as $s) {
            if ($s['minutes'] !== null) {
                same('research', $s['time_kind']);
                same(true, str_starts_with($s['time_source'], 'https://'));
            }
        }
    },
);
test('independent calculator result preserves markups material and quantity', function () {
    $p = [
        'costs' => ['personnel' => 18000000, 'rent' => 6000000],
        'billable_hours' => 3000,
        'profit_percent' => 20,
        'material_percent' => 25,
        'vat_percent' => 19,
    ];
    $r = Pricing::calculate($p, [['minutes' => 90, 'quantity' => 1, 'material_cents' => 6000]]);
    same(8000, $r['cost_rate_cents']);
    same(9600, $r['rate_cents']);
    same(21900, $r['net_cents']);
    same(4161, $r['vat_cents']);
    same(26061, $r['gross_cents']);
    $r = Pricing::calculate($p, [
        ['minutes' => 90, 'quantity' => 2, 'material_cents' => 6000, 'external_cents' => 1000],
    ]);
    same(45800, $r['net_cents']);
    same(54502, $r['gross_cents']);
    rejects(fn() => Pricing::calculate($p + [], [['minutes' => null]]), 'validation');
});
test(
    'benchmark separates scopes price types and duplicates and uses regional fallback',
    function () {
        $base = [
            'eligible' => true,
            'vat' => 19,
            'gross_cents' => 3000,
            'valid_until' => null,
            'valid_from' => null,
            'scope' => '4 Räder',
            'material' => 'ohne',
            'unit' => 'Satz',
            'vehicle_class' => 'car',
            'segment' => 'frei',
            'price_kind' => 'fixed',
            'region' => 'Berlin',
            'checked_date' => '2026-10-05',
            'seed_key' => 'x',
            'undated' => true,
        ];
        $prices = [];
        foreach ([3000, 3500, 4000, 4500, 5000] as $i => $price) {
            $prices[] = array_replace($base, [
                'provider' => 'Betrieb' . $i,
                'gross_cents' => $price,
                'region' => $i === 4 ? 'Sachsen' : 'Berlin',
            ]);
        }
        $prices[] = $prices[0];
        $prices[] = array_replace($prices[0], ['price_kind' => 'starting']);
        $prices[] = array_replace($prices[0], [
            'eligible' => false,
            'gross_cents' => 1,
            'provider' => 'Unklar',
        ]);
        $g = Pricing::benchmark($prices, '2026-10-05');
        same(2, count($g));
        same(5, $g[0]['count']);
        same('Ostdeutschland', $g[0]['region']);
        same(4000, $g[0]['mean']);
        same(4000, $g[0]['median']);
        same('individual', $g[1]['quality']);
        $small = Pricing::benchmark(array_slice($prices, 0, 3), '2026-10-05')[0];
        same('limited', $small['quality']);
        same(null, $small['mean']);
        same(3500, $small['median']);
        same(
            [],
            Pricing::benchmark(
                [array_replace($base, ['provider' => 'old', 'valid_until' => '2026-09-01'])],
                '2026-10-05',
            ),
        );
    },
);
test('appointment idempotency drafts capacity override and multiday sums', function () {
    [$s, $u, $v, $lib, $ledger, $book] = planSetup();
    $input = appointment($v);
    $a = $book->save($input);
    same($a, $book->save($input));
    same(1, count($s->find('appointments')));
    same(60, $ledger->days('2026-10-06', '2026-10-06')[0]['used']);
    $draft = $book->save(appointment($v, ['status' => 'draft', 'minutes' => 500]));
    same(60, $ledger->days('2026-10-06', '2026-10-06')[0]['used']);
    same([], $s->find('deliveries', ['appointment_id' => $draft['id']]));
    rejects(fn() => $book->save(appointment($v, ['minutes' => 450])), 'overbooked');
    same(2, count($s->find('appointments')));
    $book->save(
        appointment($v, ['minutes' => 450, 'override_reason' => 'Zusatzschicht vereinbart']),
    );
    same(510, $ledger->days('2026-10-06', '2026-10-06')[0]['used']);
    rejects(
        fn() => $ledger->allocations([['date' => '2026-10-07', 'minutes' => 30]], 60, null),
        'validation',
    );
    rejects(fn() => $ledger->days('', '2026-10-07'), 'validation');
});
test(
    'one linked draft order copied once preserves snapshots and counts capacity once',
    function () {
        [$s, $u, $v, $lib, $ledger, $book, $o] = planSetup();
        $svc = $lib->save([
            'title' => 'Eigene Kontrolle',
            'category' => 'Prüfung',
            'minutes' => 60,
            'steps' => ['Kontrollieren'],
        ]);
        $line = [
            'service_id' => $svc['id'],
            'key' => $svc['seed_key'],
            'title' => 'Eigene Kontrolle',
            'minutes' => 60,
            'quantity' => 2,
            'steps' => ['Kontrollieren'],
            'own_price' => 999,
            'secret' => 'never',
        ];
        $a = $book->save(appointment($v, ['service_lines' => [$line]]));
        $order = $book->convert($a['id'], $a['version']);
        same($order, $book->convert($a['id'], $a['version']));
        same('draft', $order['status']);
        same(120, $order['minutes']);
        same(false, isset($order['service_lines'][0]['own_price']));
        same(120, $ledger->days('2026-10-06', '2026-10-06')[0]['used']);
        $lib->save(array_replace($svc, ['title' => 'Geändert', 'minutes' => 90]));
        same('Eigene Kontrolle', $s->get('orders', $order['id'])['service_lines'][0]['title']);
        $released = $o->action($order['id'], 'release', payload(['version' => $order['version']]));
        same('open', $released['status']);
        same(120, $ledger->days('2026-10-06', '2026-10-06')[0]['used']);
    },
);

test('master-only planning service and calculation APIs reject tablet roles', function () {
    [$s, $u, $v, $lib, $ledger, $book] = planSetup();
    $u->uid = 'tablet';
    rejects(fn() => $lib->list(), 'forbidden');
    rejects(fn() => $lib->prices('x'), 'forbidden');
    rejects(fn() => $lib->profile(), 'forbidden');
    rejects(fn() => $book->view('2026-10-06', '2026-10-07'), 'forbidden');
    rejects(fn() => $book->save(appointment($v)), 'forbidden');
    rejects(fn() => $book->deliveries(), 'forbidden');
});
test('appointment dates and state updates require explicit valid transitions', function () {
    [$s, $u, $v, $lib, $ledger, $book] = planSetup();
    rejects(fn() => $book->save(appointment($v, ['pickup' => '2026-10-06T05:00:00Z'])), 'schedule');
    $a = $book->save(appointment($v));
    rejects(
        fn() => $book->save(
            appointment($v, ['id' => $a['id'], 'version' => $a['version'], 'status' => 'draft']),
        ),
        'state',
    );
    $cancel = $book->save(
        appointment($v, ['id' => $a['id'], 'version' => $a['version'], 'status' => 'cancelled']),
    );
    same(0, $ledger->days('2026-10-06', '2026-10-06')[0]['used']);
    rejects(
        fn() => $book->save(
            appointment($v, ['id' => $cancel['id'], 'version' => $cancel['version']]),
        ),
        'state',
    );
});
test('removed pickup queues isolated cancellation and delivery list provides safe context', function () {
    [$s, $u, $v, $lib, $ledger, $book] = planSetup();
    $a = $book->save(appointment($v, ['customer_email' => 'client@example.invalid', 'pickup' => '2026-10-06T15:00:00Z']));
    $changed = $book->save(appointment($v, ['id' => $a['id'], 'version' => $a['version'], 'customer_email' => 'client@example.invalid', 'pickup' => null]));
    $rows = $s->find('deliveries', ['appointment_id' => $a['id'], 'kind' => 'pickup_cancel']);
    same(1, count($rows)); same($a['pickup'], $rows[0]['payload']['pickup']); same($changed['version'], $rows[0]['payload']['version']);
    $ics = AppointmentCalendar::build($rows[0]['payload'], 'cancel', only: 'pickup');
    same(1, substr_count($ics, 'BEGIN:VEVENT')); same(true, str_contains($ics, 'METHOD:CANCEL')); same(false, str_contains($ics, '-arrival@'));
    $list = $book->deliveries()['items']; same(false, isset($list[0]['payload'])); same('Ölwechsel', $list[0]['context']['title']);
});
test('ICS escapes injection folds UTF8 and gives CalDAV one stable UID per resource', function () {
    $a = [
        'id' => 'abc',
        'version' => 3,
        'title' => "Öl;\nUID:attack",
        'arrival' => '2026-10-25T00:30:00Z',
        'pickup' => '2026-10-25T02:30:00Z',
        'work_start' => null,
        'allocations' => [['date' => '2026-10-26', 'minutes' => 90]],
        'vehicle_label' => 'CI-1',
    ];
    $ics = AppointmentCalendar::build(
        $a,
        'confirm',
        true,
        strtotime('2026-10-05T10:00:00Z'),
        'work-2026-10-26',
    );
    same(1, substr_count($ics, 'BEGIN:VEVENT'));
    same(true, str_contains($ics, 'DTSTART;VALUE=DATE:20261026'));
    same(false, str_contains($ics, "\r\nUID:attack"));
    same(true, str_contains($ics, 'SEQUENCE:3'));
    same(true, str_contains(AppointmentCalendar::build($a, 'cancel'), 'METHOD:CANCEL'));
});


test('archived catalogue snapshots stay editable and allocation dates stay plausible', function () {
    [$s, $u, $v, $lib, $ledger, $book] = planSetup();
    $service = $lib->save(['title' => 'Kontrolle', 'category' => 'Prüfung', 'minutes' => 30, 'steps' => ['Prüfen']]);
    $line = ['service_id' => $service['id'], 'title' => 'Kontrolle', 'minutes' => 30, 'quantity' => 1];
    $a = $book->save(appointment($v, ['service_lines' => [$line]]));
    $lib->save(array_replace($service, ['archived' => true]));
    $edited = $book->save(appointment($v, ['id' => $a['id'], 'version' => $a['version'], 'service_lines' => [$line], 'stage' => 'arrived']));
    same('arrived', $edited['stage']);
    rejects(fn() => $lib->lines([$line]), 'archived');
    rejects(fn() => $book->save(appointment($v, ['allocations' => [['date' => '2026-10-05', 'minutes' => 60]]])), 'validation');
    rejects(fn() => $book->save(appointment($v, ['pickup' => '2026-10-06T18:00:00Z', 'allocations' => [['date' => '2026-10-07', 'minutes' => 60]]])), 'validation');
    rejects(fn() => $book->save(appointment($v, ['work_start' => '2026-10-07T06:00:00Z', 'allocations' => [['date' => '2026-10-06', 'minutes' => 60]]])), 'validation');
});

test('reception checklist validates fields preserves old clients and isolates internal data', function () {
    [$s, $u, $v, $lib, $ledger, $book, $orders] = planSetup();
    $input = appointment($v, ['checklist' => ['key_received' => true, 'mileage' => 12345, 'condition_note' => 'INTERNAL DAMAGE', 'callback_note' => 'INTERNAL CALLBACK']]);
    $a = $book->save($input); same($a, $book->save($input)); same(true, $a['checklist']['key_received']); same(12345, $a['checklist']['mileage']);
    $count = count($s->rows['deliveries'] ?? []);
    $a = $book->save(appointment($v, ['id' => $a['id'], 'version' => $a['version'], 'stage' => 'arrived']));
    same('INTERNAL CALLBACK', $a['checklist']['callback_note']); same($count, count($s->rows['deliveries'] ?? []));
    $ics = \OCA\Werkstattmanager\Domain\AppointmentCalendar::build($a + ['vehicle_label' => 'TEST-1'], 'confirm');
    same(false, str_contains($ics, 'INTERNAL'));
    rejects(fn () => $book->save(appointment($v, ['id' => $a['id'], 'version' => 1])), 'conflict');
    $order = $book->convert($a['id'], $a['version']); same(false, isset($order['checklist']));
    $a = $s->get('appointments', $a['id']);
    $cleared = $book->save(appointment($v, ['id' => $a['id'], 'version' => $a['version'], 'checklist' => []])); same(false, $cleared['checklist']['key_received']); same('', $cleared['checklist']['callback_note']);
    $u->uid = 'tablet'; rejects(fn () => $book->save(appointment($v, ['checklist' => []])), 'forbidden');
});
test('reception checklist rejects unknown keys wrong types ranges and oversized notes', function () {
    foreach ([['unknown' => true], ['key_received' => 'true'], ['mileage' => -1], ['callback_note' => []], ['condition_note' => str_repeat('x', 2001)], 'wrong'] as $invalid) {
        rejects(fn () => \OCA\Werkstattmanager\Domain\ReceptionChecklist::validate($invalid), 'validation');
    }
});

test('additional master grants reception membership precedence and revocation', function () {
    $session = new UserSession(); $config = new Config();
    $access = new Access($session, new Groups(), $config);
    $session->uid = 'assistant'; $config->values['reception_users'] = '["assistant"]'; same('reception', $access->role()); $access->appointments(); rejects(fn () => $access->master(), 'forbidden'); rejects(fn () => $access->workshop(), 'forbidden');
    $config->values['master_users'] = '["assistant"]'; same('master', $access->role()); same(true, $access->isMaster('assistant'));
    $config->values['master_users'] = '[]'; $config->values['reception_users'] = '[]'; rejects(fn () => $access->role(), 'forbidden');
    $session->uid = 'reception'; same('reception', $access->role()); $session->uid = 'admin'; same('master', $access->role());
});
test('reception books confirmed appointments but cannot touch workshop pricing capacity or records', function () {
    [$s, $u, $v, $lib, $ledger, $book, $orders] = planSetup();
    $catalog = new Catalog($s, new Access($u, new Groups(), new Config()), new Journal($s, new Access($u, new Groups(), new Config())));
    $svc = $lib->save(['title' => 'Kontrolle', 'category' => 'Prüfung', 'minutes' => 30, 'own_price' => 10000]);
    $v = $s->update('vehicles', $v, ['notes' => 'SECRET']);
    $order = $orders->create(payload(['vehicle_id' => $v['id'], 'title' => 'Testauftrag']));
    $u->uid = 'reception';
    $input = appointment($v); unset($input['status']); $a = $book->save($input); same('confirmed', $a['status']); same($a, $book->save($input)); same(60, $book->view('2026-10-06','2026-10-06')['days'][0]['used']);
    same(false, isset($catalog->list('vehicles')['items'][0]['notes'])); same(false, isset($lib->list()['items'][0]['own_price'])); same(false, isset($lib->list()['items'][0]['seed_snapshot']));
    foreach (['orders','intervals','active','parts','defects','attachments','audit','outbox','mileage','employees'] as $kind) rejects(fn () => $catalog->list($kind), 'forbidden');
    rejects(fn () => $catalog->save('customers', ['name' => 'Forbidden']), 'forbidden'); rejects(fn () => $catalog->visibleOrder($order['id']), 'forbidden');
    rejects(fn () => $orders->create(payload(['vehicle_id' => $v['id'], 'title' => 'Test'])), 'forbidden');
    foreach (['start','pause','finish','cancel','approve','release'] as $action) rejects(fn () => $orders->action($order['id'], $action, payload(['version' => $order['version']])), 'forbidden');
    rejects(fn () => $orders->requestPart($order['id'], payload([])), 'forbidden');
    rejects(fn () => $lib->save(['title' => 'Test']), 'forbidden'); rejects(fn () => $lib->prices('test'), 'forbidden'); rejects(fn () => $lib->calculate([]), 'forbidden');
    rejects(fn () => $book->convert($a['id'], $a['version']), 'forbidden'); rejects(fn () => $book->capacity([]), 'forbidden'); rejects(fn () => $book->deliveries(), 'forbidden');
    rejects(fn () => $book->save(appointment($v, ['minutes' => 450])), 'overbooked'); rejects(fn () => $book->save(appointment($v, ['minutes' => 450, 'override_reason' => 'Forbidden'])), 'forbidden');
});
test('reception cannot read or change internal checklist note and workshop state', function () {
    [$s, $u, $v, $lib, $ledger, $book] = planSetup();
    $a = $book->save(appointment($v, ['note' => 'SECRET', 'checklist' => ['condition_note' => 'SECRET'], 'stage' => 'arrived']));
    $u->uid = 'reception'; $view = $book->view('2026-10-06','2026-10-06')['items'][0]; same('', $view['note']); same(null, $view['checklist']);
    $result = $book->save(appointment($v, ['id' => $a['id'], 'version' => $a['version'], 'stage' => 'arrived', 'note' => 'ATTACK', 'checklist' => []])); same('', $result['note']); same(null, $result['checklist']);
    $stored = $s->get('appointments',$a['id']); same('SECRET', $stored['note']); same('SECRET', $stored['checklist']['condition_note']);
    rejects(fn () => $book->save(appointment($v, ['id' => $stored['id'], 'version' => $stored['version'], 'stage' => 'ready'])), 'forbidden');
    $result = $book->save(appointment($v, ['id' => $stored['id'], 'version' => $stored['version'], 'stage' => 'arrived', 'status' => 'cancelled'])); same('cancelled',$result['status']);
});
test('service ranking counts committed occurrences once and skips drafts cancellations quantity and repeated requests', function () {
    [$s, $u, $v, $lib, $ledger, $book, $orders] = planSetup();
    $svc = $lib->save(['title' => 'Kontrolle', 'category' => 'Prüfung', 'minutes' => 30]);
    $line = ['service_id' => $svc['id'], 'title' => $svc['title'], 'minutes' => 30, 'quantity' => 2];
    $input = appointment($v, ['service_lines' => [$line, $line], 'status' => 'draft']); $a = $book->save($input); same([], $lib->usage()->counts());
    $input = appointment($v, ['id' => $a['id'], 'version' => $a['version'], 'service_lines' => [$line, $line]]); $a = $book->save($input); same($a,$book->save($input)); same(1,$lib->usage()->counts()[$svc['id']]);
    $order = $book->convert($a['id'],$a['version']); $order = $orders->action($order['id'], 'release', payload(['version'=>$order['version']])); same(1,$lib->usage()->counts()[$svc['id']]);
    $a = $s->get('appointments',$a['id']); $a = $book->save(appointment($v, ['id'=>$a['id'],'version'=>$a['version'],'service_lines'=>[$line],'status'=>'cancelled'])); same(1,$lib->usage()->counts()[$svc['id']]);
    $order = $orders->action($order['id'],'cancel',payload(['version'=>$order['version'],'reason'=>'Test'])); same([], $lib->usage()->counts());
    $input = payload(['vehicle_id'=>$v['id'],'title'=>'Direkt','service_lines'=>[$line]]); $o = $orders->create($input); same($o,$orders->create($input)); same(1,$lib->list()['items'][0]['usage_count']);
});
test('usage backfill is idempotent preserves every original row and handles linked records and empty snapshots', function () {
    [$s, $u, $v, $lib, $ledger, $book, $orders] = planSetup();
    $svc = $lib->save(['title'=>'Kontrolle','category'=>'Prüfung','minutes'=>30]); $line = ['service_id'=>$svc['id'],'title'=>'Kontrolle','minutes'=>30];
    $a = $book->save(appointment($v,['service_lines'=>[$line]])); $o = $book->convert($a['id'],$a['version']); $orders->action($o['id'],'release',payload(['version'=>$o['version']]));
    $a = $s->get('appointments',$a['id']); $o = $s->get('orders',$o['id']); $s->rows['service_usage'] = [];
    foreach (['appointments','orders','appointments','orders'] as $kind) $lib->usage()->backfill($kind);
    same(1,$lib->usage()->counts()[$svc['id']]); same($a,$s->get('appointments',$a['id'])); same($o,$s->get('orders',$o['id']));
});

test('usage backfill processes bounded stable batches with no duplicates on retry', function () {
    [$s, $u, $v, $lib, $ledger, $book] = planSetup();
    $svc = $lib->save(['title'=>'Prüfung','category'=>'Prüfung','minutes'=>1]);
    for ($i=1; $i<=201; $i++) $s->insert('appointments', ['id'=>str_pad(dechex($i),32,'0',STR_PAD_LEFT),'order_id'=>null,'status'=>'confirmed','service_lines'=>[['service_id'=>$svc['id']]]]);
    $first=$lib->usage()->backfill('appointments'); same(true,$first['more']); same(100,$lib->usage()->counts()[$svc['id']]);
    $lib->usage()->backfill('appointments'); same(100,$lib->usage()->counts()[$svc['id']]);
    $second=$lib->usage()->backfill('appointments',$first['cursor']); same(true,$second['more']); same(200,$lib->usage()->counts()[$svc['id']]);
    $third=$lib->usage()->backfill('appointments',$second['cursor']); same(false,$third['more']); same(201,$lib->usage()->counts()[$svc['id']]);
});
test('reception retains prior master overbooking approval only with unchanged capacity fields', function () {
    [$s, $u, $v, $lib, $ledger, $book] = planSetup();
    $a=$book->save(appointment($v,['minutes'=>500,'override_reason'=>'Zusatzschicht'])); $u->uid='reception';
    $updated=$book->save(appointment($v,['id'=>$a['id'],'version'=>$a['version'],'minutes'=>500,'customer_email'=>'client@example.invalid']));
    same('Zusatzschicht',$s->get('appointments',$a['id'])['override_reason']); same('',$updated['override_reason']);
    rejects(fn () => $book->save(appointment($v,['id'=>$updated['id'],'version'=>$updated['version'],'minutes'=>501])), 'overbooked');
});

test('deleting a Nextcloud user removes only their direct grants and is repeatable', function () {
    $session=new UserSession(); $config=new Config(); $access=new Access($session,new Groups(),$config);
    $config->values['master_users']='["former","director"]'; $config->values['reception_users']='["former","assistant"]';
    $access->removeUser('former'); same(['director'],$access->users('master')); same(['assistant'],$access->users('reception')); $access->removeUser('former'); same(['director'],$access->users('master'));
    $session->uid='former'; rejects(fn () => $access->role(),'forbidden');
});

test('disabled optional calendar queues no jobs while customer mail remains independent', function () {
    [$s, , $v, , , $book] = planSetup();
    $a = $book->save(appointment($v, ['customer_email' => 'kunde@example.test']));
    same([], $s->find('deliveries', ['appointment_id' => $a['id'], 'channel' => 'calendar']));
    same(1, count($s->find('deliveries', ['appointment_id' => $a['id'], 'channel' => 'email'])));
});
test('configured calendar still queues with revoked credentials and stops after disabling', function () {
    $config = new Config(); $config->values['calendar_url'] = 'https://cloud.example.test/remote.php/dav/calendars/user/workshop/';
    [$s, , $v, , , $book] = planSetup($config);
    $a = $book->save(appointment($v));
    same(2, count($s->find('deliveries', ['appointment_id' => $a['id'], 'channel' => 'calendar'])));
    $config->values['calendar_url'] = '';
    $book->save(appointment($v, ['id' => $a['id'], 'version' => $a['version'], 'title' => 'Neuer Titel']));
    same(2, count($s->find('deliveries', ['appointment_id' => $a['id'], 'channel' => 'calendar'])));
});
