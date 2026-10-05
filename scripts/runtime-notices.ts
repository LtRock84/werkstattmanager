import { existsSync, readFileSync, readdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import type { Plugin } from 'vite'

/** Inventory only packages that contribute code to the final bundle. */
export function runtimeNotices(): Plugin {
  return {
    name: 'werkstatt-runtime-notices',
    generateBundle(_options, bundle) {
      const packages = new Map<
        string,
        { name: string; version: string; license: string; repository?: string | { url: string } }
      >()
      const copyrights = new Map<string, Set<string>>()
      for (const output of Object.values(bundle)) {
        if (output.type !== 'chunk') continue
        for (const [id, module] of Object.entries(output.modules)) {
          if (!id.includes('/node_modules/') || module.renderedLength === 0) continue
          let dir = dirname(id.split('?')[0])
          while (dir.includes('/node_modules')) {
            const file = join(dir, 'package.json')
            if (existsSync(file)) {
              const metadata = JSON.parse(readFileSync(file, 'utf8'))
              if (metadata.name) {
                packages.set(dir, metadata)
                const sourceFile = id.split('?')[0]
                if (existsSync(sourceFile)) {
                  const headers = readFileSync(sourceFile, 'utf8').match(/SPDX-FileCopyrightText:[^\r\n]*/g) ?? []
                  const collected = copyrights.get(dir) ?? new Set<string>()
                  for (const header of headers) collected.add(header)
                  copyrights.set(dir, collected)
                }
                break
              }
            }
            dir = dirname(dir)
          }
        }
      }
      const notices: string[] = [
        'Werkstattmanager: bundled third-party software.\nProvide corresponding source and preserve these notices when distributing.',
      ]
      for (const [dir, pkg] of [...packages.entries()].sort((a, b) =>
        a[1].name.localeCompare(b[1].name),
      )) {
        const repository =
          typeof pkg.repository === 'string' ? pkg.repository : (pkg.repository?.url ?? '')
        notices.push(
          `\n${'='.repeat(72)}\n${pkg.name} ${pkg.version}\nSPDX: ${pkg.license}\nSource: ${repository}`,
        )
        notices.push(...[...(copyrights.get(dir) ?? [])].sort())
        const files = readdirSync(dir).filter((name) =>
          /^(licen[sc]e|copying|notice)(\.|$)/i.test(name),
        )
        for (const name of files) notices.push(readFileSync(join(dir, name), 'utf8'))
        if (!files.length) {
          const canonical = pkg.license === 'AGPL-3.0-or-later' ? 'LICENSE'
            : pkg.license === 'GPL-3.0-or-later' ? 'licenses/GPL-3.0.txt' : null
          if (!canonical) this.error(`Missing license text for ${pkg.name}`)
          notices.push(readFileSync(canonical, 'utf8'))
        }
      }
      this.emitFile({
        type: 'asset',
        fileName: 'third-party-notices.txt',
        source: notices.join('\n'),
      })
    },
  }
}
