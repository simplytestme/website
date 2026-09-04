import { useState } from 'react';

import { useLauncher } from '../context/launcher';
import AdditionalProjects from './AdditionalProjects';
import DrupalCoreVersionSelector from './DrupalCoreVersionSelector';
import { ManualInstallCheckbox, SelectProfile } from './InstallationOptions';
import Patches from './Patches';

function OptionGroup({ number, title, description, children }) {
  return (
    <section className="flex flex-col gap-6 rounded-xl border border-st-line bg-st-surface px-6 py-[22px] lg:flex-row lg:gap-10">
      <div className="flex flex-col gap-1.5 lg:w-[210px] lg:flex-none">
        <span className="font-mono text-[11px] uppercase tracking-[0.12em] text-st-accent-dark">
          {number} · {title}
        </span>
        <p className="m-0 text-[13px] leading-normal text-st-soft">
          {description}
        </p>
      </div>
      <div className="flex min-w-0 flex-1 flex-col items-start gap-3">
        {children}
      </div>
    </section>
  );
}

function AdvancedOptions() {
  const { canLaunch, patches, setPatches } = useLauncher();
  // Deep links (?project=…) land with intent to tweak, so open the panel.
  const [open, setOpen] = useState(window.location.search.length > 0);
  const expanded = open && canLaunch;

  return (
    <>
      <div className="mt-5 flex items-center justify-between gap-6 border-t border-st-hairline2 pt-[18px]">
        <button
          type="button"
          aria-expanded={expanded}
          disabled={!canLaunch}
          onClick={() => setOpen(!open)}
          className="flex items-center gap-2 text-sm font-semibold text-st-accent-dark disabled:cursor-not-allowed disabled:opacity-50"
        >
          <span>Advanced options</span>
          {!expanded && (
            <span className="hidden font-mono text-[11px] normal-case text-st-faint sm:inline">
              core, patches, extra projects
            </span>
          )}
          <span
            aria-hidden="true"
            className="inline-block h-[18px] w-[18px] rounded-full border border-st-button-line text-center text-[10px] leading-4"
          >
            {expanded ? '▴' : '▾'}
          </span>
        </button>
        <span className="hidden font-mono text-[11px] text-st-faint md:inline">
          no account needed · expires after 2 hours
        </span>
      </div>

      {expanded && (
        <div className="mt-6 flex flex-col gap-2">
          <OptionGroup
            number="1"
            title="Environment"
            description="Which Drupal it runs on, and how it gets installed."
          >
            <div className="flex w-full flex-col gap-5 sm:flex-row">
              <DrupalCoreVersionSelector />
              <SelectProfile />
            </div>
            <ManualInstallCheckbox />
          </OptionGroup>

          <OptionGroup
            number="2"
            title="Patches"
            description="Apply patch files from drupal.org issues before the site is built."
          >
            <Patches
              patches={patches}
              setPatches={setPatches}
              idPrefix="project_patch"
            />
          </OptionGroup>

          <OptionGroup
            number="3"
            title="Extra projects"
            description="Install additional modules and themes alongside this one."
          >
            <AdditionalProjects />
          </OptionGroup>
        </div>
      )}
    </>
  );
}
export default AdvancedOptions;
