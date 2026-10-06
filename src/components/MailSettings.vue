<script setup lang="ts">
import { ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { get, put, message } from '../api'
import { t } from '../format'
import { useDirtySnapshot } from '../dirty'
const props = defineProps<{ admin: boolean }>()
type Settings = { selection: { mode: string; owner?: string; service?: string; address?: string }; accounts: { id: string; address: string; label: string }[] }
const view = ref<Settings | null>(null), mode = ref('system'), service = ref(''), busy = ref(false), error = ref(''), notice = ref('')
const { reset } = useDirtySnapshot(() => [mode.value, service.value])
watch(() => props.admin, async allowed => { if (!allowed) return; try { view.value = await get<Settings>('/api/v1/mail-settings'); mode.value = view.value.selection.mode; service.value = view.value.selection.service ?? ''; reset() } catch (e) { error.value = message(e) } }, { immediate: true })
async function save() { busy.value = true; error.value = ''; notice.value = ''; try { view.value = await put<Settings>('/api/v1/mail-settings', { data: { mode: mode.value, service: service.value } }); reset(); notice.value = t('Werkstatt-Absender gespeichert. Die Auswahl gilt für alle Kundennachrichten und Erinnerungen.') } catch (e) { error.value = message(e) } finally { busy.value = false } }
</script>
<template>
  <section class="wm-card wm-form">
<h2>{{ t('Werkstatt-E-Mailadresse') }}</h2>
    <p>{{ t('Eine gemeinsame Werkstattadresse für Terminbestätigungen, Änderungen, Absagen und Erinnerungen. Ein Administrator wählt ein eigenes, in Nextcloud Mail eingerichtetes Konto aus und erlaubt dessen Nutzung für alle Werkstatttermine.') }}</p>
    <p v-if="!admin">{{ t('Die Absenderadresse richtet ein Nextcloud-Administrator hier ein.') }}</p>
    <template v-else>
      <p v-if="view?.selection.address">{{ t('Aktuelle Werkstattadresse') }}: <strong>{{ view.selection.address }}</strong></p>
      <label>{{ t('Versandweg') }}<select v-model="mode" :disabled="busy"><option value="system">{{ t('Nextcloud-Systemversand (bisherige SMTP-Einstellungen)') }}</option><option value="mail">{{ t('Gemeinsames Konto aus Nextcloud Mail') }}</option></select></label>
      <label v-if="mode === 'mail'">{{ t('Eigenes Mailkonto für die Werkstatt') }}<select v-model="service" :disabled="busy"><option value="">{{ t('Bitte auswählen') }}</option><option v-for="a in view?.accounts" :key="a.id" :value="a.id">{{ a.address }} · {{ a.label }}</option></select></label>
      <p v-if="mode === 'mail' && !view?.accounts.length">{{ t('Keine eigenen sendefähigen Konten verfügbar. Bitte Nextcloud Mail aktivieren und dort die Werkstattadresse einrichten.') }}</p>
      <p>{{ t('Zugangsdaten bleiben in Nextcloud Mail. Bei entferntem oder gesperrtem Konto erfolgt kein automatischer Wechsel zum Systemversand. Nach Übergabe an Mail den Postausgang und Versandstatus im Mailclient prüfen; die Werkstatt-App erhält keine Zustellbestätigung.') }}</p>
      <NcButton :disabled="busy || !view || (mode === 'mail' && !service)" @click="save">{{ t('Werkstatt-Absender speichern') }}</NcButton>
    </template>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p><p v-if="notice" role="status">{{ notice }}</p>
  </section>
</template>
