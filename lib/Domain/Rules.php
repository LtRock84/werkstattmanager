<?php

declare(strict_types=1);

namespace OCA\Werkstattmanager\Domain;

final class Rules {
	public static function text(mixed $value, int $max = 255, bool $required = false): string {
		if (!is_string($value)) {
			throw new Problem('validation', 'Bitte einen Text eingeben.');
		}
		$value = trim($value);
		if (strlen($value) > $max || ($required && $value === '')) {
			throw new Problem('validation', 'Text fehlt oder ist zu lang.');
		}
		return $value;
	}
	public static function integer(mixed $value, int $min = 0, int $max = 10000000): ?int {
		if ($value === null || $value === '') {
			return null;
		}
		if (
			is_bool($value) || is_array($value) || is_object($value) || filter_var($value, FILTER_VALIDATE_INT) === false
			|| (int)$value < $min
			|| (int)$value > $max
		) {
			throw new Problem('validation', 'Ungültige Zahl.');
		}
		return (int)$value;
	}
	public static function choice(mixed $value, array $choices): string {
		if (!is_string($value) || !in_array($value, $choices, true)) {
			throw new Problem('validation', 'Ungültige Auswahl.');
		}
		return $value;
	}
	public static function date(mixed $value, bool $time = false): ?string {
		if ($value === null || $value === '') {
			return null;
		}
		if (!is_string($value)) {
			throw new Problem('validation', 'Ungültiges Datum.');
		}
		if (!$time) {
			$d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
			if (!$d || $d->format('Y-m-d') !== $value) {
				throw new Problem('validation', 'Ungültiges Datum.');
			}
			return $value;
		}
		if (
			!preg_match(
				'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?(?:Z|[+-]\d{2}:\d{2})$/D',
				$value,
			)
		) {
			throw new Problem('validation', 'Zeitpunkt mit Zeitzone erforderlich.');
		}
		try {
			$d = new \DateTimeImmutable($value);
			$errors = \DateTimeImmutable::getLastErrors();
			if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
				throw new \InvalidArgumentException('Invalid date');
			}
		} catch (\Exception) {
			throw new Problem('validation', 'Ungültiger Zeitpunkt.');
		}
		return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
	}
	public static function schedule(?string $start, ?string $end): void {
		if ($start && $end && $end < $start) {
			throw new Problem(
				'schedule',
				'Fertigstellung darf nicht vor dem geplanten Beginn liegen.',
			);
		}
	}
	public static function steps(mixed $steps): array {
		if (!is_array($steps) || count($steps) > 100) {
			throw new Problem('validation', 'Maximal 100 Arbeitsschritte sind erlaubt.');
		}
		return array_values(array_map(fn ($v) => self::text($v, 1000, true), $steps));
	}
	public static function transition(string $from, string $action): string {
		$map = [
			'release' => ['draft' => 'open'],
			'start' => ['open' => 'active', 'paused' => 'active'],
			'pause' => ['active' => 'paused'],
			'finish' => ['active' => 'review'],
			'approve' => ['review' => 'completed'],
			'rework' => ['review' => 'open', 'completed' => 'open'],
			'cancel' => [
				'draft' => 'cancelled',
				'open' => 'cancelled',
				'paused' => 'cancelled',
				'review' => 'cancelled',
			],
		];
		if (!isset($map[$action][$from])) {
			throw new Problem(
				'state',
				'Diese Aktion ist im aktuellen Auftragsstatus nicht möglich.',
				409,
			);
		}
		return $map[$action][$from];
	}
	public static function nextMonth(string $date, int $months): string {
		$d = new \DateTimeImmutable($date);
		$base = $d->modify('first day of this month')->modify('+' . $months . ' months');
		return $base
			->setDate(
				(int)$base->format('Y'),
				(int)$base->format('m'),
				min((int)$d->format('d'), (int)$base->format('t')),
			)
			->format('Y-m-d');
	}
	public static function due(array $deadline, ?int $mileage, string $today): bool {
		return ($deadline['due_date'] !== null && $deadline['due_date'] <= $today)
			|| ($deadline['due_km'] !== null && $mileage !== null && $mileage >= $deadline['due_km']);
	}
}
