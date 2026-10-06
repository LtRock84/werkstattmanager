import { spawnSync } from 'node:child_process'
import { readdirSync } from 'node:fs'
import { join } from 'node:path'
const native = spawnSync('php', ['-v'], { encoding: 'utf8' })
const wasm = process.env.WM_PHP_WASM_CLI
const command = native.status === 0 ? 'php' : process.execPath
const prefix = native.status === 0 ? [] : wasm ? [wasm] : ['node_modules/@php-wasm/cli/php-wasm.js']
function run(args) {
  const result = spawnSync(command, [...prefix, ...args], { encoding: 'utf8' })
  process.stdout.write(result.stdout ?? '')
  process.stderr.write(result.stderr ?? '')
  const expected = args[0] === '-l' ? 'No syntax errors detected' : 'unit scenarios passed.'
  if (
    result.error ||
    result.status !== 0 ||
    !result.stdout?.includes(expected) ||
    /^(FAIL |Fatal error:|Parse error:)/m.test(`${result.stdout}\n${result.stderr}`)
  ) {
    console.error('PHP verification did not finish successfully.')
    process.exit(1)
  }
}
function walk(dir) {
  return readdirSync(dir, { withFileTypes: true }).flatMap((e) =>
    e.isDirectory() ? walk(join(dir, e.name)) : !e.name.startsWith('._') && e.name.endsWith('.php') ? [join(dir, e.name)] : [],
  )
}
for (const file of ['lib', 'appinfo', 'templates', 'tests/php'].flatMap(walk)) run(['-l', file])
run(['tests/php/run.php'])
