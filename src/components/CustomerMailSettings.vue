<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { get, put, message } from '../api'
import { t } from '../format'
import { useDirtySnapshot, confirmDiscard } from '../dirty'
interface Template { label: string; subject: string; body: string }
interface Settings { version: number; reminder_days: number; templates: Record<string, Template>; placeholders: string[] }
const form = ref<Settings | null>(null), baseline = ref<Settings | null>(null)
const selected = ref('confirm'), busy = ref(false), error = ref(''), notice = ref('')
const current = computed(() => form.value?.templates[selected.value])
const { dirty, reset } = useDirtySnapshot(() => form.value)
async function load() {
  if (!confirmDiscard(dirty.value)) return
  busy.value = true; error.value = ''; notice.value = ''
  try { form.value = await get<Settings>('/api/v1/mail-templates'); baseline.value = JSON.parse(JSON.stringify(form.value)); reset() }
  catch (e) { error.value = message(e) } finally { busy.value = false }
}
async function save() {
  if (!form.value) return
  busy.value = true; error.value = ''; notice.value = ''
  try { form.value = await put<Settings>('/api/v1/mail-templates', { data: form.value }); baseline.value = JSON.parse(JSON.stringify(form.value)); reset(); notice.value = t('Kundennachrichten gespeichert. Die Vorlagen gelten für noch nicht versendete Nachrichten.') }
  catch (e) { error.value = message(e) } finally { busy.value = false }
}
function undo() { if (baseline.value && confirmDiscard(dirty.value)) { form.value = JSON.parse(JSON.stringify(baseline.value)); reset() } }
onMounted(load)
</script>
<template>
  <section class="wm-card wm-form" :aria-busy="busy">
    <h2>{{ t('Kundennachrichten und Fristenerinnerungen') }}</h2>
    <p>{{ t('Die fertigen Texte werden automatisch mit Kundenname, Fahrzeug und Termindaten ausgefüllt. Sie können Betreff und Text anpassen. Es werden reine Textnachrichten versendet; Platzhalter sind keine Programmbefehle.') }}</p>
    <template v-if="form">
      <label>{{ t('Fristenerinnerung: Tage vor Fälligkeit') }}<input v-model.number="form.reminder_days" type="number" min="1" max="365" :disabled="busy" /></label>
      <p class="wm-guide">{{ t('Standard: 30 Tage vorher. Bei einer jährlichen Inspektion ungefähr elf Monate nach der letzten Erledigung, bei einer zweijährlichen HU ungefähr 23 Monate danach. Maßgeblich ist immer die tatsächliche nächste Fälligkeit. Kilometerfristen werden erst bei erreichtem bestätigtem Kilometerstand erinnert; Fahrleistung wird nicht geschätzt. Bereits überfällige Fristen werden nach Aktivierung ebenfalls einmal gemeldet.') }}</p>
      <label>{{ t('Vorlage auswählen') }}<select v-model="selected" :disabled="busy"><option v-for="(item, key) in form.templates" :key="key" :value="key">{{ t(item.label) }}</option></select></label>
      <template v-if="current">
        <label>{{ t('Betreff') }}<input v-model="current.subject" maxlength="255" required :disabled="busy" /></label>
        <label>{{ t('Nachrichtentext') }}<textarea v-model="current.body" rows="12" maxlength="10000" required :disabled="busy" /></label>
      </template>
      <p>{{ t('Verfügbare Platzhalter') }}: <code v-for="key in form.placeholders" :key="key" class="wm-mail-token">{{ '{' + key + '}' }}</code></p>
      <p>{{ t('Fristenerinnerungen lassen sich bei jedem Kunden ein- oder ausschalten. Neue Kunden sind vorausgewählt; bei bestehenden Kunden wurde beim Update keine automatische E-Mail aktiviert. Im Kundentermin wählen Sie die Abholbenachrichtigung separat. „Abholbereit“ stellt keine automatische technische Fahrzeugfreigabe dar.') }}</p>
      <div class="wm-actions"><NcButton :disabled="busy || !dirty" @click="save">{{ t('Kundennachrichten speichern') }}</NcButton><NcButton :disabled="busy || !dirty" @click="undo">{{ t('Änderungen verwerfen') }}</NcButton><NcButton :disabled="busy" @click="load">{{ t('Neu laden') }}</NcButton></div>
    </template>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p><p v-if="notice" role="status">{{ notice }}</p>
  </section>
</template>
<style scoped>
.wm-mail-token { display: inline-block; padding: 0.2rem 0.4rem; margin: 0.2rem; background: var(--color-background-dark); border-radius: var(--border-radius); }
</style>
