import { createContext, useContext, useEffect, useState } from 'react';

import { fetchWithCallback } from '../utils';

const launcherContext = createContext();

// The profiles core ships, matching InstanceLaunchDefinition::INSTALL_PROFILES.
const INSTALL_PROFILES = ['standard', 'minimal', 'demo_umami'];

function linkedInstallProfile() {
  const profile = new URLSearchParams(window.location.search).get('profile');
  return INSTALL_PROFILES.includes(profile) ? profile : 'standard';
}

// A launch link's `core` names a release (10.3.14), a minor (10.3 or 10.3.x),
// or a major (10 or 10.x), and picks the newest release it matches. When the
// project supports none of them, the usual default applies.
function defaultDrupalVersion(releases, linkedVersion) {
  const linkedPrefix = linkedVersion?.replace(/\.x$/, '');
  const linked = linkedPrefix
    ? releases.filter(
        ({ version }) =>
          version === linkedPrefix || version.startsWith(`${linkedPrefix}.`),
      )
    : [];
  const candidates = linked.length > 0 ? linked : releases;
  // The list is every compatible core release, newest first, so the first row
  // is a pre-release whenever core has one out and the project's
  // core_version_requirement does not stop below it. Once 12.0.0-alpha1
  // shipped, a project declaring `>=9` defaulted to a Drupal 12 alpha. Nobody
  // evaluating a module means to do that, and the major has no base preview
  // either, so the sandbox builds from scratch on top of it.
  //
  // `extra` carries the pre-release suffix and is null for a stable release,
  // so the first row without one is the newest stable. A line that has only
  // pre-releases keeps the newest of those, which is the only thing there is
  // to offer. Either way the whole list stays in the select, so an alpha is
  // still one choice away.
  const stable = candidates.find((release) => !release.extra);
  return (stable ?? candidates[0]).version;
}

export function useLauncher() {
  return useContext(launcherContext);
}

export function LauncherProvider({ children }) {
  const [selectedProject, setSelectedProject] = useState(null);
  const [selectedVersion, setSelectedVersion] = useState('');
  const [patches, setPatches] = useState([]);
  const [installProfile, setInstallProfile] = useState(linkedInstallProfile);
  const [drupalVersions, setDrupalVersions] = useState([]);
  const [drupalVersion, setDrupalVersion] = useState('');
  // The core version list loads after the project and version, so a linked
  // `core` applies once the list says what the project supports.
  const [linkedDrupalVersion] = useState(() =>
    new URLSearchParams(window.location.search).get('core'),
  );
  const [manualInstall, setManualInstall] = useState(false);
  const [additionalProjects, setAdditionalProjects] = useState([]);
  const [canLaunch, setCanLaunch] = useState(false);

  function setMainProject(project, version) {
    setSelectedProject(project);
    setSelectedVersion(version);
    // @todo in the future, maybe we need to have a reducer that can set all of
    //   this. Like when we refactor the fact the main project version and
    //   project data are two state values.
    if (project && project.shortname === 'drupal') {
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
    // Links from Dreditor and the 7.x site send `patch[]=`, and the redirect
    // from /project/{name}/{version} rebuilds that as `patch[0]=`. Accept
    // every form so those links still arrive with their patches.
    const patches = [...searchParams.entries()]
      .filter(([key]) => /^patch(\[\d*\])?$/.test(key))
      .map(([, value]) => value);
    if (patches.length > 0) {
      setPatches(patches);
    }
  }, []);

  useEffect(() => {
    if (selectedProject && selectedProject.type === 'Distribution') {
      setInstallProfile(selectedProject.shortname);
    }
  }, [selectedProject, setInstallProfile]);
  // The launch payload needs a core version even when the advanced options
  // panel stays closed. The select only mounts with the panel open, so the
  // default is picked here rather than there (#3621806).
  useEffect(() => {
    // Handle when the selected project is resolved before the selected version.
    // Core's own version already says which core to build, and
    // setMainProject() sets it.
    if (!selectedVersion || selectedProject?.shortname === 'drupal') {
      setDrupalVersions([]);
      return undefined;
    }
    // When the version changes quickly, a slower earlier response must not
    // overwrite the state of the one that matches the current selection.
    let stale = false;
    fetchWithCallback(
      `simplytest/core/compatible/${selectedProject.shortname}/${selectedVersion}`,
      (json) => {
        if (stale) {
          return;
        }
        // The endpoint 404s for an unknown release and can return an empty
        // list; either way there is nothing to select.
        if (Array.isArray(json.list) && json.list.length > 0) {
          setDrupalVersions(json.list.map((release) => release.version));
          setDrupalVersion(
            defaultDrupalVersion(json.list, linkedDrupalVersion),
          );
        } else {
          setDrupalVersions([]);
        }
      },
    );
    return () => {
      stale = true;
    };
  }, [selectedProject, selectedVersion, linkedDrupalVersion]);

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
  //
  // The core version arrives a request after the project version does, and
  // the backend rejects a blank one, so wait for it too.
  const canSubmit =
    Boolean(canLaunch) &&
    Boolean(drupalVersion) &&
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
        drupalVersions,
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
