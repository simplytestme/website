import React from 'react';
import {VERSIONS_ENDPOINT} from "./config/const";
import Parser from 'html-react-parser';

class DrupalVersionSelector extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      versions: {},
    };
  }

  componentDidUpdate(prevProps) {
    if (this.props.enabled && prevProps.enabled !== this.props.enabled) {
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
        this.setState({versions: JSON.parse(response)});
      }).catch((err) => {
      console.log('fetch', err)
    });
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
      <select className={!this.props.enabled ? 'hidden' : 'visible'}>
        {Parser(version_list.join(''))}
      </select>
    );
  }
}

export default DrupalVersionSelector;
