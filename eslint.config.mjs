// Trimmed from Drupal Canvas's config: the same React, Prettier and Cypress
// presets, without the TypeScript, Vitest and Playwright layers this repo has
// no use for.
import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import chaiFriendly from 'eslint-plugin-chai-friendly';
import cypress from 'eslint-plugin-cypress';
import mocha from 'eslint-plugin-mocha';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import { defineConfig } from 'eslint/config';
import globals from 'globals';

const drupalGlobals = {
  Drupal: 'readonly',
  drupalSettings: 'readonly',
  once: 'readonly',
};

export default defineConfig([
  {
    ignores: ['**/dist/**'],
  },
  js.configs.recommended,
  prettier,
  {
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
    },
  },
  // The launch form.
  {
    files: ['web/themes/simplytest_theme/lib/**/*.{js,jsx}'],
    extends: [
      react.configs.flat.recommended,
      react.configs.flat['jsx-runtime'],
      reactHooks.configs.flat.recommended,
    ],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...drupalGlobals,
      },
    },
    settings: {
      react: {
        version: 'detect',
      },
    },
    rules: {
      // React 19 no longer checks propTypes on function components, so the
      // declarations this rule asks for would do nothing at runtime.
      'react/prop-types': 'off',
      // Setting state from an effect is how the launcher context reacts to
      // URL parameters and project selection. Canvas turns this off too.
      'react-hooks/set-state-in-effect': 'off',
      'no-unused-vars': ['error', { args: 'none', caughtErrors: 'none' }],
    },
  },
  // Cypress specs and support files. All but the entry point use require().
  {
    files: ['cypress/**/*.js'],
    extends: [cypress.configs.recommended, mocha.configs.recommended],
    plugins: {
      'chai-friendly': chaiFriendly,
    },
    languageOptions: {
      sourceType: 'commonjs',
      globals: {
        ...globals.node,
      },
    },
    rules: {
      ...chaiFriendly.configs.recommendedFlat.rules,
      'mocha/no-mocha-arrows': 'off',
      'mocha/no-top-level-hooks': 'off',
      'mocha/max-top-level-suites': 'off',
      'mocha/no-exclusive-tests': 'error',
    },
  },
  // The Cypress entry point is the one ES module in that tree.
  {
    files: ['cypress/support/e2e.js'],
    languageOptions: {
      sourceType: 'module',
    },
  },
  // Build tooling at the repo root. Vite's config is the one ES module,
  // which its .mjs extension says.
  {
    files: ['*.js', '*.mjs'],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
  },
  {
    files: ['cypress.config.js', 'postcss.config.js', 'tailwind.config.js'],
    languageOptions: {
      sourceType: 'commonjs',
    },
  },
]);
