import React, { useState, useEffect } from "react";
import PropTypes from "prop-types";
import ProjectAutocomplete from "./ProjectAutocomplete";
import VersionSelector from "./VersionSelector";

function ProjectSelection({
  onChange,
  appliedCoreConstraint,
  additionalBtn,
  initialDefaultProject,
  initialDefaultVersion,
  rootProjectVersion
}) {
  const [project, setProject] = useState(initialDefaultProject);
  const [version, setVersion] = useState(initialDefaultVersion);

  useEffect(
    () => {
      setProject(initialDefaultProject);
      setVersion(initialDefaultVersion);
    },
    [initialDefaultProject, initialDefaultVersion]
  );

  useEffect(
    () => {
      if (project && version) {
        onChange(project, version);
      }
    },
    [project, version, onChange]
  );

  return (
    <div className="flex flex-row flex-grow mobile-column-flex desktop-align-item-end">
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
      />
    </div>
  );
}
ProjectSelection.propTypes = {
  onChange: PropTypes.func.isRequired,
  appliedCoreConstraint: PropTypes.string
};
export default ProjectSelection;
