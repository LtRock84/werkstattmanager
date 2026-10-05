<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { t } from '../format'
const props = defineProps<{ title: string; dirty?: boolean }>()
function requestClose() {
  if (!props.dirty || window.confirm(t('Ihre Eingaben sind noch nicht gespeichert. Möchten Sie wirklich schließen und die Eingaben verwerfen?'))) emit('close')
}
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement>()
let previous: HTMLElement | null = null
onMounted(() => {
  previous = document.activeElement instanceof HTMLElement ? document.activeElement : null
  dialog.value?.showModal()
})
onBeforeUnmount(() => {
  dialog.value?.close()
  previous?.focus()
})
</script>
<template>
  <dialog
    ref="dialog"
    class="wm-dialog"
    aria-labelledby="wm-dialog-title"
    @cancel.prevent="requestClose"
  >
    <header>
      <h2 id="wm-dialog-title">{{ title }}</h2>
      <NcButton :aria-label="t('Schließen')" @click="requestClose">{{ t('Schließen') }}</NcButton>
    </header>
    <slot />
  </dialog>
</template>
