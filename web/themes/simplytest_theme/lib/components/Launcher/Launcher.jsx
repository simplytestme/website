import React, { useState } from "react";
import { useLauncher } from "../../context/launcher";
import ProjectSelection from "../ProjectSelection";
import AdvancedOptions from "../AdvancedOptions";
import OneClickDemos from "../OneClickDemos";
import { btnPrimary, dangerBlock } from "../../ui";

// Carries the submission over to the progress page so it can label the build
// steps and offer an "edit and try again" link. The launcher context already
// parses project/version/patch back out of a query string.
function progressQuery(payload) {
  const params = new URLSearchParams();
  params.set("project", payload.project.shortname);
  params.set("version", payload.project.version);
  if (payload.project.title) {
    params.set("title", payload.project.title);
  }
  if (payload.drupalVersion) {
    params.set("core", payload.drupalVersion);
  }
  if (payload.installProfile) {
    params.set("profile", payload.installProfile);
  }
  (payload.project.patches || [])
    .filter(patch => patch !== "")
    .forEach(patch => params.append("patch", patch));
  return params.toString();
}

function Launcher() {
  const [errors, setErrors] = useState([]);
  const [submitting, setSubmitting] = useState(false);
  const { canLaunch, getLaunchPayload, selectedProject, selectedVersion, setMainProject } = useLauncher();

  function onSubmit(e) {
    e.preventDefault();
    const payload = getLaunchPayload();
    setSubmitting(true);
    fetch(`/launch-project`, {
      method: "POST",
      body: JSON.stringify(payload),
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
              window.location.href = `${json.progress}?${progressQuery(payload)}`;
            } else {
              setSubmitting(false);
              setErrors(json.errors || [json.message || "Something went wrong. Try again in a minute."]);
            }
          })
          .catch(error => {
            setSubmitting(false);
            setErrors([`${error.name}: ${error.message}`]);
          });
      })
      .catch(error => {
        setSubmitting(false);
        setErrors([`${error.name}: ${error.message}`]);
      });
  }

  return (
    <div className="flex flex-col gap-[72px] px-6 pb-20 pt-2 lg:px-16">
      <OneClickDemos setErrors={setErrors} />

      <section className="flex flex-col gap-6">
        <div className="flex flex-col gap-2 border-t border-st-line pt-6">
          <span className="eyebrow text-st-soft">Any project on drupal.org</span>
          <h2 className="m-0 text-3xl font-bold tracking-[-0.025em] text-st-body">
            Or test a specific project
          </h2>
        </div>

        {errors.length > 0 && (
          <div className={dangerBlock} role="alert">
            {errors.map(error => (
              <p className="m-0 py-1" key={error}>
                {error}
              </p>
            ))}
          </div>
        )}

        <form
          className="rounded-2xl border border-st-line2 bg-white p-5 shadow-card sm:px-7 sm:pb-6 sm:pt-7"
          onSubmit={onSubmit}
        >
          <div className="flex flex-col gap-3.5 lg:flex-row lg:items-end">
            <ProjectSelection
              onChange={setMainProject}
              initialDefaultProject={selectedProject}
              initialDefaultVersion={selectedVersion}
            />
            <button className={`${btnPrimary} whitespace-nowrap`} disabled={!canLaunch || submitting}>
              {submitting ? "Launching…" : "Launch sandbox"}
            </button>
          </div>
          <AdvancedOptions />
        </form>
      </section>
    </div>
  );
}
export default Launcher;
