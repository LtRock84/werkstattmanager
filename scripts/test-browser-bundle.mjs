// Execute the shipped IIFE without Node globals or component mocks.
// This checks startup, not layout or complete browser workflows.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { JSDOM } from 'jsdom'

const bundle = readFileSync(new URL('../js/werkstattmanager-main.js', import.meta.url), 'utf8')
for (const mode of ['master', 'tablet', 'driver', 'reception']) {
  const state = Buffer.from(JSON.stringify(mode)).toString('base64')
  const dom = new JSDOM(
    `<input type="hidden" id="initial-state-werkstattmanager-mode" value="${state}"><div id="werkstattmanager"></div>`,
    { url: 'http://localhost/', runScripts: 'outside-only' },
  )
  try {
    assert.equal(typeof dom.window.process, 'undefined')
    dom.window.eval(bundle)
    const root = dom.window.document.getElementById('werkstattmanager')
    assert.ok(root.children.length > 0, `${mode}: blank application root`)
    assert.ok(root.textContent.trim().length > 30, `${mode}: no visible application text`)
    assert.equal(root.querySelector('h1')?.textContent, {
      reception: 'Terminannahme', master: 'Übersicht', tablet: 'Werkstattaufträge', driver: 'Mangel melden',
    }[mode], `${mode}: expected role-specific main heading`)
    console.log(`PASS browser bundle startup: ${mode}`)
  } finally {
    // Close before background polling; this fixture never contacts a real server.
    dom.window.close()
  }
}
