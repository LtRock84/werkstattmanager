<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { useDirtySnapshot } from '../dirty'
import { get, message } from '../api'
import { t } from '../format'
import { serviceLine, lineMinutes, includedWork } from '../services'
import type { ServiceDefinition, ServiceLine } from '../types'
import PriceComparison from './PriceComparison.vue'
const props = defineProps<{ modelValue: ServiceLine[]; vehicleClass?: 'car' | 'van'; hidePrices?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [ServiceLine[]]; 'draft-change': [boolean] }>()
const definitions = ref<ServiceDefinition[]>([]), query = ref(''), category = ref(''), showAll = ref(false), error = ref(''), priceKey = ref('')
const customTitle = ref(''), customMinutes = ref<number | null>(null)
const { dirty: pendingWork } = useDirtySnapshot(() => ({ title: customTitle.value, minutes: customMinutes.value }))
watch(pendingWork, value => emit('draft-change', value))
const customValid = computed(() => !!customTitle.value.trim() && (customMinutes.value === null || String(customMinutes.value) === '' || Number.isInteger(customMinutes.value) && customMinutes.value >= 1 && customMinutes.value <= 100000))
function addCustom() {
  if (!customValid.value) return
  emit('update:modelValue', [...props.modelValue, { service_id: '', key: '', title: customTitle.value.trim().slice(0,255), quantity: 1, minutes: customMinutes.value == null || String(customMinutes.value) === '' ? null : customMinutes.value, unit: t('Leistung'), steps: [customTitle.value.trim()], vehicle_class: props.vehicleClass ?? 'car' }])
  customTitle.value = ''; customMinutes.value = null
}
const categories = computed(() => [...new Set(definitions.value.map(s => s.category))].sort())
const results = computed(() => {
  const rows = definitions.value.filter(s => !s.archived && s.vehicle_classes.includes(props.vehicleClass ?? 'car') && (!category.value || s.category === category.value) && (!query.value || [s.title, ...s.synonyms].join(' ').toLocaleLowerCase().includes(query.value.toLocaleLowerCase())))
  rows.sort((a, b) => (b.usage_count ?? 0) - (a.usage_count ?? 0) || Number(b.favorite) - Number(a.favorite) || a.position - b.position || a.title.localeCompare(b.title, 'de') || a.id.localeCompare(b.id))
  return query.value || category.value || showAll.value ? rows : rows.slice(0, 8)
})
const total = computed(() => lineMinutes(props.modelValue))
const warnings = computed(() => includedWork(props.modelValue, definitions.value))
onMounted(async () => { try { definitions.value = (await get<{ items: ServiceDefinition[] }>('/api/v1/services')).items } catch (e) { error.value = message(e) } })
watch(() => props.vehicleClass, (value, previous) => {
  if (previous && value !== previous) emit('update:modelValue', props.modelValue.map(l => ({ ...l, vehicle_class: value ?? 'car', minutes: null })))
})
function add(s: ServiceDefinition) { emit('update:modelValue', [...props.modelValue, serviceLine(s, props.vehicleClass)]) }
function update(index: number, field: 'quantity' | 'minutes', raw: string) {
  emit('update:modelValue', props.modelValue.map((l, i) => i === index ? { ...l, [field]: raw === '' ? null : Number(raw) } : l))
}
function steps(index: number, raw: string) { emit('update:modelValue', props.modelValue.map((l, i) => i === index ? { ...l, steps: raw.split('\n').map(x => x.trim()).filter(Boolean) } : l)) }
function remove(i: number) { emit('update:modelValue', props.modelValue.filter((_, index) => index !== i)) }
</script>
<template>
  <fieldset class="wm-service-picker">
    <legend>{{ t('Leistungen auswählen') }}</legend>
    <p>{{ t('Mehrere Arbeiten hinzufügen. Die geplante Arbeitszeit wird addiert. Unbekannte Zeiten bitte ergänzen.') }}</p>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
    <div class="wm-two">
      <label>{{ t('Arbeit suchen') }}<input v-model="query" type="search" :placeholder="t('Zum Beispiel Ölwechsel, Reifen oder Bremsen')" /></label>
      <label>{{ t('Bereich') }}<select v-model="category"><option value="">{{ t('Alle Bereiche') }}</option><option v-for="c in categories" :key="c">{{ c }}</option></select></label>
    </div>
    <NcButton :aria-pressed="showAll" @click="showAll = !showAll">{{ showAll ? t('Häufige Leistungen anzeigen') : t('Gesamten Katalog anzeigen') }}</NcButton>
    <p v-if="!query && !category && !showAll">{{ t('Die acht meistgenutzten Leistungen stehen zuerst. Bestätigte Termine und freigegebene Aufträge zählen einmal je Vorgang; Entwürfe und abgesagte Vorgänge zählen nicht. Bei gleicher Nutzung entscheiden Favoriten und Sortierung.') }}</p>
    <div class="wm-service-results">
      <div v-for="s in results" :key="s.id" class="wm-service-result">
        <NcButton @click="add(s)">+ {{ s.title }}</NcButton>
        <small v-if="s.usage_count">{{ t('In {count} Vorgängen verwendet', { count: s.usage_count }) }}</small>
        <NcButton v-if="!hidePrices" :aria-label="t('Vergleichspreise anzeigen') + ': ' + s.title" @click="priceKey = priceKey === s.seed_key ? '' : s.seed_key">{{ t('Preise / Quelle') }}</NcButton>
      </div>
      <p v-if="!results.length">{{ t('Keine passende Leistung. Unter „Eigene Arbeit hinzufügen“ direkt ergänzen.') }}</p>
    </div>
    <details class="wm-help"><summary>{{ t('Eigene Arbeit hinzufügen') }}</summary><p>{{ t('Nur für diesen Vorgang. Der Leistungskatalog wird nicht verändert.') }}</p><label>{{ t('Bezeichnung der eigenen Arbeit') }}<input v-model="customTitle" maxlength="255" /></label><label>{{ t('Arbeitsdauer der eigenen Arbeit (Minuten)') }}<input v-model.number="customMinutes" type="number" min="1" max="100000" /></label><NcButton :disabled="!customValid" @click="addCustom">{{ t('Eigene Arbeit übernehmen') }}</NcButton></details>
    <PriceComparison v-if="priceKey && !hidePrices" :service-key="priceKey" />
    <div v-for="(l, i) in modelValue" :key="i" class="wm-service-line">
      <strong>{{ l.title }}</strong><div class="wm-two">
        <label>{{ t('Menge') }}<input type="number" min="1" max="1000" :value="l.quantity" @input="update(i, 'quantity', ($event.target as HTMLInputElement).value)" /></label>
        <label>{{ t('Arbeitszeit je Einheit (Minuten)') }}<input type="number" min="1" max="100000" :value="l.minutes" :placeholder="t('Bitte ergänzen')" @input="update(i, 'minutes', ($event.target as HTMLInputElement).value)" /></label>
      </div><details class="wm-help"><summary>{{ t('Arbeitsschritte und Zeitquelle') }}</summary><label>{{ t('Arbeitsschritte – eine Zeile je Schritt') }}<textarea :value="l.steps.join('\n')" @input="steps(i, ($event.target as HTMLTextAreaElement).value)"></textarea></label><p>{{ definitions.find(s => s.id === l.service_id)?.description }}</p><p v-if="definitions.find(s => s.id === l.service_id)?.time_source"><a :href="definitions.find(s => s.id === l.service_id)?.time_source" target="_blank" rel="noopener noreferrer">{{ t('Quelle der PKW-Zeitvorgabe') }}</a></p><p>{{ t('Planungswert, keine verbindliche Herstellerzeit. Transporterzeiten bitte selbst festlegen.') }}</p></details>
      <NcButton @click="remove(i)">{{ t('Entfernen') }}</NcButton>
    </div>
    <p v-for="w in warnings" :key="w" role="status" class="wm-error">{{ t('Möglicherweise bereits enthalten. Doppelte Arbeit entfernen oder Zeiten anpassen:') }} {{ w }}</p>
    <p v-if="modelValue.length" role="status"><strong>{{ t('Gesamte geplante Arbeitszeit') }}: {{ total === null ? t('Zeitangaben ergänzen') : total + ' min' }}</strong></p>
  </fieldset>
</template>
