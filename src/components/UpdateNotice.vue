<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { get, post, message } from '../api'
import { t } from '../format'
const notesVersion = '0.6.3'
const state = ref<{ version: string; pending: boolean } | null>(null), busy = ref(false), error = ref('')
const dialog = ref<HTMLDialogElement>()
async function load() {
  busy.value = true; error.value = ''
  try {
    state.value = await get('/api/v1/update-notice')
    if (state.value?.pending) { await nextTick(); dialog.value?.showModal() }
  } catch (e) { error.value = message(e) } finally { busy.value = false }
}
async function confirm() {
  if (!state.value || busy.value) return
  busy.value = true; error.value = ''
  try {
    await post('/api/v1/update-notice', { version: state.value.version })
    dialog.value?.close(); state.value = null
  } catch (e) { error.value = message(e) } finally { busy.value = false }
}
onMounted(load)
</script>
<template>
  <dialog v-if="state?.pending" ref="dialog" class="wm-dialog" aria-labelledby="wm-update-title" @cancel.prevent>
    <h2 id="wm-update-title">{{ t('Neu im Werkstattmanager') }} · {{ state.version }}</h2>
    <template v-if="state.version === notesVersion">
      <h3>{{ t('Neue Funktionen') }}</h3>
      <ul><li>{{ t('Fahrer-QR-Codes erneut anzeigen und drucken.') }}</li><li>{{ t('Auftragstermine ändern und mehrere Vorlagen wählen.') }}</li></ul>
      <h3>{{ t('Änderungen und Fehlerbehebungen') }}</h3>
      <ul><li>{{ t('Große Fahrerfotos werden vor dem Hochladen automatisch verkleinert.') }}</li><li>{{ t('Beginn und Fertigstellung sind bei der Auftragserstellung direkt sichtbar.') }}</li><li>{{ t('Ältere Fahrer-QR-Codes bleiben aktiv, können aber erst nach ihrem Austausch erneut angezeigt werden.') }}</li></ul>
    </template>
    <p v-else>{{ t('Informationen zu dieser Version finden Sie im Änderungsprotokoll der App.') }}</p>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
    <NcButton variant="primary" :disabled="busy" @click="confirm">{{ t('Gelesen und bestätigen') }}</NcButton>
  </dialog>
  <p v-else-if="error" role="alert" class="wm-error">{{ t('Update-Hinweise konnten nicht geladen werden.') }} {{ error }} <NcButton :disabled="busy" @click="load">{{ t('Erneut versuchen') }}</NcButton></p>
</template>
