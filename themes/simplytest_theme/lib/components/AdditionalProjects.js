import React, { useState } from "react";
import { useLauncher } from "../context/launcher";
import ProjectSelection from "./ProjectSelection";
import Patches from "./Patches";

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
    <div>
      {additionalProjects.map((project, k) => (
        <div
          key={project.shortname}
          id={`additional_project_${k}`}
          className="py-4 border-b"
        >
          <div className="flex flex-wrap mb-4 sm:w-1/2">
            <div className="flex-grow">
              <ProjectSelection
                appliedCoreConstraint={drupalVersion}
                rootProjectVersion={selectedVersion}
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
                    const newProjects = [...additionalProjects];
                    newProjects[k] = {
                      version: changedVersion,
                      patches: [],
                      ...project
                    };
                    setAdditionalProjects(newProjects);
                  }
                }}
              />
            </div>
            <div className="flex-shrink-0 mr-2">
              <button
                className="text-white text-2xl font-semibold"
                type="button"
                onClick={() => removeExtraProject(k)}
              >
                <span>×</span>
              </button>
            </div>
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
      <div className="pt-2">
        <button
          type="button"
          className="text-base p-2 rounded-md btn-blue"
          onClick={addAdditionalProject}
        >
          Add additional project
        </button>
      </div>
    </div>
  );
}

export default AdditionalProjects;
