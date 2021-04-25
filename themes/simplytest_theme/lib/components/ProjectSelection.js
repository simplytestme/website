import React, {useState, useEffect} from 'react'
import PropTypes from 'prop-types'
import ProjectAutocomplete from './ProjectAutocomplete'
import VersionSelector from './VersionSelector'

function ProjectSelection({ onChange, appliedCoreConstraint, additionalBtn }) {
  const [project, setProject] = useState(null);
  const [version, setVersion] = useState('');

  useEffect(() => {
    if (project && version) {
      onChange(project, version)
    }
  }, [project, version, onChange])

  return (
    <div className="flex flex-row flex-grow mobile-column-flex desktop-align-item-end">
      <ProjectAutocomplete setSelectedItem={setProject} additionalBtn={additionalBtn} />
      {/* @todo version select can have a duplicate ID */}
      <VersionSelector selectedProject={project} selectedVersion={version} setSelectedVersion={setVersion} appliedCoreConstraint={appliedCoreConstraint} />
    </div>
  )
}
ProjectSelection.propTypes = {
  onChange: PropTypes.func.isRequired,
  appliedCoreConstraint: PropTypes.string
}
export default ProjectSelection;
