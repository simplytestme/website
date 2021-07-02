import React, { useEffect, useState } from "react";
import { useLauncher } from "../context/launcher";
import { fetchWithCallback } from "../utils";

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
        return null;
      }
      // @todo There can be bugs when toggling between core + contrib
      // @todo Prevent extra requests for core version if we're on the same major.
      let releaseUrl;
      if (selectedProject.shortname === "drupal") {
        releaseUrl = `simplytest/core/versions/${selectedVersion[0]}`;
      } else {
        releaseUrl = `simplytest/core/compatible/${
          selectedProject.shortname
        }/${selectedVersion}`;
      }
      fetchWithCallback(releaseUrl, json => {
        if (json.hasOwnProperty("list")) {
          setDrupalVersions(json.list.map(release => release.version));
          setDrupalVersion(json.list[1].version);
        }
      });
    },
    [selectedProject, selectedVersion]
  );

  if (selectedProject.shortname === "drupal") {
    return null;
  }

  return (
    <div className="mb-2 flex items-center text-base w-full sm:w-1/2 sm:mr-4">
      <label htmlFor="drupal_core_version" className="text-lg mr-2 text-white">
        Drupal Core
      </label>
      <select
        id="drupal_core_version"
        className="text-base border border-gray-400 rounded-md p-1 w-full md:w-1/3"
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
