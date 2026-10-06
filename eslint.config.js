import ts from 'typescript-eslint'
import vue from 'eslint-plugin-vue'
export default ts.config({ ignores: ['**/._*'] }, ...ts.configs.recommended, ...vue.configs['flat/recommended'], {
 files: ['**/*.vue'], languageOptions: { parserOptions: { parser: ts.parser } },
}, { rules: { 'vue/multi-word-component-names': 'off', 'vue/max-attributes-per-line': 'off', 'vue/singleline-html-element-content-newline': 'off', 'vue/html-indent': 'off', 'vue/html-self-closing': 'off' } })
