import React, { useEffect, useState } from "react";
import BuildErrorMessage from "./BuildErrorMessage";
import BuildSuccessMessage from "./BuildSuccessMessage";

function InstanceProgress() {
  const [error, setError] = useState(false);
  const [state, setState] = useState({
    progress: 0,
    previewUrl: null,
    logs: []
  });

  // @todo needs a refactor for 1st request and the subsequent.
  useEffect(() => {
    const { stateUrl } = drupalSettings;
    const interval = setInterval(async () => {
      const res = await fetch(stateUrl);
      const json = await res.json();
      if (res.status === 404) {
        setError(true);
        clearInterval(interval);
      }
      // If we're no longer interacting with a job, the job has finished and we
      // now have our preview instance.
      if (json.type === "preview") {
        clearInterval(interval);
      }
      if (json.url && json.state === "ready") {
        setTimeout(() => {
          window.location.href = json.url;
        }, 3000);
      }
      setState(json);
    }, 3000);
    return () => clearInterval(interval);
  }, []);

  if (error) {
    return (
      <div className="flex flex-col mb-10 max-w-screen-lg container mx-auto">
        <p>{state.message}</p>
        <p>
          <a href="/">Go back and try again</a>
        </p>
      </div>
    );
  }

  let progressTitle = "We're building your instance...";
  if (state.state === "failed") {
    progressTitle = "There was a build error";
  }
  // @todo need a successful build to test.
  if (state.type === "preview" && state.progress === 100) {
    progressTitle = "Sandbox built!";
    console.log(state);
  }

  return (
    <div className="flex flex-col mb-10 max-w-screen-lg container mx-auto">
      <div className="flex flex-col items-center">
        <p className="py-4 text-xl font-bold">{progressTitle}</p>
        <progress className="my-2 w-full" max="100" value={state.progress}>
          {state.progress}%
        </progress>
      </div>
      {state.state === "failed"
        ? [<BuildErrorMessage key={state.state} logs={state.logs} />]
        : null}
      {state.state === "ready"
        ? [<BuildSuccessMessage key={state.state} url={state.url} />]
        : null}
      <div>
        <pre className="h-96 overflow-scroll bg-gray-900 text-gray-50 text-xs p-4">
          {state.logs.map(item => (
            <code className="block m-0 p-0" key={item.id}>
              {item.message.replace(/^\s+|\s+$/g, "")}
            </code>
          ))}
        </pre>
      </div>
    </div>
  );
}

export default InstanceProgress;
