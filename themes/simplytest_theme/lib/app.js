import React, { useEffect, useState } from 'react'
import ReactDOM from 'react-dom'
import { LauncherProvider, useLauncher } from './context/launcher'
import Fieldset from './components/Fieldset'
import ProjectSelection from './components/ProjectSelection'

function InstallationOptions() {
  const {selectedProject, drupalVersion, installProfile, setInstallProfile, manualInstall, setManualInstall } = useLauncher()
  const allowProfileSelection = selectedProject && selectedProject.type !== "Distribution";

  function ManualInstallCheckbox() {
    return (
      <div className="flex flex-col text-base">
        <label className="inline-flex items-center font-bold">
          <input type="checkbox" value={manualInstall} onChange={event => setManualInstall(event.target.checked)} /><span className="ml-2">Manual installation</span>
        </label>
        <p className="text-sm mb-2">Check this box to perform a manual Drupal install, useful for selecting advanced options.</p>
        <p className="text-sm"><strong>During install, please enter the ID of the spawned instance for your database credentials (user, pass, and database name.)</strong></p>
      </div>
    )
  }
  function SelectProfile() {
    return (
      <div className="mb-2 flex flex-col pl-4 text-base">
        <label className="font-bold mr-2">Installation profile</label>
        <select className="p-1 border border-gray-400 rounded-md w-full md:w-1/3" value={installProfile} onChange={e => setInstallProfile(e.target.value)} disabled={!selectedProject}>
          <option value="standard">Standard</option>
          <option value="minimal">Minimal</option>
          {/* @todo the following is only Core 8.x+ */}
          {drupalVersion.indexOf('8.') === 0 ? [<option value="umami_demo">Umami Demo</option>] : null}
        </select>
      </div>
    )
  }

  if (allowProfileSelection) {
  return (
    <div className="grid grid-cols-2">
      <ManualInstallCheckbox />
      <SelectProfile />
    </div>
  )
  } else {
    return (
      <div className="grid grid-cols-1">
        <ManualInstallCheckbox />
      </div>
    )
  }
}

function DrupalCoreVersionSelector() {
  const [drupalVersions, setDrupalVersions] = useState([]);
  const { selectedVersion, drupalVersion, setDrupalVersion } = useLauncher();
  // @todo leveraged cached core releases in SM; but same major version detection.
  const apiUrl = 'https://www.drupal.org/api-d7/node.json?type=project_release&field_release_project=3060&limit=100&sort=field_release_version_minor&direction=desc&field_release_version_major=';
  useEffect(() => {
    let drupalMajor = '9';
    if (selectedVersion) {
      if (selectedVersion.indexOf('.x-') === 1) {
        drupalMajor = selectedVersion[0]
      }
      fetch(apiUrl + drupalMajor)
        .then(res => res.json())
        .then(json => {
          setDrupalVersions(json.list.map(release => release.field_release_version))
          setDrupalVersion(json.list[0].field_release_version);
        })
    }
  }, [selectedVersion])

  return (
    <div className="mb-2 flex items-center text-base">
      <label className="text-base font-bold mr-2">Drupal core version</label>
      <select className="text-base border border-gray-400 rounded-md p-1 w-full md:w-1/3" disabled={!selectedVersion} value={drupalVersion} onChange={e => setDrupalVersion(e.target.value)}>
        {drupalVersions.map(release => <option value={release} key={release}>{release}</option>)}
      </select>
    </div>
  )
}

function AdditionalProjects() {
  const { additionalProjects, setAdditionalProjects } = useLauncher();

  function additionalProjectChange(project, version) {
    console.log(arguments);
  }
  function addAdditionalProject(event) {
    setAdditionalProjects([...additionalProjects, {
      title: '',
      shortname: '',
    }]);
  }

  function removeExtraProject(k) {
    const additionalProjectsCopy = additionalProjects.slice();
    additionalProjectsCopy.splice(k, 1);
    setAdditionalProjects(additionalProjectsCopy);
  }

  return (
    <div>
      {additionalProjects.map((project, k) => (
        <div className="grid grid-cols-3">
          <div className="col-span-2">
            <ProjectSelection onChange={additionalProjectChange} />
          </div>
          <button type="button" onClick={() => removeExtraProject(k)}>Remove</button>
        </div>
      ))}
      <button type="button" className="text-sm p-2 rounded-md shadow-sm border border-gray-300" onClick={addAdditionalProject}>Add additional project</button>
    </div>
  )
}

function AdvancedOptions() {
  const { canLaunch } = useLauncher();

  if (!canLaunch) {
    return null
  }
  return (
    <details className="mt-4 flex flex-col border shadow-md p-4 bg-white">
      <summary className="font-medium text-sm">Advanced options</summary>
      <Fieldset summary="Build options">
        <DrupalCoreVersionSelector />
        <div className="grid grid-cols-6">
          <div className="font-bold">Extra projects</div>
          <div className="col-span-4">
            <AdditionalProjects />
          </div>
        </div>
      </Fieldset>
      <Fieldset summary="Installation options">
        <InstallationOptions />
      </Fieldset>
    </details>
  )
}

function Launcher() {
  const { canLaunch, getLaunchPayload, setSelectedProject, setSelectedVersion } = useLauncher();
  function onSubmit(e) {
    e.preventDefault();
    const payload = JSON.stringify(getLaunchPayload());
    console.log(payload)
    fetch(`/launch-project`, {
      method: 'POST',
      body: payload,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
    })
      .then(res => res.json())
      .then(json => {
        window.location.href = json.progress
      })
  }

  function onProjectSelection(project, version) {
    setSelectedProject(project)
    setSelectedVersion(version)
  }

  return (
    <div className="bg-gradient-to-r from-flat-blue to-sky-blue py-5">
      <form className="flex flex-col mb-10 max-w-screen-lg container mx-auto" onSubmit={onSubmit}>
        <div className="flex flex-row flex-grow items-center">
          <ProjectSelection onChange={onProjectSelection} />
          <button
            className="px-4 py-1 text-xl border rounded-md shadow bg-white hover:bg-gray-50 cursor-pointer disabled:cursor-not-allowed"
            disabled={!canLaunch}>
            Launch Sandbox
          </button>
        </div>
        <AdvancedOptions />
        <fieldset className="mt-4 border shadow-md p-4 bg-white">
          {/* @todo fetch via API or drupalSettings to know what demos exist. */}
          <summary className="font-medium text-sm">One click demos</summary>
          <div className="grid md:grid-cols-4 gap-2 mt-2">
            <button type="button" className="p-2 bg-dark-sky-blue rounded-sm shadow-sm">Umami Demo</button>
            <button type="button" className="p-2 bg-dark-sky-blue rounded-sm shadow-sm">Drupal Commerce</button>
          </div>
        </fieldset>
      </form>
    </div>
  );
}

ReactDOM.render(<LauncherProvider><Launcher/></LauncherProvider>, document.getElementById('launcher_mount'))
