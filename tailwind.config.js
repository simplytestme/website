module.exports = {
  content: [
    "web/themes/simplytest_theme/templates/**/*.twig",
    "web/themes/simplytest_theme/lib/**/*.{js,jsx}",
    "web/modules/custom/*/templates/**/*.twig",
    "web/modules/custom/*/components/**/*.twig"
  ],
  theme: {
    extend: {
      colors: {
        // Design tokens from design_handoff_simplytest_redesign/README.md.
        st: {
          ink: "#071726",
          body: "#0b1b2b",
          slate: "#40515f",
          muted: "#5a6b7c",
          soft: "#7c8b9a",
          faint: "#97a6b5",
          sub: "#47596a",
          accent: "#0d8ae0",
          "accent-bright": "#35a8f0",
          "accent-dark": "#0a6cad",
          "accent-deep": "#0a4c78",
          "accent-tint": "#e8f4fd",
          "accent-tint2": "#f4fbff",
          "accent-line": "#cfe7f9",
          "accent-divider": "#d9ecfa",
          line: "#e7edf3",
          line2: "#e3ebf2",
          "field-line": "#dbe6ef",
          "button-line": "#cfe0ee",
          dash: "#c7d8e6",
          surface: "#fbfdfe",
          field: "#f7fafd",
          hairline2: "#eef3f8",
          "hero-from": "#eaf4fc",
          success: "#0f9b6c",
          danger: "#c0392b",
          "danger-bg": "#fdf6f5",
          "danger-line": "#f1cfca",
          "danger-text": "#6b2a22"
        }
      },
      fontFamily: {
        sans: [
          "Plus Jakarta Sans",
          "ui-sans-serif",
          "system-ui",
          "-apple-system",
          "Segoe UI",
          "sans-serif"
        ],
        mono: ["Space Mono", "ui-monospace", "SFMono-Regular", "Menlo", "monospace"]
      },
      boxShadow: {
        card: "0 1px 2px rgba(11,27,43,.04), 0 18px 40px -28px rgba(11,27,43,.35)",
        tile: "0 12px 30px -18px rgba(13,138,224,.55)",
        modal: "0 40px 90px -30px rgba(7,23,38,.55)"
      }
    }
  }
};
