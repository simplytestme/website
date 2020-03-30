import React from 'react';
import AutoComplete from 'react-autocomplete';
import {PROJECTS_ENDPOINT} from './config/const';
import DrupalVersionSelector from "./DrupalVersionSelector";

class DrupalProjectSelector extends React.Component {

  constructor(props) {
    super(props);
    this.state = {
      modules: [],
      projectName: '',
      projectMachineName: '',
      projectSelected: false
    };
  }

  getDrupalModules(str) {
    this.setState({projectName: str});
    fetch(PROJECTS_ENDPOINT + '?string=' + str, {
      //mode: 'no-cors'
    })
    .then(response => response.text())
    .then((response) => {
      if (response !== 'false') {
        let list = JSON.parse(response).map((module) => { return {label: module.title + " (" + module.shortname + ") - " + module.type, name: module.shortname}});
        this.setState({modules: list});
      }
      else {
        // Update state if no result from the server.
        this.setState({modules: []});
      }
    }).catch((err) => {
      console.log('fetch', err)
    });
  }

  getProjectName(label) {
    let module = this.state.modules.find((item) => item.label === label);
    return module.name;
  }

  render() {
    return (
      <div className="simplytest-submission">
        <AutoComplete
          items={this.state.modules}
          shouldItemRender={(item, value) => item.label.toLowerCase().indexOf(value.toLowerCase()) > -1}
          getItemValue={item => item.label}
          renderItem={(item, highlighted) =>
            <div
              key={item.name}
              style={{ backgroundColor: highlighted ? '#eee' : 'transparent'}}>
              {item.label}
            </div>
          }
          value={this.state.projectName}
          onChange={e => {
            this.props.updateState({ [this.props.name]: null, [this.props.versionName]: null })
            this.getDrupalModules(e.target.value)
            this.setState({projectSelected: false})
          }}
          onSelect={value => {
            this.props.updateState({ [this.props.name]: this.getProjectName(value) })
            this.setState({ projectName: value, projectMachineName: this.getProjectName(value), projectSelected: true })
          }}
          />
          <DrupalVersionSelector
            enabled={this.state.projectSelected}
            projectMachineName={this.state.projectMachineName}
            updateState={this.props.updateState}
            name={this.props.versionName}
          />
        </div>
   );
  }
}

export default DrupalProjectSelector;
