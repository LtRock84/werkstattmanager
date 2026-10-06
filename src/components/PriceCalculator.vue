<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { get, post, message } from '../api'
import { useDirtySnapshot } from '../dirty'
import { t } from '../format'
import { money, comparisonPrice } from '../services'
import type { CostProfile, Calculation, ServiceLine, ServiceDefinition, PriceGroup } from '../types'
import ServicePicker from './ServicePicker.vue'
import PriceComparison from './PriceComparison.vue'
const categories = [ ['personnel', t('Lohn einschließlich Arbeitgeberkosten')], ['owner', t('Unternehmerlohn')], ['rent', t('Miete')], ['heat', t('Heizung')], ['electricity', t('Strom')], ['insurance', t('Versicherungen')], ['tools', t('Werkzeuge / Abschreibungen')], ['software', t('Software')], ['other', t('Sonstige Betriebskosten')] ]
const profile = ref<CostProfile>({ name: t('Werkstatt'), costs: Object.fromEntries(categories.map(([k]) => [k, 0])), billable_hours: 3000, profit_percent: 20, material_percent: 25, vat_percent: 19 })
const lines = ref<ServiceLine[]>([]), result = ref<Calculation | null>(null), error = ref(''), notice = ref(''), busy = ref(false)
const loading = ref(true)
const fingerprint = () => JSON.stringify({ profile: profile.value, lines: lines.value })
const { dirty: profileDirty, reset } = useDirtySnapshot(() => profile.value)
const { dirty: linesDirty } = useDirtySnapshot(() => lines.value)
const dirty = computed(() => profileDirty.value || linesDirty.value)
watch([profile, lines], () => { result.value = null }, { deep: true, flush: 'sync' })
const annual = computed(() => Object.values(profile.value.costs).reduce((a, b) => a + b, 0))
onMounted(async () => { try { const r = await get<{ profile: CostProfile | null }>('/api/v1/pricing'); if (r.profile) profile.value = r.profile; reset() } catch (e) { error.value = message(e) } finally { loading.value = false } })
function cost(key: string, raw: string) { profile.value.costs[key] = Math.round(Number(raw) * 100) }
async function calculate(save: boolean) {
  if (busy.value || loading.value) return
  const expected = fingerprint()
  busy.value = true; error.value = ''; notice.value = ''
  try { const r = await post<Calculation>('/api/v1/pricing', { save, data: { profile: profile.value, lines: lines.value.map(l => ({ ...l, material_cents: Math.round((l.material_euros ?? 0) * 100), external_cents: Math.round((l.external_euros ?? 0) * 100) })) } }); if (expected !== fingerprint()) { notice.value = t('Eingaben wurden geändert. Bitte erneut berechnen.'); return } if (r.profile) { profile.value = r.profile; notice.value = t('Kostenprofil gespeichert.'); reset() } result.value = r }
  catch (e) { error.value = message(e) } finally { busy.value = false }
}
function selectComparison(index: number, group: PriceGroup) {
  const value = comparisonPrice(group)
  if (value && group.vehicle_class === lines.value[index].vehicle_class) { lines.value[index].comparison = value; notice.value = t('Vergleich als Zielpreis übernommen. Die eigene Kostenkalkulation bleibt erhalten.') }
}
async function adopt(index: number, reference = false) {
  if (busy.value) return
  const expected = fingerprint()
  const line = JSON.parse(JSON.stringify(lines.value[index])) as ServiceLine, cents = reference ? line.comparison?.net_cents : result.value?.lines[index]?.net_cents
  if (!line.service_id || cents == null) return
  busy.value = true
  try { const list = await get<{ items: ServiceDefinition[] }>('/api/v1/services'); const s = list.items.find(s => s.id === line.service_id); if (!s) throw new Error(t('Leistung nicht mehr vorhanden.')); if (expected !== fingerprint()) throw new Error(t('Auswahl wurde geändert. Bitte erneut berechnen.')); await post('/api/v1/services', { data: { ...s, own_price: reference ? cents : Math.round(cents / line.quantity) } }); notice.value = t('Nettopreis ausdrücklich in den Leistungskatalog übernommen. Bestehende Aufträge bleiben unverändert.') }
  catch (e) { error.value = message(e) } finally { busy.value = false }
}
</script>
<template>
  <section class="wm-form">
<p v-if="dirty" role="status">{{ t('Ungespeicherte Eingaben bleiben beim Wechsel innerhalb der App erhalten. Beim Verlassen der Seite geht die nicht gespeicherte Auswahl verloren.') }}</p>
<fieldset :disabled="busy || loading" class="wm-calculation-fields">
<p class="wm-guide">{{ t('Jahreskosten durch tatsächlich abrechenbare Jahresstunden teilen. Anwesenheit und Terminbudget sind nicht automatisch abrechenbare Stunden. Eingekaufte Teile hier nicht als allgemeine Betriebskosten nochmals eintragen.') }}</p>
    <details class="wm-help" :open="!profile.id"><summary>{{ t('1. Jahreskosten und Stundensatz') }}</summary><div class="wm-two"><label v-for="[key, label] in categories" :key="key">{{ label }} · {{ t('Euro pro Jahr') }}<input type="number" min="0" step="0.01" :value="(profile.costs[key] ?? 0) / 100" @input="cost(key, ($event.target as HTMLInputElement).value); result = null" /></label></div><p>{{ t('Jährliche Betriebskosten') }}: <strong>{{ money(annual) }}</strong></p><div class="wm-two"><label>{{ t('Abrechenbare Stunden pro Jahr') }}<input v-model.number="profile.billable_hours" type="number" min="1" @input="result = null" /></label><label>{{ t('Gewinnaufschlag auf Kosten (%)') }}<input v-model.number="profile.profit_percent" type="number" min="0" @input="result = null" /></label><label>{{ t('Materialaufschlag (%)') }}<input v-model.number="profile.material_percent" type="number" min="0" @input="result = null" /></label><label>{{ t('Umsatzsteuer (%)') }}<input v-model.number="profile.vat_percent" type="number" min="0" max="100" @input="result = null" /></label></div><p>{{ t('Gewinnaufschlag ist ein Aufschlag auf die Kosten, keine Umsatzrendite. Beispielwerte bitte an euren Betrieb anpassen.') }}</p></details>
    <ServicePicker v-model="lines" @update:model-value="result = null" />
    <div v-for="(l, i) in lines" :key="i" class="wm-card"><h3>{{ l.title }}</h3><div class="wm-two"><label>{{ t('Material-Einkauf je Einheit (netto Euro)') }}<input v-model.number="l.material_euros" type="number" min="0" step="0.01" @input="result = null" /></label><label>{{ t('Fremdleistungen je Einheit (netto Euro)') }}<input v-model.number="l.external_euros" type="number" min="0" step="0.01" @input="result = null" /></label></div><details v-if="l.key" class="wm-help"><summary>{{ t('Regionalen Vergleichspreis auswählen') }}</summary><PriceComparison :service-key="l.key" :vehicle-class="l.vehicle_class" selectable @select="selectComparison(i, $event)" /></details><div v-if="l.comparison" class="wm-guide"><p>{{ t('Übernommener Zielpreis je Einheit netto') }}: {{ money(l.comparison.net_cents) }} · {{ l.comparison.region }} · {{ l.comparison.scope }} · {{ l.comparison.material }} · {{ l.comparison.unit }} · {{ l.comparison.price_kind === 'from' ? t('ab') : t('Festpreis') }}</p><p>{{ t('Zielpreis für die gewählte Menge netto') }}: {{ money(l.comparison.net_cents * l.quantity) }}<span v-if="result?.lines[i]"> · {{ t('Abstand zur eigenen Kalkulation netto') }}: {{ money(l.comparison.net_cents * l.quantity - result.lines[i].net_cents) }}</span></p><NcButton :disabled="busy || !l.service_id" @click="adopt(i, true)">{{ t('Zielpreis ausdrücklich in Katalog speichern') }}</NcButton><NcButton @click="delete l.comparison">{{ t('Zielpreis entfernen') }}</NcButton></div><p v-if="result?.lines[i]">{{ t('Kalkulierter Nettopreis') }}: {{ money(result.lines[i].net_cents) }} <NcButton :disabled="busy || !l.service_id" @click="adopt(i)">{{ t('Preis in Katalog übernehmen') }}</NcButton></p></div>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p><p v-if="notice" role="status" class="wm-success">{{ notice }}</p><div class="wm-actions"><NcButton :disabled="busy" variant="primary" @click="calculate(false)">{{ t('Preise berechnen') }}</NcButton><NcButton :disabled="busy" @click="calculate(true)">{{ t('Berechnen und Kostenprofil speichern') }}</NcButton></div>
    <article v-if="result" class="wm-card"><h2>{{ t('Ergebnis') }}</h2><p>{{ t('Kostendeckender Netto-Stundensatz') }}: <strong>{{ money(result.cost_rate_cents) }}</strong></p><p>{{ t('Verkaufssatz netto einschließlich Gewinnaufschlag') }}: <strong>{{ money(result.rate_cents) }}</strong></p><p>{{ t('Gesamter Leistungspreis netto') }}: {{ money(result.net_cents) }}</p><p>{{ t('Umsatzsteuer') }}: {{ money(result.vat_cents) }}</p><p>{{ t('Gesamter Leistungspreis brutto') }}: <strong>{{ money(result.gross_cents) }}</strong></p><p>{{ t('Vergleichspreise sind öffentliche Bruttopreise; die eigenen Katalogpreise werden netto gespeichert.') }}</p></article>
</fieldset>
  </section>
</template>
