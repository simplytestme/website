import { useEffect, useState } from 'react';
import PropTypes from 'prop-types';

import ProjectAutocomplete from './ProjectAutocomplete';
import VersionSelector from './VersionSelector';

function ProjectSelection({
  onChange,
  appliedCoreConstraint,
  additionalBtn,
  initialDefaultProject,
  initialDefaultVersion,
  rootProjectVersion,
}) {
  const [project, setProject] = useState(initialDefaultProject);
  const [version, setVersion] = useState(initialDefaultVersion || '');

  useEffect(() => {
    setProject(initialDefaultProject);
    setVersion(initialDefaultVersion || '');
  }, [initialDefaultProject, initialDefaultVersion]);

  useEffect(() => {
    if (project && version) {
      onChange(project, version);
    }
    // Fires when the selection changes, not when the parent re-renders with
    // a new onChange identity.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [project, version]);

  return (
    <div className="flex flex-1 flex-col gap-3.5 lg:flex-row lg:items-end">
      <ProjectAutocomplete
        initialProject={project}
        setSelectedItem={setProject}
        additionalBtn={additionalBtn}
      />
      {/* @todo version select can have a duplicate ID */}
      <VersionSelector
        initialVersion={version}
        selectedProject={project}
        selectedVersion={version}
        setSelectedVersion={setVersion}
        appliedCoreConstraint={appliedCoreConstraint}
        rootProjectVersion={rootProjectVersion}
        compact={additionalBtn}
      />
    </div>
  );
}
ProjectSelection.defaultProps = {
  appliedCoreConstraint: null,
  additionalBtn: false,
  initialDefaultProject: null,
  initialDefaultVersion: null,
  rootProjectVersion: null,
};
ProjectSelection.propTypes = {
  onChange: PropTypes.func.isRequired,
  appliedCoreConstraint: PropTypes.string,
  additionalBtn: PropTypes.bool,
  initialDefaultProject: PropTypes.shape({
    shortname: PropTypes.string.isRequired,
  }),
  initialDefaultVersion: PropTypes.string,
  rootProjectVersion: PropTypes.string,
};
export default ProjectSelection;
