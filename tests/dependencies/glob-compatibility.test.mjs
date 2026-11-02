import assert from 'node:assert/strict'
import test from 'node:test'
import { globSync } from 'glob'
import { Minimatch } from 'minimatch'
import { processGlobFilter } from 'webdav/dist/node/response.js'

test('patched glob dependencies retain the operations used by their consumers', () => {
  const files = [
    { filename: '/fahrzeuge/auftrag.pdf' },
    { filename: '/fahrzeuge/notiz.txt' },
    { filename: '/andere/rechnung.pdf' },
  ]
  assert.deepEqual(processGlobFilter(files, '*.pdf'), [files[0], files[2]])
  assert.equal(new Minimatch('src/**/*.vue').match('src/components/OrderDetail.vue'), true)
  assert.equal(new Minimatch('src/**/*.vue').match('src/components/OrderDetail.ts'), false)
  assert.ok(globSync('src/**/*.vue').includes('src/components/OrderDetail.vue'))
})
