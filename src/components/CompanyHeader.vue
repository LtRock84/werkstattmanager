<script setup lang="ts">
import { companyProfile } from '../company'
import { url } from '../api'
import { t } from '../format'
</script>
<template>
  <div v-if="companyProfile.name || companyProfile.logo" class="wm-company-header" :aria-label="t('Werkstatt')">
    <img v-if="companyProfile.logo" :src="url('/public/v1/company/logo/' + companyProfile.logo)" :alt="companyProfile.name ? '' : t('Firmenlogo')" />
    <strong v-if="companyProfile.name">{{ companyProfile.name }}</strong>
  </div>
</template>
