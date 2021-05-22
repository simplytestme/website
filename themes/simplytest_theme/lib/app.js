import React, { useEffect, useState } from 'react'
import ReactDOM from 'react-dom'
import { LauncherProvider, useLauncher } from './context/launcher'
import Fieldset from './components/Fieldset'
import ProjectSelection from './components/ProjectSelection'
import Patches from './components/Patches'
import OneClickDemos from './components/OneClickDemos'

function InstallationOptions() {
  const {selectedProject, drupalVersion, installProfile, setInstallProfile, manualInstall, setManualInstall } = useLauncher()
  const allowProfileSelection = selectedProject && selectedProject.type !== "Distribution";

  function ManualInstallCheckbox() {
    return (
      <div className="flex flex-col text-base w-full sm:w-1/2 sm:mr-4">
        <label className="inline-flex items-center font-bold">
          <input type="checkbox" value={manualInstall} onChange={event => setManualInstall(event.target.checked)} /><span className="ml-2 text-white">Manual installation</span>
        </label>
        <p className="text-sm mb-2 text-white">Check this box to perform a manual Drupal install, useful for selecting advanced options.</p>
      </div>
    )
  }
  function SelectProfile() {
    const validChecks = [
      '8.6.',
      '8.7.',
      '8.8.',
      '8.9.',
      '9.',
    ]
    const isValid = validChecks.reduce((allowed, version) => {
      return allowed || drupalVersion.indexOf(version) === 0;
    }, false);
    return (
      <div className="mb-2 flex flex-col text-lg w-full sm:w-1/2">
        <label htmlFor="install_profile" className=" mr-2 text-white">Install profile</label>
        <select id="install_profile" className="p-1 border border-gray-400 rounded-md w-full md:w-1/3" value={installProfile} onChange={e => setInstallProfile(e.target.value)} disabled={!selectedProject}>
          <option value="standard">Standard</option>
          <option value="minimal">Minimal</option>
          {isValid ? [<option value="umami_demo">Umami Demo</option>] : null}
        </select>
      </div>
    )
  }

  if (allowProfileSelection) {
  return (
    <div className="pb-4 border-b flex flex-col sm:flex-row">
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
  const { selectedProject, selectedVersion, drupalVersion, setDrupalVersion } = useLauncher();
  useEffect(() => {
    const isDrupalCore = selectedProject.shortname === 'drupal';

    // @todo Prevent extra requests for core version if we're on the same major.
    let releaseUrl;
    if (isDrupalCore) {
      releaseUrl = `simplytest/core/versions/${selectedVersion[0]}`;
    } else {
      releaseUrl = `simplytest/core/compatible/${selectedProject.shortname}/${selectedVersion}`;
    }
    fetch(releaseUrl)
      .then(res => res.json())
      .then(json => {
        console.log(json)
        setDrupalVersions(json.list.map(release => release.version))
        setDrupalVersion(json.list[1].version);
      });
  }, [selectedProject, selectedVersion])

  if (selectedProject.shortname === 'drupal') {
    return null;
  }

  return (
    <div className="mb-2 flex items-center text-base w-full sm:w-1/2 sm:mr-4">
      <label htmlFor="drupal_core_version" className="text-lg mr-2 text-white">Drupal Core</label>
      <select id="drupal_core_version" className="text-base border border-gray-400 rounded-md p-1 w-full md:w-1/3" disabled={!selectedVersion} value={drupalVersion} onChange={e => setDrupalVersion(e.target.value)}>
        {drupalVersions.map(release => <option value={release} key={release}>{release}</option>)}
      </select>
    </div>
  )
}

function AdditionalProjects() {
  const { additionalProjects, setAdditionalProjects, drupalVersion, selectedVersion } = useLauncher();
  const [additionalBtn, setAdditionalBtn] = useState(false);

  function addAdditionalProject(event) {
    setAdditionalProjects([...additionalProjects, {
      title: '',
      shortname: '',
      version: '',
      patches: [],
    }]);
    setAdditionalBtn(true)
  }

  function removeExtraProject(k) {
    const additionalProjectsCopy = additionalProjects.slice();
    additionalProjectsCopy.splice(k, 1);
    setAdditionalProjects(additionalProjectsCopy);
  }

  return (
    <div>
      {additionalProjects.map((project, k) => (
        <div key={k} id={`additional_project_${k}`} className="py-4 border-b">
          <div className="flex flex-wrap mb-4 sm:w-1/2">
            <div className="flex-grow">
              <ProjectSelection appliedCoreConstraint={drupalVersion} rootProjectVersion={selectedVersion} additionalBtn={additionalBtn} onChange={(project, version) => {
                // @todo the state management for ProjectSelection needs refactor
                // onChange is technically called with each render, and the
                // component has no idea if it has really changed or not and ends
                // up being called on each render.
                if (additionalProjects[k].shortname !== project.shortname || additionalProjects[k].version !== version) {
                  const newProjects = [...additionalProjects];
                  newProjects[k] = {
                    version,
                    patches: [],
                    ...project
                  }
                  setAdditionalProjects(newProjects);
                }
              }} />
            </div>
            <div className="flex-shrink-0 mr-2">
              <button className="text-white text-2xl font-semibold" type="button" onClick={() => removeExtraProject(k)}><span>×</span></button>
            </div>
          </div>
          {project.shortname ? <Patches patches={project.patches} setPatches={updatedPatches => {
            const newProjects = [...additionalProjects];
            newProjects[k].patches = updatedPatches;
            setAdditionalProjects(newProjects)
          }} /> : null}
        </div>
      ))}
      <div className="pt-2">
        <button type="button" className="text-base p-2 rounded-md btn-blue" onClick={addAdditionalProject}>Add additional project</button>
      </div>
    </div>
  )
}

function AdvancedOptions() {
  const { canLaunch, patches, setPatches } = useLauncher();

  if (!canLaunch) {
    return null
  }
  return (
    <details className="mt-4 flex flex-col py-4" open={patches.length > 0}>
      <summary className="inline-block font-medium text-lg underline p-0 advance-summary focus:outline-none focus:shadow-none arrow-circle
">Advanced options</summary>
      <div className="flex mb-10 flex-col sm:flex-row">
        <DrupalCoreVersionSelector />
        <Patches patches={patches} setPatches={setPatches} />
      </div>
        <InstallationOptions />
      <Fieldset summary={"Add additional projects"}>
        <p className="text-sm mb-2 text-white">Include additional modules and themes</p>
        <AdditionalProjects />
      </Fieldset>
    </details>
  )
}

function Launcher() {
  const [errors, setErrors] = useState([]);
  const { canLaunch, getLaunchPayload, setMainProject, selectedProject, selectedVersion } = useLauncher();
  function onSubmit(e) {
    e.preventDefault();
    const payload = JSON.stringify(getLaunchPayload());
    fetch(`/launch-project`, {
      method: 'POST',
      body: payload,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
    })
      .then(res => {
        res
          .json()
          .then(json => {
            if (res.ok) {
              window.location.href = json.progress
            } else {
              setErrors(json.errors);
            }
          })
          .catch(error => {
            setErrors([`${error.name}: ${error.message}`]);
          })
      })
      .catch(error => {
        setErrors([`${error.name}: ${error.message}`]);
      })
  }

  return (
    <div className="bg-gradient-to-r from-flat-blue py-5">
      {errors.map((error, i) => {
        return <div key={i} className="container max-w-screen-lg mx-auto px-4 py-2 mb-4 bg-red-600 text-red-100">{error}</div>
      })}
      <form className="flex flex-col mb-10 max-w-screen-lg container mx-auto pl-130" onSubmit={onSubmit}>
        <div className="flex flex-row flex-grow items-center mobile-column-flex desktop-align-item-end">
          <ProjectSelection onChange={setMainProject} initialDefaultProject={selectedProject} initialDefaultVersion={selectedVersion} />
          <button
            className="px-4 py-1 text-xl border rounded-md shadow bg-white hover:bg-gray-50 cursor-pointer disabled:cursor-not-allowed bg-yellow-tan"
            disabled={!canLaunch}>
            Launch Sandbox
          </button>
        </div>
        <AdvancedOptions />
        <OneClickDemos />
      </form>
    </div>
  );
}

const launcherMount = document.getElementById('launcher_mount');
if (launcherMount) {
  ReactDOM.render(<LauncherProvider><Launcher/></LauncherProvider>, launcherMount)
}

function BuildErrorMessage({ logs }) {
  const lastLogs = logs.slice(-3, -1)
  return (
    <div className="bg-red-600 text-red-100 p-4 overflow-scroll">
      <p className="font-bold mb-4">This may be the error:</p>
      <pre className="text-sm">{lastLogs.map(log => <code className="block">{log.message}</code>)}</pre>
    </div>
  )
}

function BuildSuccessMessage({ url }) {
  return (
    <div className="bg-green-600 text-green-100 p-4">
      <p className="font-bold mb-4">You will be redirected to the sandbox shortly</p>
      <pre className="text-sm"><code>{url}</code></pre>
    </div>
  )
}

function InstanceProgress() {
  const [error, setError] = useState(false)
  const [state, setState] = useState({
    progress: 0,
    previewUrl: null,
    logs: []
  })

  // @todo needs a refactor for 1st request and the subsequent.
  useEffect(() => {
    const stateUrl = drupalSettings.stateUrl;
    const interval = setInterval(async () => {
      const res = await fetch(stateUrl);
      const json = await res.json();
      if (res.status === 404) {
        setError(true)
        clearInterval(interval);
      }
      // If we're no longer interacting with a job, the job has finished and we
      // now have our preview instance.
      if (json.type === 'preview') {
        clearInterval(interval)
      }
      if (json.url && json.state === 'ready') {
       setTimeout(() => {
         window.location.href = json.url;
       }, 3000);
      }
      setState(json)
    }, 2000);
    return () => clearInterval(interval);
  }, []);

  if (error) {
    return (
      <div className="flex flex-col mb-10 max-w-screen-lg container mx-auto">
        <p>{state.message}</p>
        <p><a href={'/'}>Go back and try again</a></p>
      </div>
    )
  }

  let progressTitle = "We're building your instance..."
  if (state.state === 'failed') {
    progressTitle = 'There was a build error';
  }
  // @todo need a successful build to test.
  if (state.type === 'preview' && state.progress === 100) {
    progressTitle = 'Sandbox built!';
    console.log(state);
  }

  return (
    <div className="flex flex-col mb-10 max-w-screen-lg container mx-auto">
      <div className="flex flex-col items-center">
        <p className="py-4 text-xl font-bold">{progressTitle}</p>
        <progress className="my-2 w-full" max="100" value={state.progress}>{ state.progress }%</progress>
      </div>
      {state.state === 'failed'? [<BuildErrorMessage key={state.state} logs={state.logs} />] : null}
      {state.state === 'ready' ? [<BuildSuccessMessage key={state.state} url={state.url} />] : null}
      <div>
        <pre className="h-96 overflow-scroll bg-gray-900 text-gray-50 text-xs p-4">
          {state.logs.map(item => <code className="block m-0 p-0" key={item.id}>{item.message.replace(/^\s+|\s+$/g, '')}</code>)}
        </pre>
      </div>
    </div>
  )
}

const progressMount = document.getElementById('progress_mount');
if (progressMount) {
  ReactDOM.render(<InstanceProgress />, progressMount)
}
