import React, { useEffect, useState } from "react";
import { selectHero, selectPanel } from "../ui";

function versionWithoutCoreModifier(version) {
  if (version.indexOf(".x-") !== -1) {
    return version.substr(4);
  }
  return version;
}

// @todo this might be better coupled within the ProjectAutocomplete component?
function VersionSelector({
  selectedProject,
  selectedVersion,
  setSelectedVersion,
  appliedCoreConstraint,
  initialVersion,
  rootProjectVersion,
  compact
}) {
  const [versions, setVersions] = useState(null);
  // Side effect: when we have a project shortname, and no core constraints
  // AKA the root project, fetch the direct versions.
  useEffect(
    () => {
      if (selectedProject && !appliedCoreConstraint) {
        fetch(`/simplytest/project/${selectedProject.shortname}/versions`)
          .then(res => res.json())
          .then(json => {
            setVersions(json.list);
          });
      }
    },
    [selectedProject, appliedCoreConstraint]
  );

  // Side effect: when we have a project shortname AND core constraints, we know
  // this is a dependent/additional project. If the root project version changes,
  // we want to update for new compatibility.
  useEffect(
    () => {
      if (selectedProject && appliedCoreConstraint) {
        fetch(
          `/simplytest/project/${
            selectedProject.shortname
          }/compatibility/${appliedCoreConstraint}`
        )
          .then(res => res.json())
          .then(json => {
            setVersions(json.list);
          });
      }
    },
    [selectedProject, appliedCoreConstraint, rootProjectVersion]
  );

  useEffect(
    () => {
      if (!initialVersion && versions && versions.latest.length > 0) {
        setSelectedVersion(versions.latest[0].version);
      }
    },
    [versions, initialVersion]
  );
  if (selectedProject === null || versions === null) {
    return null;
  }
  return (
    <div className={compact ? "w-full lg:w-[180px]" : "w-full lg:w-[200px]"}>
      <label htmlFor="project_version" className={compact ? "sr-only" : "field-label mb-2 block"}>
        Version
      </label>
      <select
        id="project_version"
        className={compact ? selectPanel : selectHero}
        value={selectedVersion}
        onChange={e => {
          setSelectedVersion(e.target.value);
        }}
      >
        <optgroup label="Latest">
          {versions.latest.map(version => {
            return (
              <option value={version.version} key={version.version}>
                {versionWithoutCoreModifier(version.version)} ({version.core_compatibility})
              </option>
            );
          })}
        </optgroup>
        <optgroup label="Branches">
          {versions.branches.map(version => {
            return (
              <option value={version.version} key={version.version}>
                {version.version}
              </option>
            );
          })}
        </optgroup>
        {versions.core.map(core => {
          return (
            <optgroup label={core.label} key={core.label}>
              {core.versions.map(version => {
                return (
                  <option value={version.version} key={version.version}>
                    {version.version}
                  </option>
                );
              })}
            </optgroup>
          )
        })}
      </select>
    </div>
  );
}

export default VersionSelector;
