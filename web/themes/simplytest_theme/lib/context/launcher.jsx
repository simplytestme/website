import { createContext, useContext, useEffect, useState } from 'react';

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
    setSelectedProject(project);
    setSelectedVersion(version);
    // @todo in the future, maybe we need to have a reducer that can set all of
    //   this. Like when we refactor the fact the main project version and
    //   project data are two state values.
    if (project.shortname === 'drupal') {
      // @todo this is somehow picking the old project version if changes from
      //    contrib to core.
      setDrupalVersion(version);
    }
  }
  useEffect(() => {
    const { search } = window.location;
    const searchParams = new URLSearchParams(search);

    if (searchParams.has('project') && searchParams.has('version')) {
      setMainProject(
        { shortname: searchParams.get('project') },
        searchParams.get('version'),
      );
    } else if (searchParams.has('project')) {
      setSelectedProject({
        shortname: searchParams.get('project'),
      });
    }
    if (searchParams.has('patch')) {
      setPatches(searchParams.getAll('patch'));
    }
  }, []);

  useEffect(() => {
    if (selectedProject && selectedProject.type === 'Distribution') {
      setInstallProfile(selectedProject.shortname);
    }
  }, [selectedProject, setInstallProfile]);
  useEffect(() => {
    setCanLaunch(selectedProject && selectedVersion);
  }, [selectedVersion, selectedProject]);

  // "Add another project" inserts an empty row. Submitting with one posts empty
  // strings, and the backend answers with the raw constraint messages
  // ("additionalProjects.0.shortname: This value should not be blank.") that
  // #3265514 reported as cryptic. Hold the submit button until every row names
  // a project and a version. Dropping incomplete rows from the payload instead
  // would launch a sandbox missing a project the user believes they added.
  //
  // Kept separate from `canLaunch`, which gates the advanced options panel: an
  // incomplete row must not collapse the panel that row lives in.
  const canSubmit =
    Boolean(canLaunch) &&
    additionalProjects.every((project) => project.shortname && project.version);

  function getLaunchPayload() {
    return {
      project: {
        version: selectedVersion,
        patches,
        ...selectedProject,
      },
      drupalVersion,
      installProfile,
      manualInstall,
      // `id` only keys the rows in the UI. The backend builds typed data from
      // this payload and throws on properties it does not define.
      additionalProjects: additionalProjects.map(
        ({ id, ...project }) => project,
      ),
    };
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
        canSubmit,
        additionalProjects,
        setAdditionalProjects,
        getLaunchPayload,
      }}
    >
      {children}
    </launcherContext.Provider>
  );
}
