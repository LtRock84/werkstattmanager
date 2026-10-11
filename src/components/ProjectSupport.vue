<script setup lang="ts">
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import Modal from './Modal.vue'
import { t } from '../format'
import { PROJECT_SUPPORT_URL } from '../project-support'

const open = ref(false)
// No settings, initial state or incoming URL parameters can replace the compiled destination.
const destination = PROJECT_SUPPORT_URL
</script>

<template>
  <section v-if="destination" class="wm-project-support" :aria-label="t('Projekt unterstützen')">
    <NcButton @click="open = true">{{ t('Projekt unterstützen') }}</NcButton>
    <small>{{ t('Freiwilliger Beitrag zu den Projektkosten') }}</small>
    <Modal v-if="open" :title="t('Werkstattmanager unterstützen')" @close="open = false">
      <p>{{ t('Ihr Beitrag hilft, die laufenden Kosten und die Weiterentwicklung des Werkstattmanagers zu finanzieren.') }}</p>
      <p>{{ t('Die Unterstützung ist freiwillig. Die App bleibt kostenlos nutzbar. Eine Zahlung schaltet keine zusätzlichen Funktionen frei und begründet keinen Anspruch auf individuelle Leistungen.') }}</p>
      <p class="wm-help">{{ t('Es wird keine Spendenbescheinigung ausgestellt. Die Unterstützung ist keine steuerbegünstigte Spende.') }}</p>
      <p class="wm-help">{{ t('Erst wenn Sie zu PayPal wechseln, verlassen Sie Ihre Nextcloud. Die App übermittelt keine Kunden-, Fahrzeug- oder Auftragsdaten und speichert keine Zahlungsdaten. Prüfen Sie den Empfänger und den Betrag auf PayPal, bevor Sie zahlen.') }}</p>
      <div class="wm-actions">
        <NcButton :href="destination" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" variant="primary">
          {{ t('Zu PayPal (neuer Tab)') }}
        </NcButton>
        <NcButton @click="open = false">{{ t('Zurück zur Werkstatt') }}</NcButton>
      </div>
    </Modal>
  </section>
</template>
