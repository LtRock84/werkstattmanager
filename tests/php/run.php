<?php
/** Unit tests with narrow OCP doubles. This is not a Nextcloud integration test. */
declare(strict_types=1);
namespace OCP {
    interface IUserSession
    {
        public function getUser();
    }
    interface IGroupManager
    {
        public function isAdmin(string $uid);
        public function isInGroup(string $uid, string $gid);
        public function get(string $gid);
    }
    interface IConfig
    {
        public function getAppValue(string $app, string $key, string $default = '');
    }
    interface ISession
    {
        public function set($key, $value);
        public function get($key);
    }
}
namespace OCP\DB {
    class Exception extends \RuntimeException
    {
        public const REASON_UNIQUE_CONSTRAINT_VIOLATION = 14,
            REASON_DEADLOCK = 5,
            REASON_LOCK_WAIT_TIMEOUT = 15;
        public function getReason(): int
        {
            return 14;
        }
    }
}
namespace {
    spl_autoload_register(function (string $class): void {
        $prefix = 'OCA\\Werkstattmanager\\';
        if (str_starts_with($class, $prefix)) {
            require __DIR__ .
                '/../../lib/' .
                str_replace('\\', '/', substr($class, strlen($prefix))) .
                '.php';
        }
    });
    use OCA\Werkstattmanager\Db\Store;
    use OCA\Werkstattmanager\Db\Schema;
    use OCA\Werkstattmanager\Domain\{Rules, Problem};
    use OCA\Werkstattmanager\Service\{Access, Catalog, Journal, Orders, Defects};
    final class MemoryStore extends Store
    {
        public array $rows = [];
        public function __construct() {}
        public function transaction(callable $work): mixed
        {
            $before = $this->rows;
            try {
                return $work();
            } catch (\Throwable $e) {
                $this->rows = $before;
                throw $e;
            }
        }
        public function get(string $table, string $id): array
        {
            if (!isset($this->rows[$table][$id])) {
                throw new Problem('not_found', 'Missing', 404);
            }
            return $this->rows[$table][$id];
        }
        public function find(
            string $table,
            array $filters = [],
            int $limit = 100,
            int $offset = 0,
            string $search = '',
        ): array {
            return array_slice(
                array_values(
                    array_filter($this->rows[$table] ?? [], static function ($r) use ($filters) {
                        foreach ($filters as $k => $v) {
                            if ($k === 'employee_id' && array_key_exists('planned_employee_id', $r)) {
                                if ($r['last_employee_id'] !== $v && $r['planned_employee_id'] !== $v) { return false; }
                                continue;
                            }
                            if (is_array($v) ? !in_array($r[$k], $v, true) : $r[$k] !== $v) {
                                return false;
                            }
                        }
                        return true;
                    }),
                ),
                $offset,
                $limit,
            );
        }
        public function planningRows(string $table,string $from,string $to,int $offset=0): array {
            return array_slice(array_values(array_filter($this->rows[$table]??[],static function($r)use($table,$from,$to){
                if($table==='capacity'){return $r['date']>=$from&&$r['date']<=$to;}
                if($table==='orders'&&!in_array($r['status'],['open','active','paused','review','completed'],true)){return false;}
                $first=$r['first_day']??null;$last=$r['last_day']??null;
                if(!$first&&($r['planned_start']??null)){$first=$last=(new \DateTimeImmutable($r['planned_start']))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('Y-m-d');}
                return $first&&$first<=$to&&$last>=$from;
            })), $offset,500);
        }
        public function reportPage(string $table, string $cursor): array {
            $rows = array_filter($this->rows[$table] ?? [], fn ($r) => strcmp($r['id'], $cursor) > 0);
            uasort($rows, fn ($a, $b) => strcmp($a['id'], $b['id']));
            return array_slice(array_values($rows), 0, 500);
        }
        public function serviceCounts(): array {
            return array_count_values(array_column($this->find('service_usage', [], 500), 'service_id'));
        }
        public function usageBackfillPage(string $table, string $cursor): array {
            $rows = array_filter($this->rows[$table] ?? [], fn ($r) => strcmp($r['id'], $cursor) > 0);
            uasort($rows, fn ($a, $b) => strcmp($a['id'], $b['id']));
            return array_slice(array_values($rows), 0, 100);
        }
        public function insert(string $table, array $data): array
        {
            $row =
                [
                    'id' => $data['id'] ?? bin2hex(random_bytes(16)),
                    'version' => 1,
                    'created_at' => time(),
                    'updated_at' => time(),
                ] + $data;
            foreach (Schema::fields($table) as $k => $v) {
                $row[$k] = $row[$k] ?? null;
            }
            foreach (Schema::UNIQUE[$table] ?? [] as $columns) {
                foreach ($this->rows[$table] ?? [] as $existing) {
                    $same = true;
                    foreach ($columns as $k) {
                        $same = $same && $row[$k] !== null && $existing[$k] === $row[$k];
                    }
                    if ($same) {
                        throw new \OCP\DB\Exception('Unique');
                    }
                }
            }
            return $this->rows[$table][$row['id']] = $row;
        }
        public function update(string $table, array $old, array $changes): array
        {
            if ($this->get($table, $old['id'])['version'] !== $old['version']) {
                throw new Problem('conflict', 'Stale', 409);
            }
            $changes['version'] = $old['version'] + 1;
            return $this->rows[$table][$old['id']] = array_replace($old, $changes);
        }
        public function delete(string $table, string $id): void
        {
            unset($this->rows[$table][$id]);
        }
    }
    final class UserSession implements \OCP\IUserSession
    {
        public string $uid = 'master';
        public function getUser()
        {
            return new class ($this->uid) {
                public function __construct(private string $uid) {}
                public function getUID()
                {
                    return $this->uid;
                }
            };
        }
    }
    final class Groups implements \OCP\IGroupManager
    {
        public function isAdmin(string $uid)
        {
            return $uid === 'admin';
        }
        public function isInGroup(string $uid, string $gid)
        {
            return ($uid === 'master' && $gid === 'werkstatt-meister') ||
                ($uid === 'tablet' && $gid === 'werkstatt-tablet') || ($uid === 'reception' && $gid === 'werkstatt-annahme');
        }
        public function get(string $gid)
        {
            return null;
        }
    }
    final class Config implements \OCP\IConfig
    {
        public array $values = [];
        public function setAppValue(string $app, string $key, string $value): void { $this->values[$key] = $value; }
        public function getAppValue(string $app, string $key, string $default = '')
        {
            return $this->values[$key] ?? ($key === 'reception_group' ? 'werkstatt-annahme' : $default);
        }
    }
    final class Session implements \OCP\ISession
    {
        private array $values = [];
        public function set($key, $value)
        {
            $this->values[$key] = $value;
        }
        public function get($key)
        {
            return $this->values[$key] ?? null;
        }
    }
    $passed = 0;
    function test(string $name, callable $fn): void
    {
        global $passed;
        try {
            $fn();
            $passed++;
            echo "PASS $name\n";
        } catch (\Throwable $e) {
            fwrite(
                STDERR,
                "FAIL $name: " .
                    get_class($e) .
                    ' ' .
                    $e->getMessage() .
                    "\n" .
                    $e->getTraceAsString() .
                    "\n",
            );
            exit(1);
        }
    }
    function same(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                'Expected ' . json_encode($expected) . ' got ' . json_encode($actual),
            );
        }
    }
    function rejects(callable $fn, string $code): void
    {
        try {
            $fn();
        } catch (Problem $e) {
            same($code, $e->error);
            return;
        }
        throw new \RuntimeException('Expected rejection: ' . $code);
    }
    function setup(): array
    {
        $store = new MemoryStore();
        $user = new UserSession();
        $access = new Access($user, new Groups(), new Config());
        $journal = new Journal($store, $access);
        $catalog = new Catalog($store, $access, $journal);
        $orders = new Orders($store, $access, $catalog, $journal);
        $vehicle = $catalog->save('vehicles', ['kind' => 'fleet', 'plate' => 'TEST-1']);
        $a = $catalog->save('employees', ['name' => 'A']);
        $b = $catalog->save('employees', ['name' => 'B']);
        return [$store, $user, $access, $catalog, $journal, $orders, $vehicle, $a, $b];
    }
    function payload(array $data = []): array
    {
        return $data + ['request_id' => bin2hex(random_bytes(16))];
    }
    function create(Orders $orders, array $vehicle): array
    {
        return $orders->create(
            payload([
                'vehicle_id' => $vehicle['id'],
                'title' => 'Bremsen prüfen',
                'steps' => ['Prüfen'],
                'status' => 'open',
                'minutes' => 45,
            ]),
        );
    }
    test(
        'invalid schedule rejected',
        fn() => rejects(
            fn() => Rules::schedule('2026-10-02T10:00:00Z', '2026-10-02T09:00:00Z'),
            'schedule',
        ),
    );
    test('optional independent schedule', function () {
        Rules::schedule(null, '2026-10-02T09:00:00Z');
        same(null, Rules::integer(''));
    });
    test(
        'month-end recurrence clamps day',
        fn() => same('2027-02-28', Rules::nextMonth('2027-01-31', 1)),
    );
    test(
        'date validates leap days',
        fn() => rejects(fn() => Rules::date('2026-02-29'), 'validation'),
    );
    test('combined deadline uses either criterion', function () {
        same(true, Rules::due(['due_date' => '2026-12-01', 'due_km' => 100], 100, '2026-10-01'));
        same(true, Rules::due(['due_date' => '2026-10-01', 'due_km' => 100], 50, '2026-10-01'));
    });
    test('template copied and later edit cannot alter order', function () {
        [$s, $u, $a, $c, $j, $o, $v] = setup();
        $template = $c->save('templates', [
            'title' => 'Inspektion',
            'steps' => ['Prüfen'],
            'minutes' => 60,
        ]);
        $order = $o->create(
            payload([
                'vehicle_id' => $v['id'],
                'title' => $template['title'],
                'steps' => $template['steps'],
                'minutes' => $template['minutes'],
            ]),
        );
        $c->save(
            'templates',
            [
                'version' => $template['version'],
                'title' => 'Andere Vorlage',
                'steps' => ['Ändern'],
                'minutes' => 120,
            ],
            $template['id'],
        );
        same(['Prüfen'], $s->get('orders', $order['id'])['steps']);
        same(60, $s->get('orders', $order['id'])['minutes']);
    });
    test('idempotent create does not duplicate', function () {
        [$s, $u, $a, $c, $j, $o, $v] = setup();
        $p = payload(['vehicle_id' => $v['id'], 'title' => 'Test']);
        same($o->create($p)['id'], $o->create($p)['id']);
        same(1, count($s->find('orders')));
        rejects(fn() => $o->create(array_replace($p, ['title' => 'Anders'])), 'request_id');
    });
    test('one active order per employee, failed start rolls back', function () {
        [$s, $u, $a, $c, $j, $o, $v, $e] = setup();
        $one = create($o, $v);
        $two = create($o, $v);
        $u->uid = 'tablet';
        $o->action($one['id'], 'start', payload(['version' => 1, 'employee_id' => $e['id']]));
        rejects(
            fn() => $o->action(
                $two['id'],
                'start',
                payload(['version' => 1, 'employee_id' => $e['id']]),
            ),
            'employee_busy',
        );
        same(1, count($s->find('active')));
        same(1, count($s->find('intervals')));
        same('open', $s->get('orders', $two['id'])['status']);
    });
    test('different employees can work on different orders', function () {
        [$s, $u, $a, $c, $j, $o, $v, $e, $f] = setup();
        $one = create($o, $v);
        $two = create($o, $v);
        $o->action($one['id'], 'start', payload(['version' => 1, 'employee_id' => $e['id']]));
        $o->action($two['id'], 'start', payload(['version' => 1, 'employee_id' => $f['id']]));
        same(2, count($s->find('active')));
    });
    test('stale start of same order rejected', function () {
        [$s, $u, $a, $c, $j, $o, $v, $e, $f] = setup();
        $one = create($o, $v);
        $o->action($one['id'], 'start', payload(['version' => 1, 'employee_id' => $e['id']]));
        rejects(
            fn() => $o->action(
                $one['id'],
                'start',
                payload(['version' => 1, 'employee_id' => $f['id']]),
            ),
            'conflict',
        );
        same(1, count($s->find('active')));
    });
    test('pause then colleague takeover preserves attribution', function () {
        [$s, $u, $a, $c, $j, $o, $v, $e, $f] = setup();
        $one = create($o, $v);
        $r = $o->action($one['id'], 'start', payload(['version' => 1, 'employee_id' => $e['id']]));
        $r = $o->action(
            $one['id'],
            'pause',
            payload(['version' => $r['version'], 'employee_id' => $e['id'], 'reason' => 'Pause']),
        );
        same($one['id'], $c->list('orders', 0, ['employee_id' => $e['id']])['items'][0]['id']);
        $r = $o->action(
            $one['id'],
            'start',
            payload(['version' => $r['version'], 'employee_id' => $f['id']]),
        );
        same([], $c->list('orders', 0, ['employee_id' => $e['id']])['items']);
        same($one['id'], $c->list('orders', 0, ['employee_id' => $f['id']])['items'][0]['id']);
        $rows = $s->find('intervals');
        same($e['id'], $rows[0]['employee_id']);
        same($f['id'], $rows[1]['employee_id']);
        same(true, $rows[0]['ended'] !== null);
        same(null, $rows[1]['ended']);
    });
    test('finish requires protocol and meister approval', function () {
        [$s, $u, $a, $c, $j, $o, $v, $e] = setup();
        $r = create($o, $v);
        $r = $o->action($r['id'], 'start', payload(['version' => 1, 'employee_id' => $e['id']]));
        $u->uid = 'tablet';
        rejects(
            fn() => $o->action(
                $r['id'],
                'finish',
                payload(['version' => $r['version'], 'employee_id' => $e['id']]),
            ),
            'protocol',
        );
        $r = $o->action(
            $r['id'],
            'finish',
            payload(['version' => $r['version'], 'employee_id' => $e['id'], 'steps' => ['Prüfen']]),
        );
        same('review', $r['status']);
        same(0, count($s->find('active')));
        rejects(
            fn() => $o->action($r['id'], 'approve', payload(['version' => $r['version']])),
            'forbidden',
        );
        $u->uid = 'master';
        same(
            'completed',
            $o->action($r['id'], 'approve', payload(['version' => $r['version']]))['status'],
        );
    });
    test('open parts prevent approval and request is idempotent', function () {
        [$s, $u, $a, $c, $j, $o, $v, $e] = setup();
        $r = create($o, $v);
        $r = $o->action($r['id'], 'start', payload(['version' => 1, 'employee_id' => $e['id']]));
        $p = payload(['employee_id' => $e['id'], 'title' => 'Filter']);
        $part = $o->requestPart($r['id'], $p);
        same($part['id'], $o->requestPart($r['id'], $p)['id']);
        $r = $s->get('orders', $r['id']);
        $r = $o->action(
            $r['id'],
            'finish',
            payload(['version' => $r['version'], 'employee_id' => $e['id'], 'note' => 'Fertig']),
        );
        rejects(
            fn() => $o->action($r['id'], 'approve', payload(['version' => $r['version']])),
            'parts_open',
        );
        $o->updatePart($part['id'], 'cancelled', $part['version']);
        $r = $s->get('orders', $r['id']);
        same(
            'completed',
            $o->action($r['id'], 'approve', payload(['version' => $r['version']]))['status'],
        );
    });
    test('tablet cannot read customer data, drafts, or unrelated collections', function () {
        [$s, $u, $a, $c, $j, $o, $v] = setup();
        $r = $o->create(
            payload(['vehicle_id' => $v['id'], 'title' => 'Entwurf', 'status' => 'draft']),
        );
        $u->uid = 'tablet';
        rejects(fn() => $c->list('customers'), 'forbidden');
        same([], $c->list('orders')['items']);
        rejects(fn() => $c->visibleOrder($r['id']), 'not_found');
        rejects(fn() => $c->list('intervals'), 'forbidden');
        same(false, array_key_exists('vin', $c->list('vehicles')['items'][0]));
    });
    test('time corrections reject overlaps and journal originals', function () {
        [$s, $u, $a, $c, $j, $o, $v, $e] = setup();
        $r = create($o, $v);
        $i = $s->insert('intervals', [
            'order_id' => $r['id'],
            'employee_id' => $e['id'],
            'started' => 1000,
            'ended' => 2000,
        ]);
        $s->insert('intervals', [
            'order_id' => $r['id'],
            'employee_id' => $e['id'],
            'started' => 3000,
            'ended' => 4000,
        ]);
        rejects(
            fn() => $o->correctTime($i['id'], [
                'version' => 1,
                'started' => 1500,
                'ended' => 3500,
                'reason' => 'Korrektur',
            ]),
            'overlap',
        );
        $o->correctTime($i['id'], [
            'version' => 1,
            'started' => 1100,
            'ended' => 2100,
            'reason' => 'Korrektur',
        ]);
        $audit = array_values(
            array_filter($s->find('audit'), fn($r) => $r['action'] === 'time.corrected'),
        )[0];
        same(1000, $audit['details']['before']['started']);
    });
    test('driver sees only approved summary; revoke invalidates session', function () {
        [$s, $u, $a, $c, $j, $o, $v] = setup();
        $d = new Defects($s, $a, $j, new Session());
        $token = $d->link($v['id'])['token'];
        $view = $d->exchange($token);
        same(false, array_key_exists('vin', $view['vehicle']));
        $p = payload(['description' => 'Private detail', 'reporter' => 'Driver']);
        $report = $d->report($p);
        same($report['id'], $d->report($p)['id']);
        same([], $d->publicView()['items']);
        $row = $s->get('defects', $report['id']);
        $d->update($row['id'], [
            'version' => $row['version'],
            'status' => 'open',
            'published' => true,
            'public_text' => 'Ölverlust',
        ]);
        $public = $d->publicView()['items'][0];
        same(['text', 'status', 'created_at'], array_keys($public));
        same('Ölverlust', $public['text']);
        $d->link($v['id'], true);
        rejects(fn() => $d->publicView(), 'link');
    });
    test('driver duplicate target must belong to same vehicle', function () {
        [$s, $u, $a, $c, $j, $o, $v] = setup();
        $v2 = $c->save('vehicles', ['kind' => 'fleet', 'plate' => 'TEST-2']);
        $d = new Defects($s, $a, $j, new Session());
        $d->exchange($d->link($v['id'])['token']);
        $r1 = $d->report(payload(['description' => 'one']));
        $d->exchange($d->link($v2['id'])['token']);
        $r2 = $d->report(payload(['description' => 'two']));
        rejects(
            fn() => $d->update($r1['id'], [
                'version' => 1,
                'status' => 'duplicate',
                'duplicate_id' => $r2['id'],
            ]),
            'validation',
        );
    });
    test('deadline completion creates exactly one next deadline', function () {
        [$s, $u, $a, $c, $j, $o, $v] = setup();
        $d = $c->save('deadlines', [
            'vehicle_id' => $v['id'],
            'title' => 'HU',
            'due_date' => '2026-10-01',
            'repeat_months' => 24,
        ]);
        $c->completeDeadline($d['id'], '2026-10-02', null);
        rejects(fn() => $c->completeDeadline($d['id'], '2026-10-02', null), 'state');
        $next = $s->find('deadlines', ['status' => 'open']);
        same(1, count($next));
        same('2028-10-02', $next[0]['due_date']);
    });
    test('deadline warnings reject empty entries and deduplicate valid days', function () {
        [$s, $u, $a, $c, $j, $o, $v] = setup();
        $input = ['vehicle_id' => $v['id'], 'title' => 'Prüfung', 'due_date' => '2027-01-01'];
        rejects(fn() => $c->save('deadlines', $input + ['warning_days' => [null]]), 'validation');
        $d = $c->save('deadlines', $input + ['warning_days' => [30, 7, 7, 0]]);
        same([30, 7, 0], $d['warning_days']);
    });
    test('duplicate links cannot form a cycle and are cleared when reopening', function () {
        [$s, $u, $a, $c, $j, $o, $v] = setup();
        $d = new Defects($s, $a, $j, new Session());
        $d->exchange($d->link($v['id'])['token']);
        $one = $d->report(payload(['description' => 'one']));
        $two = $d->report(payload(['description' => 'two']));
        $merged = $d->update($one['id'], ['version' => 1, 'status' => 'duplicate', 'duplicate_id' => $two['id']]);
        rejects(fn() => $d->update($two['id'], ['version' => 2, 'status' => 'duplicate', 'duplicate_id' => $one['id']]), 'validation');
        $opened = $d->update($one['id'], ['version' => $merged['version'], 'status' => 'open']);
        same(null, $opened['duplicate_id']);
    });
    test('master retries failed notifications once and preserves the attempt history', function () {
        [$s, $u, $a, $c] = setup();
        $r = $s->insert('outbox', ['recipient' => 'master', 'status' => 'failed', 'attempts' => 10, 'event_key' => 'retry-test', 'subject' => 'part', 'object_id' => 'object', 'next_try' => 0]);
        same(1, count($c->list('outbox')['items']));
        same(false, array_key_exists('event_key', $c->list('outbox')['items'][0]));
        same(['queued' => true, 'cancelled' => false], $c->retryNotification($r['id'], 1));
        same('pending', $s->get('outbox', $r['id'])['status']);
        same(0, $s->get('outbox', $r['id'])['attempts']);
        same(10, $s->find('audit', ['action' => 'notification.retry'])[0]['details']['previous_attempts']);
        rejects(fn() => $c->retryNotification($r['id'], 1), 'conflict');
        same(1, count($s->find('outbox')));
        same([], $c->list('outbox')['items']);
    });
    test('notification retry cancels delivery after recipient rights were removed', function () {
        [$s, $u, $a, $c] = setup();
        $r = $s->insert('outbox', ['recipient' => 'former-master', 'status' => 'failed', 'attempts' => 10, 'event_key' => 'revoked-test']);
        same(['queued' => false, 'cancelled' => true], $c->retryNotification($r['id'], 1));
        same('cancelled', $s->get('outbox', $r['id'])['status']);
    });
    test('tablet cannot inspect or retry notification deliveries', function () {
        [$s, $u, $a, $c] = setup();
        $u->uid = 'tablet';
        rejects(fn() => $c->list('outbox'), 'forbidden');
        rejects(fn() => $c->retryNotification('unknown', 1), 'forbidden');
    });
    test('route placeholders do not collide with Nextcloud internal routing keys', function () {
        $routes = require __DIR__ . '/../../appinfo/routes.php';
        foreach ($routes['routes'] as $route) {
            same(0, preg_match('/\{(?:action|caller|_route)\}/', $route['url']));
        }
    });
    test('audit context resolves references and respects anonymized employee names', function () {
        [$s, $u, $a, $c, $j, $o, $v, $e] = setup();
        $order = create($o, $v);
        $order = $o->action(
            $order['id'],
            'start',
            payload(['version' => 1, 'employee_id' => $e['id'], 'reason' => 'Old default pause']),
        );
        $part = $o->requestPart(
            $order['id'],
            payload([
                'employee_id' => $e['id'],
                'title' => 'Ölfilter',
                'quantity' => 2,
                'pause' => true,
            ]),
        );
        $audit = $c->list('audit', 0, ['vehicle_id' => $v['id']])['items'];
        $request = array_values(
            array_filter($audit, fn($r) => $r['action'] === 'part.requested'),
        )[0];
        same(true, $request['details']['paused']);
        same('Ölfilter', $request['context']['part']['title']);
        same(2, $request['context']['part']['quantity']);
        same($order['id'], $request['context']['order']['id']);
        same('A', $request['context']['employee']['name']);
        same(false, isset($request['context']['employee']['id']));
        $start = array_values(array_filter($audit, fn($r) => $r['action'] === 'order.start'))[0];
        same('', $start['details']['reason']);
        same(false, isset($s->get('audit', $request['id'])['context']));
        $s->update('employees', $s->get('employees', $e['id']), ['name' => 'Anonymisiert']);
        same('Anonymisiert', $j->withContext([$request])[0]['context']['employee']['name']);
        $s->delete('parts', $part['id']);
        same(null, $j->withContext([$request])[0]['context']['part']);
    });
    test('audit and enriched context reject tablet access', function () {
        [$s, $u, $a, $c, $j] = setup();
        $u->uid = 'tablet';
        rejects(fn() => $c->list('audit'), 'forbidden');
        rejects(fn() => $j->withContext($s->find('audit')), 'forbidden');
    });
    test('finish audit retains actual work notes and steps', function () {
        [$s, $u, $a, $c, $j, $o, $v, $e] = setup();
        $order = create($o, $v);
        $order = $o->action(
            $order['id'],
            'start',
            payload(['version' => 1, 'employee_id' => $e['id']]),
        );
        $o->action(
            $order['id'],
            'finish',
            payload([
                'version' => $order['version'],
                'employee_id' => $e['id'],
                'note' => 'Probefahrt ohne Befund',
                'steps' => ['Öl gewechselt'],
            ]),
        );
        $entry = $s->find('audit', ['action' => 'order.finish'])[0];
        same('Probefahrt ohne Befund', $entry['details']['note']);
        same(['Öl gewechselt'], $entry['details']['steps']);
        same('', $entry['details']['reason']);
    });
    require __DIR__.'/employee-planning.php';
    require __DIR__.'/planning.php';
    require __DIR__.'/company-mail.php';
    require __DIR__.'/statistics.php';
    require __DIR__.'/customer-mail.php';
    echo "$passed unit scenarios passed. Nextcloud/SQL concurrency integration is separate.\n";
}
