<script setup lang="ts">
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { post, message } from '../api'
import { companyProfile, type CompanyProfile } from '../company'
import { t } from '../format'
import { useDirtySnapshot } from '../dirty'
const name = ref(companyProfile.value.name), file = ref<File | null>(null), remove = ref(false), busy = ref(false), error = ref(''), notice = ref(''), input = ref<HTMLInputElement | null>(null)
const { reset } = useDirtySnapshot(() => [name.value, file.value?.name, remove.value])
function choose(event: Event) { file.value = (event.target as HTMLInputElement).files?.[0] ?? null; if (file.value) remove.value = false }
async function save() {
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const data = new FormData(); data.append('name', name.value); data.append('remove_logo', remove.value ? '1' : '0'); if (file.value) data.append('logo', file.value)
    companyProfile.value = await post<CompanyProfile>('/api/v1/company', data); name.value = companyProfile.value.name; file.value = null; remove.value = false; if (input.value) input.value.value = ''; reset(); notice.value = t('Firmenprofil gespeichert. Name und Logo sind im Appkopf sichtbar.')
  } catch (e) { error.value = message(e) } finally { busy.value = false }
}
</script>
<template>
  <form class="wm-card wm-form" @submit.prevent="save">
    <h2>{{ t('Firma und Logo') }}</h2>
    <p>{{ t('Firmenname und Logo erscheinen im oberen Bereich aller Werkstattansichten und auch im öffentlichen Fahrerformular. Bitte keine vertraulichen Angaben verwenden.') }}</p>
    <label>{{ t('Firmenname') }}<input v-model="name" maxlength="255" :disabled="busy" /></label>
    <label>{{ t('Firmenlogo (PNG, JPEG oder WebP, maximal 2 MB)') }}<input ref="input" type="file" accept="image/png,image/jpeg,image/webp" :disabled="busy" @change="choose" /></label>
    <label v-if="companyProfile.logo"><input v-model="remove" type="checkbox" :disabled="busy" />{{ t('Vorhandenes Logo entfernen') }}</label>
    <p v-if="error" role="alert" class="wm-error">{{ error }}</p><p v-if="notice" role="status">{{ notice }}</p>
    <NcButton :disabled="busy" type="submit">{{ t('Firmenprofil speichern') }}</NcButton>
  </form>
</template>
