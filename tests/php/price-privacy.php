<?php
use OCA\Werkstattmanager\Domain\Pricing;

test('neutral provider identities preserve independent-provider statistics and are idempotent', function () {
    $rows = json_decode(file_get_contents(__DIR__.'/../../lib/Data/prices.json'), true, 512, JSON_THROW_ON_ERROR)['observations'];
    $legacy = $rows;
    $aliases = [];
    foreach ($legacy as &$row) {
        $aliases[$row['provider']] ??= 'Legacy research provider '.count($aliases);
        $row['provider'] = $aliases[$row['provider']];
        $row['source'] = 'https://example.invalid/research';
    }
    unset($row);
    $public = Pricing::publicObservations($legacy);
    same($public, Pricing::publicObservations($public));
    $summary = function (array $observations): array {
        return array_map(function ($group) { unset($group['observations']); return $group; }, Pricing::benchmark($observations, '2026-10-16'));
    };
    same($summary($legacy), $summary($public));
    foreach ($public as $i => $row) {
        same('', $row['source']);
        same(true, (bool)preg_match('/^Preisgeber [a-f0-9]{12}$/D', $row['provider']));
        same($legacy[$i]['gross_cents'], $row['gross_cents']);
        same($legacy[$i]['seed_key'], $row['seed_key']);
    }
});
test('price API withholds legacy names and URLs without altering stored observations', function () {
    [$store, , , $library] = planSetup();
    $row = json_decode(file_get_contents(__DIR__.'/../../lib/Data/prices.json'), true, 512, JSON_THROW_ON_ERROR)['observations'][0];
    $row['provider'] = 'Legacy company not for publication';
    $row['source'] = 'https://example.invalid/private-research';
    $stored = $store->insert('prices', $row);
    $response = $library->prices($row['service_key']);
    same('', $response['items'][0]['source']);
    same(false, str_contains(json_encode($response), 'Legacy company'));
    same(false, str_contains(json_encode($response), 'private-research'));
    same($stored, $store->get('prices', $stored['id']));
});
test('public catalogue retains all observations without named providers or source links', function () {
    $rows = json_decode(file_get_contents(__DIR__.'/../../lib/Data/prices.json'), true, 512, JSON_THROW_ON_ERROR)['observations'];
    same(334, count($rows));
    same(32, count(array_unique(array_column($rows, 'provider'))));
    same(334, count(array_unique(array_column($rows, 'seed_key'))));
    foreach ($rows as $row) {
        same('', $row['source']);
        same(true, (bool)preg_match('/^Preisgeber [a-f0-9]{12}$/D', $row['provider']));
    }
});
