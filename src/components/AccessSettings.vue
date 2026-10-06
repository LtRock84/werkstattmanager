<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { get, put, message } from '../api'
import { t } from '../format'
type User = { id: string; name: string }
type Grants = { master_group: string; tablet_group: string; reception_group: string; master_users: User[]; reception_users: User[] }
const props = defineProps<{ admin: boolean }>()
const data = ref<Grants | null>(null), query = ref(''), results = ref<User[]>([]), target = ref<'master_users' | 'reception_users'>('reception_users'), busy = ref(false), error = ref(''), notice = ref('')
let revision = 0, timer: ReturnType<typeof setTimeout> | undefined
async function load() {
  if (!props.admin) return
  try { data.value = await get<Grants>('/api/v1/access-settings') } catch (e) { error.value = message(e) }
}
watch(() => props.admin, admin => { if (admin) void load() }, { immediate: true })
watch(query, value => {
  clearTimeout(timer); const n = ++revision; results.value = []
  if (value.trim().length < 2) return
  timer = setTimeout(async () => {
    try { const r = await get<{ items: User[] }>(`/api/v1/access-users?q=${encodeURIComponent(value)}`); if (n === revision) results.value = r.items }
    catch (e) { if (n === revision) error.value = message(e) }
  }, 250)
})
onBeforeUnmount(() => { ++revision; clearTimeout(timer) })
function add(u: User) {
  if (!data.value) return
  data.value.master_users = data.value.master_users.filter(x => x.id !== u.id)
  data.value.reception_users = data.value.reception_users.filter(x => x.id !== u.id)
  data.value[target.value].push(u); query.value = ''; notice.value = t('Zuordnung vorgemerkt. Zum Anwenden Zugänge speichern.'); error.value = ''
}
async function save() {
  if (!data.value || busy.value) return
  busy.value = true; error.value = ''; notice.value = ''
  try { await put('/api/v1/access-settings', { data: { ...data.value, master_users: data.value.master_users.map(x => x.id), reception_users: data.value.reception_users.map(x => x.id) } }); notice.value = t('Zugänge gespeichert. Die Rechte gelten ab dem nächsten Zugriff.'); await load() }
  catch (e) { error.value = message(e) } finally { busy.value = false }
}
</script>
<template>
  <section class="wm-card wm-form">
    <h2>{{ t('Zugänge für Meister und Terminannahme') }}</h2>
    <p>{{ t('Meisterzugang: vollständige Werkstattverwaltung einschließlich Kunden, Zeiten, Abnahme, Preisen und Export. Zum Beispiel für Geschäftsleitung. Terminannahme: ausschließlich Kundentermine vereinbaren, ändern und absagen; vorhandene Kunden und Fahrzeuge auswählen. Neue Stammdaten legt der Meister an.') }}</p>
    <p>{{ t('Nextcloud-Administratoren besitzen bereits Meisterrechte. Bei mehrfachen Zuordnungen hat der Meisterzugang Vorrang. Wer nur Termine vereinbaren soll, darf daher weder Administrator noch Mitglied der Meistergruppe sein.') }}</p>
    <p v-if="!admin">{{ t('Ein Nextcloud-Administrator kann hier zusätzliche Benutzer freigeben.') }}</p>
    <template v-if="admin && data">
      <fieldset :disabled="busy">
<legend>{{ t('Gruppen aus Nextcloud') }}</legend>
        <label>{{ t('Meistergruppe') }}<input v-model="data.master_group" required /></label>
        <label>{{ t('Tabletgruppe') }}<input v-model="data.tablet_group" required /></label>
        <label>{{ t('Terminannahmegruppe (optional)') }}<input v-model="data.reception_group" /></label>
        <p>{{ t('Gruppen müssen in Nextcloud existieren. Alternativ einzelne Benutzer unten hinzufügen. Eine leere Terminannahmegruppe erteilt niemandem Gruppenrechte.') }}</p>
      </fieldset>
      <fieldset :disabled="busy">
<legend>{{ t('Einzelne Nextcloud-Benutzer zusätzlich freigeben') }}</legend>
        <label>{{ t('Zugang für ausgewählten Benutzer') }}<select v-model="target"><option value="reception_users">{{ t('Nur Terminannahme') }}</option><option value="master_users">{{ t('Vollständiger Meisterzugang') }}</option></select></label>
        <label>{{ t('Benutzer suchen (mindestens zwei Zeichen)') }}<input v-model="query" type="search" /></label>
        <div class="wm-actions"><NcButton v-for="u in results" :key="u.id" @click="add(u)">+ {{ u.name }} ({{ u.id }})</NcButton></div>
        <p v-if="query.length >= 2 && !results.length">{{ t('Keine Suchergebnisse geladen. Namen oder Benutzernamen prüfen.') }}</p>
        <template v-for="item in (['master_users', 'reception_users'] as const)" :key="item">
          <h3>{{ item === 'master_users' ? t('Zusätzliche Meisterbenutzer') : t('Zusätzliche Terminannahmebenutzer') }}</h3>
          <ul><li v-for="u in data[item]" :key="u.id">{{ u.name }} ({{ u.id }}) <NcButton :aria-label="t('Zugang entfernen') + ': ' + u.name" @click="data[item] = data[item].filter(x => x.id !== u.id)">{{ t('Entfernen') }}</NcButton></li></ul>
          <p v-if="!data[item].length">{{ t('Keine zusätzlichen Benutzer zugeordnet.') }}</p>
        </template>
      </fieldset>
      <NcButton variant="primary" :disabled="busy" @click="save">{{ busy ? t('Wird gespeichert …') : t('Zugänge speichern') }}</NcButton>
    </template>
    <p v-if="notice" role="status" class="wm-success">{{ notice }}</p><p v-if="error" role="alert" class="wm-error">{{ error }}</p>
  </section>
</template>
