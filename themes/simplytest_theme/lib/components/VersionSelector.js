import React, { useEffect, useState } from "react";

// @todo this might be better coupled within the ProjectAutocomplete component?
function VersionSelector({
  selectedProject,
  selectedVersion,
  setSelectedVersion,
  appliedCoreConstraint,
  initialVersion,
  rootProjectVersion
}) {
  const [versions, setVersions] = useState([]);
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
      if (!initialVersion && versions.length > 0) {
        setSelectedVersion(versions[0].version);
      }
    },
    [versions, initialVersion]
  );
  if (selectedProject === null) {
    return null;
  }
  return (
    <div className="mr-2">
      <label htmlFor="project_version" className="sr-only">
        Project version
      </label>
      <select
        id="project_version"
        className="text-xl font-sans border rounded-md shadow px-4 py-1 w-full version-list"
        value={selectedVersion}
        onChange={e => {
          setSelectedVersion(e.target.value);
        }}
      >
        {versions.map(version => {
          return (
            <option value={version.version} key={version.version}>
              {version.version}
            </option>
          );
        })}
      </select>
    </div>
  );
}

export default VersionSelector;
