import React from 'react';

class DrupalOneClickDemo extends React.Component {

  constructor(props) {
    super(props);
    this.state = {
      ocds: []
    };
  }

  componentDidMount() {
    fetch('/simplytest/ocd').then((res) => res.json().then((data) => {
      this.setState((state) => ({ ocds: data}));
    }));
  }

  render() {
    let ocds = this.state.ocds.map((ocd, key) =>
      <button name={ocd.ocd_id} onClick={() => {this.props.submitProject(ocd.ocd_id);}}>{ocd.title}</button>
    );
    return (
      <div className="simplytest-submission">
        { ocds }
      </div>
    );
  }
}

export default DrupalOneClickDemo;
