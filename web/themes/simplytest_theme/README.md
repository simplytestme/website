# SimplyTest theme

The launch form and progress page are React apps (`lib/`), mounted into Drupal
at `#launcher_mount` and `#progress_mount`. Styling is Tailwind, with the
design tokens defined in the repo-root `tailwind.config.js`; the design
reference lives in `design_handoff_simplytest_redesign/`.

Everything builds with Vite from the repository root:

```
npm install
npm run build    # writes dist/app.js, dist/style.css and the font files
npm run watch    # rebuild on change
```

`dist/` is not committed; deploys build it in `lagoon/cli.dockerfile` and CI
builds it before running Cypress.

The header, hero, and footer are plain Twig in `templates/layout/` — nothing
in this theme is configured through blocks or theme settings.
