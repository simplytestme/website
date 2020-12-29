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

  function getLaunchPayload() {
    return {
      project: selectedProject,
      version: selectedVersion,
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
        setSelectedProject,
        selectedVersion,
        setSelectedVersion,
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
