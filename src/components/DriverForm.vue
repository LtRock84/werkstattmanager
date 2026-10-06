<script setup lang="ts">
import { ref, onMounted } from 'vue'
import CompanyHeader from './CompanyHeader.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { get, post, upload, message } from '../api'
import { t, vehicleLabel, stamp } from '../format'
import type { DriverView } from '../types'
import { requestId as newRequestId } from '../request-id'
const view = ref<DriverView | null>(null),
  error = ref(''),
  notice = ref(''),
  busy = ref(false),
  description = ref(''),
  category = ref('other'),
  priority = ref('normal'),
  reporter = ref(''),
  mileage = ref<number | null>(null),
  files = ref<File[]>([]),
  received = ref<string | null>(null)
const requestId = ref(newRequestId())
const photoInput = ref<HTMLInputElement | null>(null)
onMounted(async () => {
  busy.value = true
  try {
    const token = location.hash.slice(1)
    history.replaceState(null, '', location.pathname + location.search)
    view.value = token
      ? await post('/public/v1/session', { token })
      : await get('/public/v1/vehicle')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
})
function selectFiles(e: Event) {
  files.value = Array.from((e.target as HTMLInputElement).files ?? [])
  if (files.value.length > 5) error.value = t('Maximal fünf Fotos auswählen.')
}
async function submit() {
  if (busy.value) return
  if (!received.value && !description.value.trim()) {
    error.value = t('Bitte kurz beschreiben, was am Fahrzeug aufgefallen ist.')
    return
  }
  busy.value = true
  error.value = ''
  notice.value = ''
  try {
    if (files.value.length > 5 || files.value.some((f) => f.size > 8 * 1024 * 1024))
      throw new Error(t('Maximal fünf Fotos mit jeweils höchstens 8 MB.'))
    if (!received.value) {
      const r = await post<{ id: string }>('/public/v1/defects', {
        data: {
          request_id: requestId.value,
          description: description.value,
          category: category.value,
          priority: priority.value,
          reporter: reporter.value,
          mileage: mileage.value,
        },
      })
      received.value = r.id
    }
    while (files.value.length) {
      await upload('/public/v1/photos', files.value[0], { defect_id: received.value })
      files.value.shift()
    }
    if (photoInput.value) photoInput.value.value = ''
    notice.value = t('Ihre Meldung ist in der Werkstatt eingegangen. Vielen Dank.')
    description.value = ''
    reporter.value = ''
    mileage.value = null
    received.value = null
    requestId.value = newRequestId()
    view.value = await get('/public/v1/vehicle')
  } catch (e) {
    error.value =
      (received.value
        ? t('Meldung gespeichert. Noch nicht bestätigte Fotos können erneut hochgeladen werden. ')
        : '') + message(e)
  } finally {
    busy.value = false
  }
}
async function more() {
  if (!view.value) return
  try {
    const p = await get<DriverView>('/public/v1/vehicle?offset=' + view.value.items.length)
    view.value.items.push(...p.items)
    view.value.more = p.more
  } catch (e) {
    error.value = message(e)
  }
}
</script>
<template>
  <main class="wm-driver">
    <header>
      <CompanyHeader />
      <span class="wm-eyebrow">{{ t('WERKSTATTMANAGER') }}</span>
      <h1>{{ t('Mangel melden') }}</h1>
      <p v-if="view" class="wm-vehicle-title">{{ vehicleLabel(view.vehicle) }}</p>
      <p>{{ t('Ihre Meldung wird direkt dem Fahrzeug zugeordnet.') }}</p>
    </header>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p>
    <p v-if="notice" role="status" class="wm-success">{{ notice }}</p>
    <template v-if="view">
      <section class="wm-card">
        <h2>{{ t('Bereits bekannte offene Mängel') }}</h2>
        <p v-if="!view.items.length" class="wm-muted">
          {{ t('Die Werkstatt hat bisher keine offenen Mängel für Fahrer veröffentlicht.') }}
        </p>
        <article v-for="(d, n) in view.items" :key="n" class="wm-log">
          <p>{{ d.text }}</p>
          <small>{{ stamp(d.created_at) }} · {{ t('Offen') }}</small>
        </article>
        <NcButton v-if="view.more" @click="more">{{ t('Weitere laden') }}</NcButton>
        <p class="wm-muted">{{ t('Diese Liste ist keine technische Freigabe des Fahrzeugs.') }}</p>
      </section>
      <form class="wm-form wm-card" @submit.prevent="submit">
        <h2>{{ t('Was ist am Fahrzeug aufgefallen?') }}</h2>
        <p class="wm-muted">
          {{
            t(
              'Eine kurze Beschreibung reicht. Fotos, Name und Kilometerstand sind freiwillig. Zum Schluss auf „Meldung absenden“ tippen.',
            )
          }}
        </p>
        <fieldset :disabled="busy || !!received">
          <label>{{ t('Bereich')
            }}<select v-model="category">
              <option value="engine">{{ t('Motor / Antrieb') }}</option>
              <option value="brakes">{{ t('Bremsen') }}</option>
              <option value="tires">{{ t('Reifen') }}</option>
              <option value="lights">{{ t('Beleuchtung') }}</option>
              <option value="body">{{ t('Karosserie') }}</option>
              <option value="other">{{ t('Sonstiges / ich bin unsicher') }}</option>
            </select></label><label>{{ t('Beschreibung (Pflichtfeld)')
            }}<textarea
              v-model="description"
              required
              maxlength="10000"
              rows="5"
              :placeholder="
                t('Bitte kurz beschreiben, was nicht funktioniert und wann es auftritt.')
              "
            ></textarea></label><label>{{ t('Dringlichkeit')
            }}<select v-model="priority">
              <option value="normal">{{ t('Normal') }}</option>
              <option value="urgent">{{ t('Dringend – bitte schnell prüfen') }}</option>
            </select></label>
          <div class="wm-two">
            <label>{{ t('Kilometerstand (optional)')
              }}<input v-model.number="mileage" type="number" min="0" max="10000000" /></label><label>{{ t('Ihr Name (optional)')
              }}<input v-model="reporter" maxlength="255" autocomplete="name" /></label>
          </div>
        </fieldset>
        <label>{{ t('Fotos: maximal fünf, jeweils 8 MB und 12 Megapixel')
          }}<input
            ref="photoInput"
            type="file"
            multiple
            accept="image/jpeg,image/png,image/webp"
            :disabled="busy"
            @change="selectFiles"
        /></label>
        <ul class="wm-file-list">
          <li v-for="(f, n) in files" :key="n">
            <span>{{ f.name }}</span><NcButton
              :disabled="busy"
              :aria-label="t('Foto entfernen') + ': ' + f.name"
              @click="files.splice(n, 1)"
              >
{{ t('Entfernen') }}
</NcButton>
          </li>
        </ul>
        <details class="wm-help">
          <summary>{{ t('Welche Fotos kann ich verwenden?') }}</summary>
          <p>
            {{
              t(
                'JPEG, PNG oder WebP, höchstens 8 MB und 12 Megapixel je Foto. Bei einer Fehlermeldung ein kleineres Bild wählen. Sie können den Mangel auch ohne Foto melden.',
              )
            }}
          </p>
        </details>
        <p class="wm-muted">
          {{
            t(
              'Die Werkstatt erhält Ihre Angaben und Fotos. Ihre Originalmeldung wird nicht automatisch öffentlich angezeigt.',
            )
          }}
        </p>
        <NcButton
          variant="primary"
          :disabled="busy || (!received && !description.trim())"
          @click="submit"
        >
          {{
            busy
              ? t('Wird gesendet …')
              : received
                ? t('Fotos erneut senden')
                : t('Meldung absenden')
          }}
        </NcButton>
      </form>
    </template>
    <p v-else-if="busy" role="status">{{ t('Fahrzeug wird geladen …') }}</p>
    <p v-else>{{ t('Bitte den gültigen QR-Code am Fahrzeug öffnen.') }}</p>
  </main>
</template>
