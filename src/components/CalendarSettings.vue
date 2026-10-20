<script setup lang="ts">
import { ref, onMounted } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { get, put, message } from '../api'
import { t } from '../format'
const props = defineProps<{ admin: boolean }>()
const form = ref({ mode: 'native', native_enabled: true, calendar_url: '', calendar_user: '', has_password: false }), password = ref(''), error = ref(''), notice = ref(''), busy = ref(false), showLegacy = ref(false)
onMounted(async () => { try { form.value = await get('/api/v1/calendar-settings') } catch (e) { error.value = message(e) } })
async function save(remove = false) { if (!props.admin) return; busy.value = true; try { form.value = await put('/api/v1/calendar-settings', { data: { calendar_url: form.value.calendar_url, calendar_user: form.value.calendar_user, password: password.value, remove_password: remove } }); password.value = ''; notice.value = form.value.native_enabled ? t('Der automatische Werkstattkalender ist aktiv.') : t('Externer Kalenderzugang gespeichert. Ausstehende Zustellungen werden vom Hintergrundjob wiederholt.') } catch (e) { error.value = message(e) } finally { busy.value = false } }
async function useNative() { form.value.calendar_url = ''; form.value.calendar_user = ''; await save(true); showLegacy.value = false }
function legacyToggle(event: Event) { showLegacy.value = (event.target as HTMLDetailsElement).open }
</script>
<template>
  <section class="wm-card wm-form">
    <h2>{{ t('Werkstattkalender') }}</h2>
    <p v-if="form.native_enabled" role="status"><strong>{{ t('Automatisch eingerichtet') }}</strong> · {{ t('Meister und Administratoren sehen bestätigte Werkstatttermine direkt in der Nextcloud-Kalender-App. Ein zusätzliches Konto und App-Passwort sind nicht erforderlich.') }}</p>
    <p v-else role="status"><strong>{{ t('Externe Kalenderverbindung aktiv') }}</strong> · {{ t('Damit Termine nicht doppelt erscheinen, ist der automatische Werkstattkalender ausgeschaltet.') }}</p>
    <p>{{ t('Der Kalender ist schreibgeschützt. Termine werden weiterhin im Werkstattmanager angelegt, geändert und abgesagt.') }}</p>
    <p>{{ t('Kunden-E-Mails verwenden unabhängig davon den eingerichteten Werkstatt-Absender.') }}</p>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p><p v-if="notice" role="status">{{ notice }}</p>
    <div v-if="admin && !form.native_enabled">
      <p>{{ t('Bereits in den externen Kalender übertragene Einträge werden dabei nicht automatisch gelöscht. Entfernen Sie diese dort einmalig, damit Termine nicht doppelt erscheinen.') }}</p>
      <div class="wm-actions"><NcButton :disabled="busy" @click="useNative">{{ t('Auf automatischen Werkstattkalender umstellen') }}</NcButton></div>
    </div>
    <details v-if="admin" class="wm-help" :open="!form.native_enabled || showLegacy" @toggle="legacyToggle">
      <summary>{{ t('Erweiterte externe CalDAV-Anbindung (Altbestand)') }}</summary>
      <p>{{ t('Nur für bestehende Installationen: Eine externe Verbindung ersetzt den automatischen Kalender und benötigt ein eigenes Integrationskonto.') }}</p>
      <label>{{ t('Integrationskonto') }}<input v-model="form.calendar_user" autocomplete="off" /></label>
      <label>{{ t('CalDAV-Adresse des Kalenders (eigene Nextcloud, HTTPS)') }}<input v-model="form.calendar_url" type="url" autocomplete="off" /></label>
      <label>{{ t('App-Passwort (leer lassen, um das vorhandene zu behalten)') }}<input v-model="password" type="password" autocomplete="new-password" /></label>
      <p>{{ form.has_password ? t('Ein verschlüsseltes App-Passwort ist hinterlegt.') : t('Noch kein App-Passwort hinterlegt.') }}</p>
      <div class="wm-actions"><NcButton :disabled="busy" @click="save()">{{ t('Externen Kalenderzugang speichern') }}</NcButton><NcButton :disabled="busy || !form.has_password" @click="save(true)">{{ t('Gespeichertes App-Passwort entfernen') }}</NcButton></div>
    </details>
  </section>
</template>
