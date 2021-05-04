import React, { useContext, createContext, useState, useEffect } from "react";

const launcherContext = createContext();

export function useLauncher() {
  return useContext(launcherContext);
}

export function LauncherProvider({ children }) {
  const [selectedProject, setSelectedProject] = useState(null);
  const [selectedVersion, setSelectedVersion] = useState('');
  const [patches, setPatches] = useState([]);
  const [installProfile, setInstallProfile] = useState('standard');
  const [drupalVersion, setDrupalVersion] = useState('');
  const [manualInstall, setManualInstall] = useState(false);
  const [additionalProjects, setAdditionalProjects] = useState([]);
  const [canLaunch, setCanLaunch] = useState(false);

  useEffect(() => {
    if (selectedProject && selectedProject.type === "Distribution") {
      setInstallProfile(selectedProject.shortname)
    }
  }, [selectedProject, setInstallProfile]);
  useEffect(() => {
      setCanLaunch(selectedProject && selectedVersion)
  }, [selectedVersion, selectedProject, setCanLaunch])

  function setMainProject(project, version) {
    setSelectedProject(project)
    setSelectedVersion(version)
    // @todo in the future, maybe we need to have a reducer that can set all of
    //   this. Like when we refactor the fact the main project version and
    //   project data are two state values.
    if (project.shortname === 'drupal') {
      setDrupalVersion(version)
    }
  }

  function getLaunchPayload() {
    return {
      project: {
        version: selectedVersion,
        patches,
        ...selectedProject
      },
      drupalVersion,
      installProfile,
      manualInstall,
      additionalProjects,
    }
  }

  return (
    <launcherContext.Provider
      value={{
        selectedProject,
        selectedVersion,
        patches,
        setPatches,
        setMainProject,
        installProfile,
        setInstallProfile,
        drupalVersion,
        setDrupalVersion,
        manualInstall,
        setManualInstall,
        canLaunch,
        additionalProjects,
        setAdditionalProjects,
        getLaunchPayload
      }}
    >
      {children}
    </launcherContext.Provider>
  );
}
