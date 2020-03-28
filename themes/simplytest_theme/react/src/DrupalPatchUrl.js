import React from 'react';
import Parser from 'html-react-parser';

class DrupalPatchUrl extends React.Component {

  updateValue = (event) => {
    var value = (event.target.value !== '_none') ? event.target.value : null;
    this.props.updateState({ [event.target.name]: value });
  }

  render() {
    var extra_projects = '';
    for (let i = 0; i < this.props.getState('extraProjects').length; i++) {
      if (this.props.getState('extraProjects_' + i)) {
        extra_projects += '<option value="' + this.props.getState('extraProjects_' + i) + '">' + this.props.getState('extraProjects_' + i) + '</option>';
      }
    }
    return (
      <div className="simplytest-submission">
        <input type="textfield" name={this.props.name} onChange={this.updateValue} />
        <select name={this.props.projectName} onChange={this.updateValue}>
          <option value="_none">Select a Project</option>
          {this.props.getState('projectMachineName') ? <option value={this.props.getState('projectMachineName')}>{this.props.getState('projectMachineName')}</option> : null}
          { Parser(extra_projects) }
          <option value="core">Drupal Core</option>
        </select>
        </div>
   );
  }
}

export default DrupalPatchUrl;
