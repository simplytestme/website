import { useLauncher } from '../context/launcher'
import React, { useEffect, useState } from 'react'

// @todo this might be better coupled within the ProjectAutocomplete component?
function VersionSelector({ selectedProject, selectedVersion, setSelectedVersion }) {
  const [versions, setVersions] = useState({
    branches: [],
    tags: [],
  });
  useEffect(() => {
    if (selectedProject) {
      fetch(`/simplytest/project/${selectedProject.shortname}/versions`)
        .then(res => res.json())
        .then(json => {
          setVersions(json);
        });
    }
  }, [selectedProject]);
  useEffect(() => {
    if (versions.tags.length > 0) {
      setSelectedVersion(versions.tags[0].tags[0]);
    }
    else if(versions.branches.length > 0) {
      setSelectedVersion(versions.branches[0].branch)
    }
  }, [versions])
  if (selectedProject === null) {
    return null;
  }
  return (
    <div className={"mr-2"}>
      <label className="sr-only">Project version</label>
      <select className="text-xl font-sans border rounded-md shadow px-4 py-1 w-full" value={selectedVersion} onChange={(e) => {
        setSelectedVersion(e.target.value)
      }}>
        {versions.tags.map(versionGroup => {
          return (
            <optgroup label={versionGroup.grouping} key={versionGroup.grouping}>
              {versionGroup.tags.map(version => <option value={version} key={version}>{version}</option>)}
            </optgroup>
          )
        })}
        <optgroup label={"Branches"}>
          {versions.branches.map(version => <option value={version.branch} key={version.branch}>{version.branch}</option>)}
        </optgroup>
      </select>
    </div>
  );
}

export default VersionSelector;
