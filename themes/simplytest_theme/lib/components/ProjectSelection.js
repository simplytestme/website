import React, {useState, useEffect} from 'react'
import PropTypes from 'prop-types'
import ProjectAutocomplete from './ProjectAutocomplete'
import VersionSelector from './VersionSelector'

function ProjectSelection({ onChange }) {
  const [project, setProject] = useState(null);
  const [version, setVersion] = useState('');

  useEffect(() => {
    if (project && version) {
      onChange(project, version)
    }
  }, [project, version, onChange])

  return (
    <div className="flex flex-row flex-grow">
      <ProjectAutocomplete setSelectedItem={setProject} />
      <VersionSelector selectedProject={project} selectedVersion={version} setSelectedVersion={setVersion} />
    </div>
  )
}
ProjectSelection.propTypes = {
  onChange: PropTypes.func.isRequired
}
export default ProjectSelection;
