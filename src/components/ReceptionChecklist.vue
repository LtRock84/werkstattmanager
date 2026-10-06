<script setup lang="ts">
import { t } from '../format'
import type { ReceptionChecklist } from '../types'
import { receptionLabels } from '../planning'
const model = defineModel<ReceptionChecklist>({ required: true })
const reception = ['key_received', 'documents_checked', 'damage_checked', 'work_agreed'] as const
const returnChecks = ['final_checked', 'customer_informed', 'key_returned'] as const
const labels = receptionLabels()
</script>
<template>
  <details class="wm-help">
    <summary>{{ t('Fahrzeugannahme, Rückfrage und Abholung') }}</summary>
    <p>{{ t('Haken erst setzen, wenn der Schritt durchgeführt wurde. Die Angaben werden mit dem Termin gespeichert und bleiben intern. Sie ersetzen keine technische Freigabe oder Kundenunterschrift.') }}</p>
    <fieldset>
<legend>{{ t('Bei der Annahme') }}</legend>
      <label v-for="key in reception" :key="key" class="wm-check"><input v-model="model[key]" type="checkbox" />{{ labels[key] }}</label>
      <label>{{ t('Kilometerstand bei Annahme (optional)') }}<input v-model.number="model.mileage" type="number" min="0" max="10000000" /></label>
      <p>{{ t('Dokumentiert nur diesen Termin. Den Fahrzeugkilometerstand bitte separat in der Fahrzeugakte bestätigen.') }}</p>
      <label>{{ t('Vorhandene Schäden / übergebenes Zubehör') }}<textarea v-model="model.condition_note" maxlength="2000" rows="2"></textarea></label>
    </fieldset>
    <label>{{ t('Rückfrage / Rückrufnotiz') }}<textarea v-model="model.callback_note" maxlength="2000" rows="2" :placeholder="t('Zum Beispiel: Freigabe für Zusatzarbeit erfragen; Kunde ab 14 Uhr erreichbar.')"></textarea></label>
    <p>{{ t('Wenn eine Antwort aussteht, zusätzlich den Terminstand „Rückfrage beim Kunden“ wählen. Es wird keine Nachricht automatisch verschickt.') }}</p>
    <fieldset>
<legend>{{ t('Vor und bei der Abholung') }}</legend>
      <label v-for="key in returnChecks" :key="key" class="wm-check"><input v-model="model[key]" type="checkbox" />{{ labels[key] }}</label>
    </fieldset>
  </details>
</template>
