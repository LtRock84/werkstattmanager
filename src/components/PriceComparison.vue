<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { get, message } from '../api'
import { t } from '../format'
import { money, comparisonPrice } from '../services'
import type { PriceGroup, PriceObservation } from '../types'
const props = defineProps<{ serviceKey: string; selectable?: boolean; vehicleClass?: string }>()
const emit = defineEmits<{ select: [PriceGroup] }>()
const confirmed = ref<Record<string, boolean>>({})
const groups = ref<PriceGroup[]>([]), items = ref<PriceObservation[]>([]), error = ref(''), busy = ref(false)
let revision = 0
async function load() {
  confirmed.value = {}
  const n = ++revision; busy.value = true
  try { const result = await get<{ groups: PriceGroup[]; items: PriceObservation[] }>(`/api/v1/services/${encodeURIComponent(props.serviceKey)}/prices`); if (n === revision) { groups.value = result.groups; items.value = result.items; error.value = '' } }
  catch (e) { if (n === revision) error.value = message(e) }
  finally { if (n === revision) busy.value = false }
}
onMounted(load); watch(() => props.serviceKey, load)
</script>
<template>
  <section class="wm-price-comparison" :aria-label="t('Vergleich veröffentlichter Werkstattpreise')">
    <h3>{{ t('Vergleich veröffentlichter Werkstattpreise') }}</h3>
    <p>{{ t('Öffentliche Angebote zur Orientierung. Umfang, Material und Fahrzeug müssen übereinstimmen.') }}</p>
    <p class="wm-muted">{{ t('Unternehmensnamen und Anbieterlinks werden nicht veröffentlicht. Preisstand und Vergleichsumfang bleiben sichtbar.') }}</p>
    <p v-if="busy" role="status">{{ t('Preise werden geladen …') }}</p>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
    <p v-if="!busy && !groups.some(g => g.count >= 5)" class="wm-guide">{{ t('Noch kein belastbarer Vergleichspreis') }}</p>
    <article v-for="g in groups" :key="g.key" class="wm-card">
      <strong>{{ g.scope }} · {{ g.vehicle_class === 'van' ? t('Transporter') : t('PKW') }}</strong>
      <p>{{ g.region }} · {{ g.count }} {{ t('unabhängige Anbieter') }} · {{ g.material }} · {{ g.unit }}</p>
      <p v-if="g.mean !== null"><strong>{{ g.price_kind === 'from' ? t('Durchschnitt veröffentlichter Einstiegspreise') : t('Durchschnitt veröffentlichter Festpreise') }}: {{ money(g.mean) }}</strong></p>
      <p v-if="g.count >= 3">{{ t('Median') }}: {{ money(g.median) }} · {{ t('Spanne') }}: {{ money(g.min) }} – {{ money(g.max) }}</p>
      <p v-if="g.quality === 'limited'">{{ t('Kleine Datenbasis: eingeschränkter Vergleich, kein gesicherter regionaler Durchschnitt.') }}</p>
      <p v-if="g.undated">{{ t('Preisgültigkeit teilweise nicht bestätigt; siehe Prüfdatum der Angaben.') }}</p>
      <div v-if="selectable && comparisonPrice(g) && (!vehicleClass || vehicleClass === g.vehicle_class)">
        <p>{{ t('Vergleich brutto je genannter Einheit') }}: {{ money(g.mean) }} · {{ t('Umsatzsteuer') }}: {{ comparisonPrice(g)?.vat }} % · {{ t('Vergleich netto') }}: {{ money(comparisonPrice(g)?.net_cents) }}</p>
        <label class="wm-check"><input v-model="confirmed[g.key]" type="checkbox" />{{ t('Ich habe geprüft: Umfang, Einheit, Material und Fahrzeugklasse entsprechen genau einer Einheit meiner ausgewählten Leistung.') }}</label>
        <NcButton :disabled="!confirmed[g.key]" @click="emit('select', g)">{{ t('Vergleich als Zielpreis übernehmen') }}</NcButton>
        <p>{{ t('Der Zielpreis gilt für den gesamten genannten Umfang. Eigene Kosten, Material und Arbeitslohn bleiben in der Kalkulation separat sichtbar. Einstiegspreise bleiben als „ab“ gekennzeichnet.') }}</p>
      </div>
      <details class="wm-help">
<summary>{{ t('Einzelpreise und Prüfdatum anzeigen') }}</summary>
        <p v-for="o in g.observations" :key="o.id"><span>{{ t('Anonymisierter Preisgeber') }}</span> · {{ o.city }} · {{ o.price_kind === 'from' ? t('ab') : '' }} {{ money(o.gross_cents) }} · {{ t('geprüft am') }} {{ o.checked_date }}</p>
      </details>
    </article>
    <details v-if="items.some(o => !o.eligible)" class="wm-help">
<summary>{{ t('Weitere Angaben mit unklarem oder abweichendem Umfang') }}</summary>
      <p v-for="o in items.filter(o => !o.eligible)" :key="o.id"><span>{{ t('Anonymisierter Preisgeber') }}</span> · {{ o.scope }} · {{ money(o.gross_cents) }} · {{ o.exclusion }}</p>
    </details>
  </section>
</template>
