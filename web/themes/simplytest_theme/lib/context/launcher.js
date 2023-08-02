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

  function setMainProject(project, version) {
    setSelectedProject(project)
    setSelectedVersion(version)
    // @todo in the future, maybe we need to have a reducer that can set all of
    //   this. Like when we refactor the fact the main project version and
    //   project data are two state values.
    if (project.shortname === 'drupal') {
      // @todo this is somehow picking the old project version if changes from
      //    contrib to core.
      setDrupalVersion(version)
    }
  }
  useEffect(() => {
    const { search } = window.location;
    const searchParams = new URLSearchParams(search);

    if (searchParams.has("project") && searchParams.has("version")) {
      setMainProject(
        { shortname: searchParams.get("project") },
        searchParams.get("version")
      );
    } else if (searchParams.has("project")) {
      setSelectedProject({
        shortname: searchParams.get("project")
      });
    }
    if (searchParams.has("patch")) {
      const paramsPatch = searchParams.get("patch");
      setPatches(Array.isArray(paramsPatch) ? paramsPatch : [paramsPatch]);
    }
  }, []);

  useEffect(
    () => {
      if (selectedProject && selectedProject.type === "Distribution") {
        setInstallProfile(selectedProject.shortname);
      }
    },
    [selectedProject, setInstallProfile]
  );
  useEffect(
    () => {
      setCanLaunch(selectedProject && selectedVersion);
    },
    [selectedVersion, selectedProject]
  );

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
