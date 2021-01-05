import React, { useContext, createContext, useState, useEffect } from "react";

const launcherContext = createContext();

export function useLauncher() {
  return useContext(launcherContext);
}

export function LauncherProvider({ children }) {
  const [selectedProject, setSelectedProject] = useState(null);
  const [selectedVersion, setSelectedVersion] = useState('');
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
  }

  function getLaunchPayload() {
    return {
      project: {
        version: selectedVersion,
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
