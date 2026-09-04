// Shared Tailwind class stacks for the design system.
// Tokens live in tailwind.config.js; specs in
// design_handoff_simplytest_redesign/README.md.

export const btnPrimary =
  'rounded-[10px] bg-st-accent px-[26px] py-[15px] text-base font-bold text-white hover:bg-st-accent-dark disabled:cursor-not-allowed disabled:opacity-50';

export const btnPrimarySm =
  'rounded-lg bg-st-accent px-3.5 py-[11px] text-sm font-semibold text-white hover:bg-st-accent-dark disabled:cursor-not-allowed disabled:opacity-50';

export const btnSecondary =
  'rounded-lg border border-st-button-line bg-white px-4 py-[13px] text-sm font-semibold text-st-accent-deep hover:border-st-accent hover:text-st-accent disabled:cursor-not-allowed disabled:opacity-50';

export const btnSecondarySm =
  'rounded-lg border border-st-button-line bg-white px-3.5 py-[11px] text-sm font-semibold text-st-accent-deep hover:border-st-accent hover:text-st-accent disabled:cursor-not-allowed disabled:opacity-50';

export const btnDashed =
  'rounded-lg border border-dashed border-st-button-line bg-white px-3.5 py-[9px] text-[13px] font-semibold text-st-accent-dark hover:border-st-accent';

// The big search-card input.
export const inputHero =
  'w-full rounded-[10px] border border-st-field-line bg-st-field px-4 py-3.5 text-[17px] font-medium text-st-body';

export const selectHero =
  'w-full rounded-[10px] border border-st-field-line bg-st-field px-3 py-3.5 text-base font-medium text-st-body';

// Controls inside the grouped advanced-options panels (white on #fbfdfe).
export const inputPanel =
  'w-full rounded-lg border border-st-field-line bg-white px-3.5 py-[11px] text-[15px] text-st-body';

export const selectPanel =
  'w-full rounded-lg border border-st-field-line bg-white px-3 py-[11px] text-[15px] text-st-body';

export const removeButton =
  'h-[42px] w-[42px] flex-none rounded-lg border border-st-line bg-white text-sm text-st-soft hover:text-st-body';

export const dangerBlock =
  'rounded-[14px] border border-st-danger-line bg-st-danger-bg px-5 py-4 text-sm text-st-danger-text';
