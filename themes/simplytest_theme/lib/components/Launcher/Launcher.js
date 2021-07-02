import React, { useState } from "react";
import { useLauncher } from "../../context/launcher";
import ProjectSelection from "../ProjectSelection";
import AdvancedOptions from "../AdvancedOptions";
import OneClickDemos from "../OneClickDemos";

function Launcher() {
  const [errors, setErrors] = useState([]);
  const {
    canLaunch,
    getLaunchPayload,
    setMainProject,
    selectedProject,
    selectedVersion
  } = useLauncher();
  function onSubmit(e) {
    e.preventDefault();
    const payload = JSON.stringify(getLaunchPayload());
    fetch(`/launch-project`, {
      method: "POST",
      body: payload,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json"
      }
    })
      .then(res => {
        res
          .json()
          .then(json => {
            if (res.ok) {
              window.location.href = json.progress;
            } else {
              setErrors(json.errors);
            }
          })
          .catch(error => {
            setErrors([`${error.name}: ${error.message}`]);
          });
      })
      .catch(error => {
        setErrors([`${error.name}: ${error.message}`]);
      });
  }

  return (
    <div className="bg-gradient-to-r from-flat-blue py-5">
      {errors.map((error, i) => {
        return (
          <div
            key={error}
            className="container max-w-screen-lg mx-auto px-4 py-2 mb-4 bg-red-600 text-red-100"
          >
            {error}
          </div>
        );
      })}
      <form
        className="flex flex-col mb-10 max-w-screen-lg container mx-auto pl-130"
        onSubmit={onSubmit}
      >
        <div className="flex flex-row flex-grow items-center mobile-column-flex desktop-align-item-end">
          <ProjectSelection
            onChange={setMainProject}
            initialDefaultProject={selectedProject}
            initialDefaultVersion={selectedVersion}
          />
          <button
            className="px-4 py-1 text-xl border rounded-md shadow bg-white hover:bg-gray-50 cursor-pointer disabled:cursor-not-allowed bg-yellow-tan"
            disabled={!canLaunch}
          >
            Launch Sandbox
          </button>
        </div>
        <AdvancedOptions />
        <OneClickDemos />
      </form>
    </div>
  );
}
export default Launcher;
