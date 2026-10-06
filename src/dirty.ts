import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { t } from './format'

export function confirmDiscard(dirty: boolean): boolean {
  return !dirty || window.confirm(t('Ihre Eingaben sind noch nicht gespeichert. Möchten Sie wirklich schließen und die Eingaben verwerfen?'))
}

export function useDirtySnapshot(source: () => unknown) {
  const snapshot = () => JSON.stringify(source(), (_key, value) => value === '' ? null : value)
  const baseline = ref(snapshot())
  const dirty = computed(() => snapshot() !== baseline.value)
  const reset = () => { baseline.value = snapshot() }
  const leaving = (event: BeforeUnloadEvent) => {
    if (dirty.value) { event.preventDefault(); event.returnValue = '' }
  }
  onMounted(() => window.addEventListener('beforeunload', leaving))
  onBeforeUnmount(() => window.removeEventListener('beforeunload', leaving))
  return { dirty, reset }
}
