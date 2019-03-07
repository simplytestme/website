import React from 'react';
import DrupalCoreSelector from './DrupalCoreSelector';

class DrupalSelection extends React.Component {
    render() {
        return (
            <div className="drupal-selection">
                <div className="drupal-core-selection">
                    <DrupalCoreSelector />
                </div>
                <button value="yes">Select</button>
            </div>
        );
    }
}

export default DrupalSelection;
