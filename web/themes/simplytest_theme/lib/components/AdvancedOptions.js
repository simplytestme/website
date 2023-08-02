import React from "react";
import { useLauncher } from "../context/launcher";
import DrupalCoreVersionSelector from "./DrupalCoreVersionSelector";
import Patches from "./Patches";
import InstallationOptions from "./InstallationOptions";
import Fieldset from "./Fieldset";
import AdditionalProjects from "./AdditionalProjects";

function AdvancedOptions() {
  const { canLaunch, patches, setPatches } = useLauncher();

  if (!canLaunch) {
    return null;
  }
  return (
    <details className="mt-4 flex flex-col py-4" open={window.location.search.length > 0}>
      <summary
        className="inline-block font-medium text-lg underline p-0 advance-summary focus:outline-none focus:shadow-none arrow-circle
"
      >
        Advanced options
      </summary>
      <div className="flex mb-10 flex-col sm:flex-row">
        <DrupalCoreVersionSelector />
        <Patches patches={patches} setPatches={setPatches} />
      </div>
      <InstallationOptions />
      <Fieldset summary="Add additional projects">
        <p className="text-sm mb-2 text-white">
          Include additional modules and themes
        </p>
        <AdditionalProjects />
      </Fieldset>
    </details>
  );
}
export default AdvancedOptions;
