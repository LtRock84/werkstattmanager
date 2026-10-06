<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue'
import { all } from '../api'
import { t, orderNumber, statusText } from '../format'
import type { Order } from '../types'
const props = defineProps<{ vehicleId: string; excludeOrderId?: string | null }>()
const orders = ref<Order[]>([]), failed = ref(false)
let revision = 0
watch(() => [props.vehicleId, props.excludeOrderId], async () => {
  const n = ++revision; orders.value = []; failed.value = false
  if (!props.vehicleId) return
  try {
    const rows = await all('orders', { vehicle_id: props.vehicleId })
    if (n === revision) orders.value = rows.filter(o => o.id !== props.excludeOrderId && ['draft', 'open', 'active', 'paused', 'review'].includes(o.status))
  } catch { if (n === revision) failed.value = true }
}, { immediate: true })
onBeforeUnmount(() => { ++revision })
</script>
<template>
  <aside v-if="orders.length || failed" class="wm-guide" role="status">
    <template v-if="orders.length"><strong>{{ t('Für dieses Fahrzeug gibt es bereits unerledigte Aufträge:') }} {{ orders.length }}</strong><ul><li v-for="o in orders.slice(0, 3)" :key="o.id">{{ orderNumber(o.id) }} · {{ o.title }} · {{ statusText(o.status) }}</li></ul><p>{{ t('Bitte prüfen, ob die Arbeiten zusammengehören, bevor ein zusätzlicher Auftrag entsteht. Vorhandene Aufträge werden nicht automatisch geändert.') }}</p></template>
    <p v-else>{{ t('Vorhandene Aufträge konnten nicht geprüft werden. Bitte bei Bedarf in der Fahrzeugakte nachsehen.') }}</p>
  </aside>
</template>
