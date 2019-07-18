import React from 'react';
import DrupalProjectSelector from './DrupalProjectSelector';

class DrupalSelection extends React.Component {
    render() {
        return (
            <div className="drupal-selection">
                <div className="drupal-core-selection">
                    <DrupalProjectSelector />
                </div>
                <button value="yes">Select</button>
            </div>
        );
    }
}

export default DrupalSelection;
