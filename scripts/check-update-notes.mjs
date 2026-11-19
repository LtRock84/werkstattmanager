import { readFileSync } from 'node:fs'
const version = JSON.parse(readFileSync('package.json', 'utf8')).version
const notes = readFileSync('src/components/UpdateNotice.vue', 'utf8')
const changelog = readFileSync('CHANGELOG.md', 'utf8')
if (!notes.includes(`const notesVersion = '${version}'`) || !changelog.includes(`## ${version} `)) {
  throw new Error('Für diese Appversion müssen Update-Dialog und CHANGELOG.md aktualisiert werden.')
}
