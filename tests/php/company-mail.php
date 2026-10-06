<?php
/** Narrow doubles verify business guards; live OCP/provider integration is separate. */
namespace OCP { interface IUserManager { public function get(string $uid); } }
namespace OCP\Files { interface IAppData {} }
namespace OCP\Mail { interface IMailer {} }
namespace OCP\Mail\Provider {
    interface IService {}
    interface IMessageSend { public function sendMessage($message, array $options = []): void; }
    interface IManager { public function services(string $uid): array; public function findServiceById(string $uid, string $id, ?string $provider = null): ?IService; }
    class Address { public function __construct(public ?string $address = null, public ?string $label = null) {} public function getAddress(): ?string { return $this->address; } }
    class Attachment { public function __construct(public string $contents, public string $name, public string $type) {} }
}
namespace {
    function companyMailSetup(): array {
        $session = new UserSession(); $session->uid = 'admin'; $config = new Config(); $access = new \OCA\Werkstattmanager\Service\Access($session, new Groups(), $config);
        $company = new \OCA\Werkstattmanager\Service\Company($access, $config, new class implements \OCP\Files\IAppData {});
        $users = new class implements \OCP\IUserManager { public bool $enabled = true; public function get(string $uid) { return $uid === 'admin' ? new class($this->enabled) { public function __construct(private bool $enabled) {} public function isEnabled(): bool { return $this->enabled; } } : null; } };
        $providers = new class implements \OCP\Mail\Provider\IManager {
            public bool $exists = true; public string $address = 'werkstatt@example.test'; public array $sent = [];
            public function services(string $uid): array { $service = $this->findServiceById($uid, '1', 'mail-application'); return ['mail-application' => $service ? [$service] : []]; }
            public function findServiceById(string $uid, string $id, ?string $provider = null): ?\OCP\Mail\Provider\IService {
                if (!$this->exists || $uid !== 'admin' || $id !== '1' || $provider !== 'mail-application') { return null; }
                return new class($this) implements \OCP\Mail\Provider\IService, \OCP\Mail\Provider\IMessageSend {
                    public function __construct(private object $manager) {} public function id(): string { return '1'; } public function capable(string $v): bool { return $v === 'MessageSend'; } public function getLabel(): string { return 'Firma'; }
                    public function getPrimaryAddress(): \OCP\Mail\Provider\Address { return new \OCP\Mail\Provider\Address($this->manager->address); }
                    public function initiateMessage(): object { return new class { public array $values = []; public function __call($name, $args) { $this->values[$name] = $args; return $this; } }; }
                    public function sendMessage($message, array $options = []): void { $this->manager->sent[] = $message->values; }
                };
            }
        };
        $sender = new \OCA\Werkstattmanager\Service\WorkshopMailer($access, $config, $users, $providers, new class implements \OCP\Mail\IMailer {}, $company);
        return [$session, $config, $company, $sender, $providers, $users];
    }
    test('company profile normalizes name and denies reception/tablet modifications', function () {
        [$u, $c, $company] = companyMailSetup(); same(['name' => '', 'logo' => ''], $company->profile()); same('Firma', $company->save(' Firma ')['name']);
        rejects(fn () => $company->save("Firma\r\nX"), 'validation'); foreach (['tablet', 'reception'] as $role) { $u->uid = $role; rejects(fn () => $company->save('Bad'), 'forbidden'); } same('Firma', $company->profile()['name']); rejects(fn () => $company->logo('../x'), 'not_found');
    });
    test('shared sender uses only own Mail account and retains address and ICS without credentials', function () {
        [$u, $c, $company, $sender, $providers] = companyMailSetup(); $company->save('Werkstatt'); $sender->configure(['mode' => 'mail', 'service' => '1']);
        same('werkstatt@example.test', $sender->settings()['selection']['address']); rejects(fn () => $sender->configure(['mode' => 'mail', 'service' => 'foreign']), 'mail_config');
        $send = $sender->prepare('kunde@example.test', 'Termin', 'Text', 'BEGIN:VCALENDAR'); same([], $providers->sent); same('submitted', $send());
        $message = $providers->sent[0]; same('werkstatt@example.test', $message['setFrom'][0]->address); same('Werkstatt', $message['setFrom'][0]->label); same('kunde@example.test', $message['setTo'][0]->address); same('BEGIN:VCALENDAR', $message['setAttachments'][0]->contents);
        same(false, str_contains($c->values['workshop_mail'], 'password'));
    });
    test('shared sender rejects missing disabled changed or deleted owner without SMTP fallback', function () {
        [$u, $c, $company, $sender, $providers, $users] = companyMailSetup(); $sender->configure(['mode' => 'mail', 'service' => '1']); $providers->exists = false; rejects(fn () => $sender->prepare('k@example.test', 'T', 'B', 'ICS'), 'mail_config');
        $providers->exists = true; $providers->address = 'changed@example.test'; rejects(fn () => $sender->prepare('k@example.test', 'T', 'B', 'ICS'), 'mail_config'); $providers->address = 'werkstatt@example.test'; $users->enabled = false; rejects(fn () => $sender->prepare('k@example.test', 'T', 'B', 'ICS'), 'mail_config');
        $users->enabled = true; $sender->removeOwner('unrelated'); same('admin', $sender->settings()['selection']['owner']); $sender->removeOwner('admin'); rejects(fn () => $sender->prepare('k@example.test', 'T', 'B', 'ICS'), 'mail_config'); same([], $providers->sent);
    });
    test('sender configuration and account directory are administrator-only', function () {
        [$u, $c, $company, $sender] = companyMailSetup(); foreach (['master', 'reception', 'tablet'] as $role) { $u->uid = $role; rejects(fn () => $sender->settings(), 'forbidden'); rejects(fn () => $sender->configure(['mode' => 'system']), 'forbidden'); }
    });
    test('Mail handoff history strips private payload and remains master-only', function () {
        [$s, $u, $v, $lib, $ledger, $book] = planSetup();
        $s->insert('deliveries', ['status' => 'submitted', 'payload' => ['title' => 'Termin', 'customer_email' => 'private@example.test', 'note' => 'PRIVATE']]);
        $rows = $book->deliveries(); same('Termin', $rows['submitted'][0]['context']['title']); same(false, isset($rows['submitted'][0]['payload']));
        $u->uid = 'reception'; rejects(fn () => $book->deliveries(), 'forbidden');
    });

}
