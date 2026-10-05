import { copyFileSync, mkdirSync } from 'node:fs'
mkdirSync('css', { recursive: true })
copyFileSync('js/werkstattmanager-werkstattmanager.css', 'css/main.css')
