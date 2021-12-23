import React from "react";
import { useLauncher } from "../context/launcher";

function ManualInstallCheckbox() {
  const { manualInstall, setManualInstall } = useLauncher();
  return (
    <div className="flex flex-col text-base w-full sm:w-1/2 sm:mr-4">
      <label className="inline-flex items-center font-bold">
        <input
          type="checkbox"
          value={manualInstall}
          onChange={event => setManualInstall(event.target.checked)}
        />
        <span className="ml-2 text-white">Manual installation</span>
      </label>
      <p className="text-sm mb-2 text-white">
        Check this box to perform a manual Drupal install, useful for selecting
        advanced options.
      </p>
    </div>
  );
}

function SelectProfile() {
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
    <div className="mb-2 flex flex-col text-lg w-full sm:w-1/2">
      <label htmlFor="install_profile" className=" mr-2 text-white">
        Install profile
      </label>
      <select
        id="install_profile"
        className="p-1 border border-gray-400 rounded-md w-full md:w-1/3"
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

function InstallationOptions() {
  return (
    <div className="pb-4 border-b flex flex-col sm:flex-row">
      <ManualInstallCheckbox />
      <SelectProfile />
    </div>
  );
}

export default InstallationOptions;
