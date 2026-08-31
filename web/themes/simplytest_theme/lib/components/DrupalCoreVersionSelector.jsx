import React, { useEffect, useState } from "react";
import { useLauncher } from "../context/launcher";
import { fetchWithCallback } from "../utils";
import { selectPanel } from "../ui";

function DrupalCoreVersionSelector() {
  const [drupalVersions, setDrupalVersions] = useState([]);
  const {
    selectedProject,
    selectedVersion,
    drupalVersion,
    setDrupalVersion
  } = useLauncher();

  useEffect(
    () => {
      // Handle when the selected project is resolved before the selected version.
      if (!selectedVersion) {
        return undefined;
      }
      // @todo There can be bugs when toggling between core + contrib
      // @todo Prevent extra requests for core version if we're on the same major.
      let releaseUrl;
      if (selectedProject.shortname === "drupal") {
        const [major] = selectedVersion.split('.')
        releaseUrl = `simplytest/core/versions/${major}`;
      } else {
        releaseUrl = `simplytest/core/compatible/${
          selectedProject.shortname
        }/${selectedVersion}`;
      }
      // When the version changes quickly, a slower earlier response must not
      // overwrite the state of the one that matches the current selection.
      let stale = false;
      fetchWithCallback(releaseUrl, json => {
        if (stale) {
          return;
        }
        // The endpoint 404s for an unknown release and can return an empty
        // list; either way there is nothing to select.
        if (Array.isArray(json.list) && json.list.length > 0) {
          setDrupalVersions(json.list.map(release => release.version));
          setDrupalVersion(json.list[0].version);
        } else {
          setDrupalVersions([]);
        }
      });
      return () => {
        stale = true;
      };
    },
    [selectedProject, selectedVersion]
  );

  if (selectedProject.shortname === "drupal") {
    return null;
  }

  return (
    <div className="flex flex-1 flex-col gap-[7px]">
      <label htmlFor="drupal_core_version" className="text-[13px] font-semibold text-st-slate">
        Drupal core
      </label>
      <select
        id="drupal_core_version"
        className={selectPanel}
        disabled={!selectedVersion}
        value={drupalVersion}
        onChange={e => setDrupalVersion(e.target.value)}
      >
        {drupalVersions.map(release => (
          <option value={release} key={release}>
            {release}
          </option>
        ))}
      </select>
    </div>
  );
}

export default DrupalCoreVersionSelector;
