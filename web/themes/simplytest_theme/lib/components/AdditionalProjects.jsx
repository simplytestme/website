import React, { useState } from "react";
import { useLauncher } from "../context/launcher";
import ProjectSelection from "./ProjectSelection";
import Patches from "./Patches";
import { btnDashed, removeButton } from "../ui";

function AdditionalProjects() {
  const {
    additionalProjects,
    setAdditionalProjects,
    drupalVersion,
    selectedVersion
  } = useLauncher();
  const [additionalBtn, setAdditionalBtn] = useState(false);

  function addAdditionalProject() {
    setAdditionalProjects([
      ...additionalProjects,
      {
        title: "",
        shortname: "",
        version: "",
        patches: []
      }
    ]);
    setAdditionalBtn(true);
  }

  function removeExtraProject(k) {
    const additionalProjectsCopy = additionalProjects.slice();
    additionalProjectsCopy.splice(k, 1);
    setAdditionalProjects(additionalProjectsCopy);
  }

  return (
    <div className="flex w-full flex-col items-start gap-3">
      {additionalProjects.map((project, k) => (
        <div
          key={project.shortname}
          id={`additional_project_${k}`}
          className="flex w-full flex-col gap-3 border-b border-st-hairline2 pb-4 last:border-0"
        >
          <div className="flex w-full flex-row items-end gap-2.5">
            <ProjectSelection
              appliedCoreConstraint={drupalVersion}
              rootProjectVersion={selectedVersion}
              initialDefaultProject={
                project.shortname !== ""
                  ? {
                      shortname: project.shortname,
                      title: project.title,
                      type: project.type
                    }
                  : null
              }
              initialDefaultVersion={project.version}
              additionalBtn={additionalBtn}
              onChange={(changedProject, changedVersion) => {
                // @todo the state management for ProjectSelection needs refactor
                // onChange is technically called with each render, and the
                // component has no idea if it has really changed or not and ends
                // up being called on each render.
                if (
                  additionalProjects[k].shortname !==
                    changedProject.shortname ||
                  additionalProjects[k].version !== changedVersion
                ) {
                  // Copy the array. Mutating it in place and passing the
                  // same reference back makes React bail out of the render,
                  // so the patch field below never mounts. See #3571405.
                  const newProjects = [...additionalProjects];
                  newProjects[k] = {
                    version: changedVersion,
                    patches: [],
                    ...changedProject
                  };
                  setAdditionalProjects(newProjects);
                }
              }}
            />
            <button
              className={removeButton}
              type="button"
              aria-label={`Remove additional project ${k}`}
              onClick={() => removeExtraProject(k)}
            >
              <span aria-hidden="true">×</span>
            </button>
          </div>
          {project.shortname ? (
            <Patches
              patches={project.patches}
              setPatches={updatedPatches => {
                const newProjects = [...additionalProjects];
                newProjects[k].patches = updatedPatches;
                setAdditionalProjects(newProjects);
              }}
            />
          ) : null}
        </div>
      ))}
      <button type="button" className={btnDashed} onClick={addAdditionalProject}>
        + Add another project
      </button>
    </div>
  );
}

export default AdditionalProjects;
