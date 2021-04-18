import { useLauncher } from '../context/launcher'
import React, { useEffect, useState } from 'react'

// @todo this might be better coupled within the ProjectAutocomplete component?
function VersionSelector({ selectedProject, selectedVersion, setSelectedVersion, appliedCoreConstraint }) {
  const [versions, setVersions] = useState([]);
  useEffect(() => {
    if (selectedProject) {

      if (appliedCoreConstraint) {
        fetch(`/simplytest/project/${selectedProject.shortname}/compatibility/${appliedCoreConstraint}`)
          .then(res => res.json())
          .then(json => {
            setVersions(json.list);
          });
      }
      else {
        fetch(`/simplytest/project/${selectedProject.shortname}/versions`)
          .then(res => res.json())
          .then(json => {
            setVersions(json.list);
          });
      }
    }
  }, [selectedProject]);
  useEffect(() => {
    if (versions.length > 0) {
      setSelectedVersion(versions[0].version)
    }
  }, [versions])
  if (selectedProject === null) {
    return null;
  }
  return (
    <div className={"mr-2"}>
      <label for="project_version" className="sr-only">Project version</label>
      <select id="project_version" className="text-xl font-sans border rounded-md shadow px-4 py-1 w-full version-list" value={selectedVersion} onChange={(e) => {
        setSelectedVersion(e.target.value)
      }}>
        {versions.map(version => {
          return (
            <option value={version.version} key={version.version}>{version.version}</option>
          )
        })}
      </select>
    </div>
  );
}

export default VersionSelector;
