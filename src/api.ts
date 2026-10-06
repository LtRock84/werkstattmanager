import axios, { isAxiosError } from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type { Page, Table, Tables } from './types'
import { t } from './format'
import { requestId } from './request-id'
export const url = (path: string) => generateUrl(`/apps/werkstattmanager${path}`)
export async function get<T>(path: string): Promise<T> {
  return (await axios.get<T>(url(path))).data
}
export async function post<T>(path: string, data: unknown): Promise<T> {
  return (await axios.post<T>(url(path), data)).data
}
export async function put<T>(path: string, data: unknown): Promise<T> {
  return (await axios.put<T>(url(path), data)).data
}
export async function list<K extends Table>(
  kind: K,
  query: Record<string, string | number> = {},
): Promise<Page<Tables[K]>> {
  const params = new URLSearchParams(Object.entries(query).map(([k, v]) => [k, String(v)]))
  return get(`/api/v1/records/${kind}?${params}`)
}
export async function all<K extends Table>(
  kind: K,
  query: Record<string, string | number> = {},
): Promise<Tables[K][]> {
  const result: Tables[K][] = []
  for (let offset = 0; ; offset += 100) {
    const page = await list(kind, { ...query, offset })
    result.push(...page.items)
    if (!page.more) return result
  }
}
const pending = new Map<string, string>()
// Retry the same payload with the same key after an uncertain HTTP outcome.
export async function command<T>(path: string, payload: Record<string, unknown>): Promise<T> {
  const signature = path + JSON.stringify(payload)
  let request_id = pending.get(signature)
  if (!request_id) {
    request_id = requestId()
    pending.set(signature, request_id)
  }
  try {
    const result = await post<T>(path, { data: { ...payload, request_id } })
    pending.delete(signature)
    return result
  } catch (e) {
    if (isAxiosError(e) && e.response && e.response.status < 500) pending.delete(signature)
    throw e
  }
}
export async function upload(
  path: string,
  file: File,
  fields: Record<string, string>,
): Promise<{ id: string; name: string }> {
  const data = new FormData()
  data.append('file', file)
  Object.entries(fields).forEach(([k, v]) => data.append(k, v))
  return post(path, data)
}
export function message(e: unknown): string {
  if (isAxiosError<{ message?: string }>(e))
    return e.response?.data?.message || t('Verbindung fehlgeschlagen. Bitte erneut versuchen.')
  return e instanceof Error ? e.message : t('Vorgang fehlgeschlagen.')
}
