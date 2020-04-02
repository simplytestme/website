import React from 'react';

class DrupalCoreSelector extends React.Component {

  constructor(props) {
    super(props);
    this.getDrupalCoreBranches = this.getDrupalCoreBranches.bind(this);
    this.state = {
      options: [],
    };
  }

  componentDidMount = () => {
    this.props.updateState({ [this.props.name]: null, [this.props.profileName]: 'standard' });
    this.setState({ profileSelected: 'standard' })
  }

  getDrupalCoreBranches(versions) {
    const parseString = require('react-native-xml2js').parseString;
    // TO DO - remove proxy and cache in STM.
    var proxy = 'https://cors-anywhere.herokuapp.com/';
    for (let i = 0; i < versions.length; i++) {
      let url = 'https://updates.drupal.org/release-history/drupal/' + versions[i] + '.x';
      fetch(proxy + url, {
        //mode: 'no-cors'
      })
        .then(response => response.text())
        .then((response) => {
          parseString(response, (err, result) => {
            var feedData = JSON.parse(JSON.stringify(result));
            let releases = this.state.options.concat(feedData.project.releases[0].release);
            this.setState({ options: releases });
            return;
          });
        }).catch((err) => {
          console.log('fetch', err)
        });
    }
  }

  componentDidUpdate(prevProps) {
    if (this.props.version && prevProps.version !== this.props.version) {
      this.setState({ options: [] });
      if (parseInt(this.props.version, 10)) {
        this.getDrupalCoreBranches([this.props.version]);
      }
      else {
        this.getDrupalCoreBranches([8, 7, 6]);
      }
    }
  }

  updateValue = (event) => {
    var value = (event.target.value !== '_none') ? event.target.value : null;
    this.props.updateState({ [event.target.name]: value });
  }

  render() {
    return (
      <div className="core-profile-selector">
        <div className="core-selector">
          <label>Drupal Core: </label>
          <select className="drupalCoreSelector" name={this.props.name} onChange={this.updateValue}>
            <option value="_none">Select a Branch</option>
            {this.state.options.map(function (release, i) {
              return (
                <option value={release.tag} key={release.tag}>
                  {release.name}
                </option>
              )
            })}
          </select>
        </div>
        <div className="install-profile">
          <label>Install Profile: </label>
          <select className="drupalProfileSelector" value={this.state.profileSelected} onChange={(event) => {
            this.props.updateState({ [this.props.profileName]: event.target.value })
            this.setState({ profileSelected: event.target.value })
          }}>
            <option value="standard">Standard</option>
            <option value="minimal">Minimal</option>
          </select>
        </div>
        <div className="manual-install">
          <input type="checkbox" name={this.props.manualInstall} onChange={(event) => {
            this.props.updateState({ [this.props.manualInstall]: event.target.checked });
          }} />Manual Install
        </div>
    </div>
  );
  }
}

export default DrupalCoreSelector;
