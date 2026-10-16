import { t, date, stamp, orderNumber } from './format'
import type { Audit, ReceptionChecklist } from './types'
import { receptionLabels } from './planning'

export function auditEntry(entry: Audit) {
  const titles: Record<string, string> = {
    'mail.templates_saved': t('Kundennachrichten und Fristenvorlauf geändert'),
    'revenue.saved': t('Plan- oder Ist-Umsatz erfasst oder korrigiert'),
    'appointment.draft': t('Kundentermin als Entwurf gespeichert'),
    'appointment.confirmed': t('Kundentermin bestätigt oder geändert'),
    'appointment.cancelled': t('Kundentermin abgesagt'),
    'appointment.order_created': t('Arbeitsauftrag aus Kundentermin erstellt'),
    'service.saved': t('Werkstattleistung gespeichert'),
    'vehicles.saved': t('Fahrzeugdaten gespeichert'),
    'deadlines.saved': t('Termin oder Frist gespeichert'),
    'deadline.completed': t('Termin oder Frist erledigt'),
    'mileage.confirmed': t('Kilometerstand bestätigt'),
    'order.created': t('Auftrag erstellt'),
    'order.plan': entry.details.after_employee_id ? t('Mitarbeiter für Auftrag vorgeplant') : t('Mitarbeiter-Vorplanung aufgehoben'),
    'order.release': t('Auftrag für die Werkstatt freigegeben'),
    'order.start': t('Arbeit gestartet – Zeiterfassung begonnen'),
    'order.pause': t('Arbeit pausiert – Zeiterfassung angehalten'),
    'order.finish': t('Arbeit fertig gemeldet – wartet auf Meisterabnahme'),
    'order.approve': t('Auftrag vom Meister abgenommen und abgeschlossen'),
    'order.cancel': t('Auftrag storniert'),
    'order.rework': t('Auftrag zur Nacharbeit zurückgegeben'),
    'part.requested': t('Teil für den Auftrag angefordert'),
    'part.ordered': t('Teil als bestellt markiert'),
    'part.received': t('Teil als eingetroffen markiert'),
    'part.rejected': t('Teileanforderung abgelehnt'),
    'part.cancelled': t('Teileanforderung storniert'),
    'defect.reported': t('Fahrer hat einen Mangel gemeldet'),
    'defect.updated': t('Mängelmeldung bearbeitet'),
    'link.rotated': t('Neuer Fahrerlink und QR-Code erstellt'),
    'link.revoked': t('Fahrerlinks und QR-Codes widerrufen'),
    'time.corrected': t('Arbeitszeit durch den Meister korrigiert'),
  }
  const lines: { label: string; value: string }[] = []
  const add = (label: string, value: unknown) => {
    if (typeof value === 'string' && value.trim()) lines.push({ label, value })
    else if (typeof value === 'number') lines.push({ label, value: String(value) })
  }
  const { context, details, action } = entry
  if (context?.order) add(t('Auftrag'), `${orderNumber(context.order.id)} · ${context.order.title}`)
  else if (entry.order_id) add(t('Auftrag'), orderNumber(entry.order_id))
  if (context?.employee) add(t('Mitarbeiter'), context.employee.name)
  else if (entry.employee_id) add(t('Mitarbeiter'), t('Name nicht mehr verfügbar'))
  if (action === 'order.plan') {
    add(t('Bisher vorgesehen'), details.before_employee_id ? context?.previous_employee?.name ?? t('Name nicht mehr verfügbar') : t('Niemand'))
    add(t('Jetzt vorgesehen'), details.after_employee_id ? context?.employee?.name ?? t('Name nicht mehr verfügbar') : t('Niemand'))
    add(t('Bedeutung'), t('Nur Vorplanung. Keine Arbeitszeit gestartet; Kollegen dürfen übernehmen.'))
  }
  if (action.startsWith('part.')) {
    if (context?.part) {
      const part = context.part
      add(t('Teil / Menge'), `${part.quantity} × ${part.title}`)
      add(t('Teilenummer'), part.part_number)
      add(
        t('Dringlichkeit'),
        { low: t('Niedrig'), normal: t('Normal'), urgent: t('Dringend') }[
          part.priority as 'low' | 'normal' | 'urgent'
        ],
      )
      add(t('Hinweis zur Teileanforderung'), part.note)
    } else add(t('Teileanforderung'), t('Angaben nicht mehr verfügbar'))
    if (details.paused === true)
      add(
        t('Arbeitsablauf'),
        t('Auftrag pausiert: Warten auf Teile. Die Zeiterfassung wurde angehalten.'),
      )
  }
  if (context?.defect) add(t('Mangel'), context.defect.description)
  if (context?.deadline) add(t('Termin / Frist'), context.deadline.title)
  if (action === 'mileage.confirmed')
    add(t('Kilometerstand'), typeof details.value === 'number' ? `${details.value} km` : '')
  if (action.startsWith('appointment.')) {
    add(t('Kundentermin'), details.title)
    if (typeof details.arrival === 'string')
      add(t('Ankunft'), Number.isFinite(Date.parse(details.arrival)) ? stamp(details.arrival) : t('Zeitpunkt nicht mehr verfügbar'))
    add(t('Grund für zusätzliche Annahme'), details.reason)
    if (details.order_unchanged === true) add(t('Arbeitsauftrag'), t('Der vorhandene Arbeitsauftrag bleibt unverändert.'))
    if (Array.isArray(details.checklist_changes)) {
      const labels = receptionLabels()
      const display = (v: unknown): string => typeof v === 'boolean' ? v ? t('Erledigt') : t('Nicht bestätigt') : typeof v === 'number' ? `${v} km` : typeof v === 'string' && v ? v : t('Keine Angabe')
      for (const change of details.checklist_changes) {
        if (change && typeof change === 'object' && typeof change.field === 'string' && Object.hasOwn(labels, change.field))
          add(labels[change.field as keyof ReceptionChecklist], `${display(change.before)} → ${display(change.after)}`)
      }
    }
  }
  add(t('Grund für zusätzliche Annahme'), details.override_reason)
  if (typeof details.date === 'string') add(t('Datum'), date(details.date))
  // Older clients sent a default pause reason even for start/approval. Do not mislabel it.
  if (['order.pause', 'order.cancel', 'order.rework', 'time.corrected'].includes(action))
    add(t('Grund'), details.reason)
  if (['order.pause', 'order.finish'].includes(action)) {
    add(t('Arbeitsnotiz'), details.note)
    if (Array.isArray(details.steps))
      add(
        t('Erledigte Arbeiten'),
        details.steps.filter((step): step is string => typeof step === 'string').join('\n'),
      )
  }
  if (action === 'revenue.saved') {
    add(t('Grund'), details.reason)
    for (const [key, label] of [['before', t('Bisherige Beträge')], ['after', t('Neue Beträge')]]) {
      const value = details[key]
      if (value && typeof value === 'object' && 'planned_net_cents' in value && 'actual_net_cents' in value) {
        const money = (v: unknown) => typeof v === 'number' ? new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(v / 100) : t('Nicht erfasst')
        add(label, `${t('Plan netto')}: ${money(value.planned_net_cents)} · ${t('Ist netto')}: ${money(value.actual_net_cents)}`)
        if ('revenue_date' in value && typeof value.revenue_date === 'string') add(t('Umsatzdatum'), date(value.revenue_date))
      }
    }
  }
  if (action === 'time.corrected') {
    for (const [key, label] of [
      ['before', t('Bisherige Buchung')],
      ['after', t('Korrigierte Buchung')],
    ]) {
      const value = details[key]
      if (value && typeof value === 'object' && 'started' in value && 'ended' in value) {
        if (typeof value.started === 'number' && typeof value.ended === 'number')
          add(label, `${stamp(value.started)} – ${stamp(value.ended)}`)
      }
    }
  }
  return {
    title: titles[action] || t('Eintrag im Fahrzeugprotokoll'),
    actor:
      entry.actor === 'driver'
        ? t('Über den Fahrerlink gemeldet')
        : t('Ausgeführt über Nextcloud-Konto: {account}', { account: entry.actor }),
    lines,
  }
}
