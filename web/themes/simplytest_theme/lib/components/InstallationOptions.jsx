import React from "react";
import { useLauncher } from "../context/launcher";
import { selectPanel } from "../ui";

export function ManualInstallCheckbox() {
  const { manualInstall, setManualInstall } = useLauncher();
  return (
    <label className="flex w-full cursor-pointer items-start gap-3 rounded-[10px] border border-st-line bg-white px-4 py-3.5">
      <input
        type="checkbox"
        className="mt-[3px] h-4 w-4 accent-st-accent"
        checked={manualInstall}
        onChange={event => setManualInstall(event.target.checked)}
      />
      <span className="flex flex-col gap-[3px]">
        <span className="text-sm font-semibold text-st-body">Run the installer myself</span>
        <span className="text-[13px] leading-normal text-st-soft">
          Land on Drupal&rsquo;s install screen instead of a finished site. Useful for
          testing install-time options.
        </span>
      </span>
    </label>
  );
}

export function SelectProfile() {
  const validChecks = ["8.6.", "8.7.", "8.8.", "8.9.", "9."];
  const {
    selectedProject,
    drupalVersion,
    installProfile,
    setInstallProfile
  } = useLauncher();

  if (!selectedProject) {
    return null;
  }
  if (selectedProject.type === "Distribution") {
    return null;
  }

  const isUmamiAllowed = validChecks.reduce((allowed, version) => {
    return allowed || drupalVersion.indexOf(version) === 0;
  }, false);

  return (
    <div className="flex flex-1 flex-col gap-[7px]">
      <label htmlFor="install_profile" className="text-[13px] font-semibold text-st-slate">
        Install profile
      </label>
      <select
        id="install_profile"
        className={selectPanel}
        value={installProfile}
        onChange={e => setInstallProfile(e.target.value)}
        disabled={!selectedProject}
      >
        <option key="standard" value="standard">
          Standard
        </option>
        <option key="minimal" value="minimal">
          Minimal
        </option>
        {isUmamiAllowed
          ? [
              <option key="demo_umami" value="demo_umami">
                Umami Demo
              </option>
            ]
          : null}
      </select>
    </div>
  );
}
