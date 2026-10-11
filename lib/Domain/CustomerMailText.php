<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Domain;

/** Plain-text substitution, deliberately not an executable template language. */
final class CustomerMailText {
	public const TOKENS = ['kundenname', 'fahrzeug', 'termin', 'abholung', 'leistung', 'frist', 'faellig_am', 'faellig_km', 'werkstatt'];
	public static function defaults(): array {
		$hello = "Guten Tag {kundenname},\n\n";
		$bye = "\n\nBei Fragen oder Änderungswünschen wenden Sie sich bitte an uns.\nFreundliche Grüße\n{werkstatt}";
		return [
			'confirm' => ['label' => 'Terminbestätigung', 'subject' => 'Ihr Werkstatttermin für {fahrzeug}', 'body' => $hello . "wir bestätigen Ihren Termin für {fahrzeug}.\nTermin / Fahrzeugannahme: {termin}\nGeplante Arbeiten: {leistung}\nVereinbarte Abholung: {abholung}\n\nBitte bringen Sie den Fahrzeugschlüssel und vorhandene Serviceunterlagen mit." . $bye],
			'update' => ['label' => 'Terminänderung', 'subject' => 'Ihr Werkstatttermin wurde geändert · {fahrzeug}', 'body' => $hello . "Ihr Termin für {fahrzeug} wurde angepasst.\nAktueller Termin / Fahrzeugannahme: {termin}\nGeplante Arbeiten: {leistung}\nVereinbarte Abholung: {abholung}" . $bye],
			'cancel' => ['label' => 'Terminabsage', 'subject' => 'Terminabsage für {fahrzeug}', 'body' => $hello . "der Termin am {termin} für {fahrzeug} wurde abgesagt.\nGeplante Arbeiten: {leistung}\nBitte vereinbaren Sie bei Bedarf einen neuen Termin mit uns." . $bye],
			'pickup_cancel' => ['label' => 'Abholzeit aufgehoben', 'subject' => 'Abholzeit für {fahrzeug} aufgehoben', 'body' => $hello . "die bisher zugesagte Abholzeit {abholung} für {fahrzeug} entfällt.\nDer Annahmetermin {termin} bleibt bestehen. Wir stimmen die neue Abholzeit mit Ihnen ab." . $bye],
			'reminder' => ['label' => 'Terminerinnerung', 'subject' => 'Erinnerung an Ihren Werkstatttermin · {fahrzeug}', 'body' => $hello . "wir erinnern Sie an Ihren Termin für {fahrzeug}.\nTermin / Fahrzeugannahme: {termin}\nGeplante Arbeiten: {leistung}\nVereinbarte Abholung: {abholung}\n\nFalls Sie den Termin nicht wahrnehmen können, geben Sie uns bitte rechtzeitig Bescheid." . $bye],
			'ready' => ['label' => 'Fahrzeug abholbereit', 'subject' => 'Ihr Fahrzeug {fahrzeug} ist abholbereit', 'body' => $hello . "Ihr Fahrzeug {fahrzeug} ist abholbereit.\nAuftrag / Arbeiten: {leistung}\nAnnahmetermin: {termin}\nVereinbarte Abholung: {abholung}\n\nSie können Ihr Fahrzeug während unserer vereinbarten Abholzeiten abholen. Falls keine Abholzeit vereinbart ist, stimmen Sie diese bitte kurz mit uns ab." . $bye],
			'deadline' => ['label' => 'Fristenerinnerung', 'subject' => 'Bitte Werkstatttermin vereinbaren · {fahrzeug}', 'body' => $hello . "für Ihr Fahrzeug {fahrzeug} steht folgende Arbeit oder Prüfung an:\n{frist}\nFällig am: {faellig_am}\nKilometergrenze: {faellig_km}\n\nBitte vereinbaren Sie rechtzeitig einen Termin mit uns. Falls die Arbeit bereits anderweitig erledigt wurde, teilen Sie uns dies bitte mit, damit wir die Fahrzeugakte aktualisieren können." . $bye],
		];
	}
	public static function validate(mixed $input): array {
		if (!is_array($input) || array_diff(array_keys($input), array_keys(self::defaults()))) {
			throw new Problem('validation', 'Ungültige Nachrichtenvorlagen.');
		}
		$result = [];
		foreach (self::defaults() as $kind => $default) {
			$value = $input[$kind] ?? null;
			if (!is_array($value)) {
				throw new Problem('validation', 'Bitte alle Nachrichtenvorlagen ausfüllen.');
			}
			$subject = Rules::text($value['subject'] ?? '', 255, true);
			$body = Rules::text($value['body'] ?? '', 10000, true);
			if (preg_match('/[\x00-\x1f\x7f]/', $subject) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $body)) {
				throw new Problem('validation', 'Die Vorlage enthält unzulässige Steuerzeichen.');
			}
			preg_match_all('/\{([^{}]+)\}/', $subject . $body, $matches);
			if (array_diff($matches[1], self::TOKENS)) {
				throw new Problem('validation', 'Unbekannter Platzhalter. Bitte einen der angezeigten Platzhalter verwenden.');
			}
			$result[$kind] = ['label' => $default['label'], 'subject' => $subject, 'body' => $body];
		}
		return $result;
	}
	public static function render(array $template, array $values): array {
		$map = [];
		foreach (self::TOKENS as $key) {
			$map['{' . $key . '}'] = (string)($values[$key] ?? 'Nicht vereinbart');
		}
		// Replacements are not evaluated recursively. Data cannot introduce header fields.
		$subject = preg_replace('/[\x00-\x1f\x7f]/u', ' ', strtr($template['subject'], $map));
		return ['subject' => implode('', array_slice(preg_split('//u', $subject ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 255)), 'body' => strtr($template['body'], $map)];
	}
}
