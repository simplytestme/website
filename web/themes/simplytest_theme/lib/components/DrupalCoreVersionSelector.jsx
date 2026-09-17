import { useEffect, useState } from 'react';

import { useLauncher } from '../context/launcher';
import { selectPanel } from '../ui';
import { fetchWithCallback } from '../utils';

function DrupalCoreVersionSelector() {
  const [drupalVersions, setDrupalVersions] = useState([]);
  const { selectedProject, selectedVersion, drupalVersion, setDrupalVersion } =
    useLauncher();

  useEffect(() => {
    // Handle when the selected project is resolved before the selected version.
    if (!selectedVersion) {
      return undefined;
    }
    // @todo There can be bugs when toggling between core + contrib
    // @todo Prevent extra requests for core version if we're on the same major.
    let releaseUrl;
    if (selectedProject.shortname === 'drupal') {
      const [major] = selectedVersion.split('.');
      releaseUrl = `simplytest/core/versions/${major}`;
    } else {
      releaseUrl = `simplytest/core/compatible/${
        selectedProject.shortname
      }/${selectedVersion}`;
    }
    // When the version changes quickly, a slower earlier response must not
    // overwrite the state of the one that matches the current selection.
    let stale = false;
    fetchWithCallback(releaseUrl, (json) => {
      if (stale) {
        return;
      }
      // The endpoint 404s for an unknown release and can return an empty
      // list; either way there is nothing to select.
      if (Array.isArray(json.list) && json.list.length > 0) {
        setDrupalVersions(json.list.map((release) => release.version));
        // The list is every compatible core release, newest first, so the
        // first row is a pre-release whenever core has one out and the
        // project's core_version_requirement does not stop below it. Once
        // 12.0.0-alpha1 shipped, a project declaring `>=9` defaulted to a
        // Drupal 12 alpha. Nobody evaluating a module means to do that, and
        // the major has no base preview either, so the sandbox builds from
        // scratch on top of it.
        //
        // `extra` carries the pre-release suffix and is null for a stable
        // release, so the first row without one is the newest stable. A line
        // that has only pre-releases keeps the newest of those, which is the
        // only thing there is to offer. Either way the whole list stays in
        // the select, so an alpha is still one choice away.
        const stable = json.list.find((release) => !release.extra);
        setDrupalVersion((stable ?? json.list[0]).version);
      } else {
        setDrupalVersions([]);
      }
    });
    return () => {
      stale = true;
    };
  }, [selectedProject, selectedVersion, setDrupalVersion]);

  if (selectedProject.shortname === 'drupal') {
    return null;
  }

  return (
    <div className="flex flex-1 flex-col gap-[7px]">
      <label
        htmlFor="drupal_core_version"
        className="text-[13px] font-semibold text-st-slate"
      >
        Drupal core
      </label>
      <select
        id="drupal_core_version"
        className={selectPanel}
        disabled={!selectedVersion}
        value={drupalVersion}
        onChange={(e) => setDrupalVersion(e.target.value)}
      >
        {drupalVersions.map((release) => (
          <option value={release} key={release}>
            {release}
          </option>
        ))}
      </select>
    </div>
  );
}

export default DrupalCoreVersionSelector;
