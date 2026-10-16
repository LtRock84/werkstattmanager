<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../format'
import type { Employee, Order } from '../types'
const props = defineProps<{ order: Order; employees: Employee[] }>()
const employee = computed(() => props.employees.find(e => e.id === props.order.planned_employee_id))
</script>
<template>
  <span v-if="order.planned_employee_id && ['draft', 'open', 'paused'].includes(order.status)" class="wm-employee-plan">
    <strong>{{ t('Vorgeplant:') }} {{ employee?.name ?? t('Mitarbeiter nicht mehr verfügbar') }}</strong>
    <span v-if="!employee || employee.archived">{{ t('Bitte neu planen. Dieser Mitarbeiter ist nicht mehr verfügbar.') }}</span>
    <span>{{ t('Nur ein Hinweis – Kollegen dürfen übernehmen. Die Vorplanung startet keine Arbeitszeit.') }}</span>
  </span>
</template>
