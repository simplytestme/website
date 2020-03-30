import React from 'react';
import {VERSIONS_ENDPOINT} from "./config/const";
import Parser from 'html-react-parser';

class DrupalVersionSelector extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      versions: {},
      value: '',
    };
  }

  componentDidUpdate(prevProps) {
    if (this.props.enabled && prevProps.enabled !== this.props.enabled) {
      // Reset value if project is updated.
      this.props.updateState({ [this.props.name]: null });
      this.getDrupalVersions(this.props.projectMachineName);
    }
  }

  getDrupalVersions(projectMachineName) {
    let url = VERSIONS_ENDPOINT;
    fetch(url.replace('{project}', projectMachineName), {
      //mode: 'no-cors'
    })
      .then(response => response.text())
      .then((response) => {
        this.setState({ versions: JSON.parse(response) });
      }).catch((err) => {
      console.log('fetch', err)
    });
  }

  updateValue = (event) => {
    var value = (event.target.value !== '_none') ? event.target.value : null;
    this.setState({ value: value });
    this.props.updateState({ [this.props.name]: value });
  }

  render() {
    let versions = this.state.versions;
    let version_list = Object.keys(versions).map((key) => {
      let drupal_version = '<option disabled="true">' + key + '</option>';
      let module_versions = Object.keys(versions[key]).map((version) => {
        return '<option value="' + version + '">' + version + '</option>';
      });
      return drupal_version + module_versions.join('');
    });
    return (
      <select className={!this.props.enabled ? 'hidden' : 'visible'} onChange={this.updateValue} value={this.state.value}>
        <option value="_none">Select a Version</option>
        {Parser(version_list.join(''))}
      </select>
    );
  }
}

export default DrupalVersionSelector;
