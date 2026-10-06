import { ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
export type CompanyProfile = { name: string; logo: string }
const initial = loadState<CompanyProfile>('werkstattmanager', 'company', { name: '', logo: '' })
export const companyProfile = ref<CompanyProfile>(initial && typeof initial === 'object' ? initial : { name: '', logo: '' })
