import React, { useEffect, useState } from "react";
import BuildErrorMessage from "./BuildErrorMessage";
import BuildSuccessMessage from "./BuildSuccessMessage";

// How long to wait between polls. The backend caches computed state for the
// same window, so polling faster than this only returns cached answers.
const POLL_INTERVAL = 3000;
// Give up after this many consecutive failed status requests.
const MAX_FAILURES = 5;

function InstanceProgress() {
  const [error, setError] = useState(false);
  const [state, setState] = useState({
    progress: 0,
    previewUrl: null,
    logs: []
  });

  useEffect(() => {
    const { stateUrl } = drupalSettings;
    let timeoutId = null;
    let stopped = false;
    let failures = 0;

    const schedule = delay => {
      if (!stopped) {
        timeoutId = setTimeout(poll, delay);
      }
    };

    // One request per tick, and the next tick is only scheduled once this one
    // finishes, so slow responses stretch the interval instead of overlapping.
    const poll = async () => {
      let json;
      try {
        const res = await fetch(stateUrl);
        if (res.status === 404) {
          setState(await res.json());
          setError(true);
          return;
        }
        if (!res.ok) {
          throw new Error(`Status request failed with ${res.status}`);
        }
        json = await res.json();
      } catch (e) {
        failures += 1;
        if (failures >= MAX_FAILURES) {
          setState(prev => ({
            ...prev,
            message:
              "We can't check on your sandbox right now. Reload the page to try again."
          }));
          setError(true);
          return;
        }
        // Back off so a struggling backend gets 6s, 12s, 24s, 48s of air.
        schedule(POLL_INTERVAL * 2 ** failures);
        return;
      }

      failures = 0;
      setState(json);
      // A preview means the job finished; a failed job never becomes one.
      // Either way the state is final and polling must stop.
      if (json.type === "preview") {
        if (json.url && json.state === "ready") {
          setTimeout(() => {
            window.location.href = json.url;
          }, 3000);
        }
        return;
      }
      if (json.state === "failed") {
        return;
      }
      schedule(POLL_INTERVAL);
    };

    poll();
    return () => {
      stopped = true;
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
    };
  }, []);

  if (error) {
    return (
      <div className="flex flex-col pb-10 max-w-screen-lg container mx-auto">
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
  if (state.type === "preview" && state.progress === 100) {
    progressTitle = "Sandbox built!";
  }

  return (
    <div className="flex flex-col pb-10 max-w-screen-lg container mx-auto">
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
