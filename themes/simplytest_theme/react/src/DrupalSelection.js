import React from 'react';
import DrupalProjectSelector from './DrupalProjectSelector';
import DrupalCoreSelector from './DrupalCoreSelector';
import DrupalPatchUrl from './DrupalPatchUrl';
import DrupalOneClickDemo from './DrupalOneClickDemo';
import {SIMPYTEST_LAUNCHER} from './config/const';

class DrupalSelection extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      extraProjects: [
        DrupalProjectSelector
      ],
      extraPatches: [
        DrupalPatchUrl
      ]
    };
  }

  getState = (name) => {
    return (this.state[name]) ? this.state[name] : null;
  }

  updateState = (state) => {
    this.setState(state);
  }

  addProject = () => {
    const projects = this.state.extraProjects.push(DrupalProjectSelector);
    this.setState({ projects });
  }

  addPatches = () => {
    const patches = this.state.extraPatches.push(DrupalPatchUrl);
    this.setState({ patches });
  }

  drupalVersion = () => {
    return (this.state.projectVersion) ? this.state.projectVersion.charAt(0) : null;
  }

  submitProject = (ocd_id) => {
    var project_details = {
      'project': this.getState('projectMachineName'),
      'version': this.getState('projectVersion'),
      'additionals': [],
      'patches': [],
    };
    if (this.getState('drupalVersion')) {
      project_details.drupal_version = this.getState('drupalVersion');
    }
    if (this.getState('manualInstall')) {
      project_details.bypass_install = this.getState('manualInstall');
    }
    if (ocd_id) {
      project_details.ocd_id = ocd_id;
    }
    for (let i = 0; i < this.getState('extraProjects').length; i++) {
      if (this.getState('extraProjects_' + i)) {
        project_details.additionals.push({ name: this.getState('extraProjects_' + i), version: this.getState('extraProjectVersion_' + i)});
      }
    }
    for (let i = 0; i < this.getState('extraPatches').length; i++) {
      if (this.getState('extraPatches_' + i)) {
        project_details.patches.push({ url: this.getState('extraPatches_' + i), apply_to: this.getState('extraPatchProject_' + i) });
      }
    }
    // To Do: Send these details to Drupal.
    console.log(project_details);
    const requestOptions = {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(project_details)
    };
    fetch(SIMPYTEST_LAUNCHER, requestOptions)
      .then(response => response.json());
  }

  render() {
    const projects = this.state.extraProjects.map((Element, index) => {
      return <Element name={'extraProjects_' + index} versionName={'extraProjectVersion_' + index} updateState={this.updateState} />
    });
    const patches = this.state.extraPatches.map((Element, index) => {
      return <Element name={'extraPatches_' + index} projectName={'extraPatchProject_' + index} updateState={this.updateState} getState={this.getState} />
    });
    var extraProps = {
      disabled: !this.state.projectVersion ? 'disabled' : ''
    };
    return (
        <div className="drupal-selection">
            <div className="drupal-project-selection">
            <DrupalProjectSelector name="projectMachineName" versionName="projectVersion" updateState={this.updateState} /> <button name="launch" {...extraProps} onClick={() => {this.submitProject(null);}}>Launch Sandbox</button>
            </div>
            <fieldset className="advance-options-selection collapsible">
              <legend>Advanced Options:</legend>
              <DrupalCoreSelector version={this.drupalVersion()} updateState={this.updateState} name="drupalVersion" profileName='profileSelected' manualInstall='manualInstall' />
              <div className="extra-projects"><label>Add Projects: </label> { projects } <button name="add-another-projects" onClick={ this.addProject }>Add Another</button></div>
              <div className="patches"><label>Add Patches: </label>{ patches } <button name="add-another-patcjes" onClick={ this.addPatches }>Add Another</button></div>
            </fieldset>
          <fieldset className="one-click-demo collapsible">
            <legend>One Click Demo:</legend>
            <DrupalOneClickDemo submitProject={this.submitProject} updateState={this.updateState} />
          </fieldset>
        </div>
    );
  }
}

export default DrupalSelection;
