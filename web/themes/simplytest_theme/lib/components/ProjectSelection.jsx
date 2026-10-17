import { useEffect, useState } from 'react';

import ProjectAutocomplete from './ProjectAutocomplete';
import VersionSelector from './VersionSelector';

function ProjectSelection({
  onChange,
  onClear = () => {},
  excludeCore = false,
  appliedCoreConstraint = null,
  additionalBtn = false,
  initialDefaultProject = null,
  initialDefaultVersion = null,
  rootProjectVersion = null,
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
        setSelectedItem={(item) => {
          // A version belongs to the project it was picked for. Kept across a
          // switch, it launched the new project with the old one's release
          // while the select showed the new list's first option. A deep link
          // lookup re-selects the same project, and keeps its linked version.
          if (item?.shortname !== project?.shortname) {
            setVersion('');
          }
          setProject(item);
          // onChange only fires for a complete selection, so a cleared field
          // has to be reported on its own.
          if (!item) {
            onClear();
          }
        }}
        additionalBtn={additionalBtn}
        excludeCore={excludeCore}
      />
      {/* @todo version select can have a duplicate ID */}
      {/* Keyed by project so a switch drops the old release list. Otherwise
          the default picks the old project's first version before the new
          list arrives, and nothing replaces it afterwards. */}
      <VersionSelector
        key={project?.shortname ?? ''}
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
export default ProjectSelection;
