<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch, useId } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { t } from '../format'
import { confirmDiscard } from '../dirty'
const props = withDefaults(
  defineProps<{ title: string; dirty?: boolean; locked?: boolean; active?: boolean; closeLabel?: string }>(),
  { active: true, closeLabel: undefined },
)
function requestClose() {
  if (!props.locked && confirmDiscard(!!props.dirty))
    emit('close')
}
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement>()
const titleId = `wm-dialog-${useId()}`
let previous: HTMLElement | null = null
let sectionFocus: HTMLElement | null = null
function rememberFocus(event: FocusEvent) {
  if (event.target instanceof HTMLElement) sectionFocus = event.target
}
function synchronize() {
  if (props.active && !dialog.value?.open) {
    if (!previous)
      previous = document.activeElement instanceof HTMLElement ? document.activeElement : null
    const restore = sectionFocus
    dialog.value?.showModal()
    if (restore?.isConnected) restore.focus()
  } else if (!props.active) {
    if (
      document.activeElement instanceof HTMLElement &&
      dialog.value?.contains(document.activeElement)
    ) {
      sectionFocus = document.activeElement
    }
    dialog.value?.close()
  }
}
onMounted(synchronize)
watch(() => props.active, synchronize, { flush: 'post' })
onBeforeUnmount(() => {
  dialog.value?.close()
  previous?.focus()
})
</script>
<template>
  <dialog
    ref="dialog"
    class="wm-dialog"
    :class="{ 'wm-dialog--sectioned': !!$slots.navigation }"
    :aria-labelledby="titleId"
    @cancel.prevent="requestClose"
    @focusin="rememberFocus"
  >
    <header>
      <h2 :id="titleId">{{ title }}</h2>
      <NcButton :disabled="locked" :aria-label="closeLabel || t('Schließen')" @click="requestClose">
        {{ closeLabel || t('Schließen') }}
      </NcButton>
    </header>
    <template v-if="$slots.navigation">
      <div class="wm-dialog-navigation"><slot name="navigation" /></div>
      <div class="wm-dialog-content"><slot /></div>
    </template>
    <slot v-else />
  </dialog>
</template>
