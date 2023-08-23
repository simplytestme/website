import React, { useState, useEffect } from "react";
import { fetchWithCallback } from '../utils'

function doLaunch(demo, setProcessing, setErrors) {
  setProcessing(demo.id);
  fetch(`/one-click-demos/${demo.id}`, {
    method: "POST",
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
            setProcessing("");
            console.error(json);
            setErrors([json.message])
          }
        })
        .catch(error => {
          setProcessing("");
          console.log(error);
          setErrors([error.message])
        });
    })
    .catch(error => {
      setProcessing("");
      console.log(error);
      alert("There was an error, check the console.");
    });
}

function OneClickDemos({setErrors}) {
  const [demos, setDemos] = useState([]);
  const [processing, setProcessing] = useState("");
  useEffect(() => {
    fetchWithCallback("/one-click-demos", setDemos);
  }, []);

  return (
    <fieldset className="mt-4">
      <summary className="font-medium text-xl text-white">
        One Click Demos
      </summary>
      <div className="flex md:grid-cols-2 gap-2 mt-2 mobile-column-flex">
        {demos.map(demo => (
          <button
            key={demo.id}
            type="button"
            disabled={processing !== ""}
            className={`w-full p-3 bg-off-white hover:bg-flat-blue hover:text-white rounded-sm my-1 shadow-sm flex flex-row items-center ${
              processing === demo.id ? "bg-flat-blue text-white" : ""
            }`}
            onClick={event => {
              event.preventDefault();
              doLaunch(demo, setProcessing, setErrors);
            }}
          >
            <span className="flex-grow">{demo.title}</span>
            {processing === demo.id ? (
              <svg
                key="processing"
                className="animate-spin h-5 w-5 text-white"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  className="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  strokeWidth="4"
                />
                <path
                  className="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
              </svg>
            ) : null}
          </button>
        ))}
      </div>
    </fieldset>
  );
}

export default OneClickDemos;
