function escapeHtml(value: string): string {
  return value.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] ?? c))
}

/** The template and customer fields are plain text, never executable markup. */
export function pickupPrintHtml(title: string, body: string, qr: string, qrLabel: string): string {
  return `<!doctype html><html lang="de"><head><meta charset="utf-8"><title>${escapeHtml(title)}</title><style>body{font-family:system-ui,sans-serif;color:#111;margin:0;padding:24px}main{max-width:640px;margin:auto}h1{font-size:1.5rem}p{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.5}img{display:block;width:75mm;max-width:90%;height:auto;margin:20px auto}@media print{body{padding:0}main{max-width:none}}</style></head><body><main><h1>${escapeHtml(title)}</h1><p>${escapeHtml(body)}</p><img src="${escapeHtml(qr)}" alt="${escapeHtml(qrLabel)}"></main></body></html>`
}
