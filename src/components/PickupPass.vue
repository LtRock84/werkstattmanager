<script setup lang="ts">
import { onMounted, ref } from 'vue'
import CompanyHeader from './CompanyHeader.vue'
import { get, message, post } from '../api'
import { t, vehicleLabel } from '../format'
import type { PickupPublicView } from '../types'

const view = ref<PickupPublicView | null>(null)
const error = ref('')
const busy = ref(true)

onMounted(async () => {
  try {
    const token = location.hash.slice(1)
    history.replaceState(null, '', location.pathname + location.search)
    view.value = token
      ? await post('/public/v1/pickup/session', { token })
      : await get('/public/v1/pickup')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
})
</script>

<template>
  <main class="wm-driver wm-pickup-public">
    <header><CompanyHeader /><span class="wm-eyebrow">{{ t('DIGITALER ABHOLSCHEIN') }}</span><h1>{{ t('Fahrzeugabholung') }}</h1></header>
    <p v-if="busy" role="status">{{ t('Abholcode wird geprüft …') }}</p>
    <p v-else-if="error" role="alert" class="wm-error">{{ error }}</p>
    <section v-else-if="view" class="wm-card">
      <p class="wm-pickup-name">{{ view.customer_name }}</p>
      <h2>{{ vehicleLabel(view.vehicle) }}</h2>
      <p :class="view.ready ? 'wm-pickup-ready' : 'wm-pickup-wait'">
        {{ view.ready ? t('Das Fahrzeug ist abholbereit.') : t('Das Fahrzeug ist noch nicht zur Abholung freigegeben.') }}
      </p>
      <p class="wm-muted">{{ t('Bitte zeigen Sie diese Seite bei der Abholung vor. Der Code ist übertragbar und wird nach Abholung oder Widerruf ungültig.') }}</p>
      <p class="wm-muted">{{ t('Dieser Abholschein ist keine technische Freigabe oder Aussage zur Verkehrssicherheit.') }}</p>
    </section>
  </main>
</template>
