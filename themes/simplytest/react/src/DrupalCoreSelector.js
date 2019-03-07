import React from 'react';
import DrupalSelection from './DrupalSelection';

class DrupalCoreSelector extends React.Component {

    constructor(props) {
        super(props);
        this.getDrupalCoreBranches = this.getDrupalCoreBranches.bind(this);
        this.state = {
            d7options: [],
            d8options: [],
        };
    }

    getDrupalCoreBranches() {
        const parseString = require('react-native-xml2js').parseString;
        //TO DO - remove proxy and cache in STM
        var proxy = 'https://cors-anywhere.herokuapp.com/';
        var d7url = 'https://updates.drupal.org/release-history/drupal/7.x';
        var d8url = 'https://updates.drupal.org/release-history/drupal/8.x';

        fetch(proxy + d8url, {
            //mode: 'no-cors'
        })
            .then(response => response.text())
            .then((response) => {
                parseString(response, (err, result) => {
                    var feedData = JSON.parse(JSON.stringify(result));
                    console.log(feedData.project.releases[0].release);
                    this.setState({d8options: feedData.project.releases[0].release});
                });
            }).catch((err) => {
            console.log('fetch', err)
        });

        fetch(proxy + d7url, {
            //mode: 'no-cors'
        })
            .then(response => response.text())
            .then((response) => {
                parseString(response, (err, result) => {
                    //console.log(response);
                    var feedData = JSON.parse(JSON.stringify(result));
                    this.setState({d7options: feedData.project.releases[0].release});
                });
            }).catch((err) => {
            console.log('fetch', err)
        });
    }

    componentDidMount() {
        this.getDrupalCoreBranches();
    }

    render() {
        return (
            <select className="drupalCoreSelector">
                <option disabled="true">Drupal 8 Releases</option>
                {this.state.d8options.map(function(release, i) { return (
                    <option value={release.tag} key={release.tag}>
                        {release.name}
                    </option>
                )})}
                <option disabled="true">Drupal 7 Releases</option>
                {this.state.d7options.map(function(release, i) { return (
                <option value={release.tag} key={release.tag}>
                    {release.name}
                </option>
                )})}
            </select>
        );
    }
}

export default DrupalCoreSelector;
