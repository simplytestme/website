import React from "react";
import PropTypes from "prop-types";

// NOTE: We receive patches and setPatches as props, since this component is
// shared between the root project and any additional projects.
function Patches({ patches, setPatches }) {
  if (patches.length === 0) {
    patches.push("");
  }

  function addPatch() {
    setPatches([...patches, []]);
  }

  function removeExtraPatch(k) {
    const patchesCopy = patches.slice();
    patchesCopy.splice(k, 1);
    setPatches(patchesCopy);
  }

  return (
    <div className="w-full sm:w-1/2 mt-4">
      <h3 className="mb-2 text-sm text-white">
        Add patches on the chosen project
      </h3>
      {patches.map((patch, k) => (
        // NOTE: we should not use `k`, but if we use `patch`, the value is
        // constantly modified onChange as the array is rebuilt. This is a major
        // peformance problem as we're constantly rebuilding the entire component
        // whenever someone types.
        // eslint-disable-next-line react/no-array-index-key
        <div key={k} id={`project_patch_${k}`} className="mb-2 flex flex-row">
          <label className="sr-only" htmlFor={`project_patch_url_${k}`}>
            Project patch {k}
          </label>
          <input
            id={`project_patch_url_${k}`}
            type="url"
            value={patch}
            onChange={event => {
              const newPatches = [...patches];
              newPatches[k] = event.target.value;
              setPatches(newPatches);
            }}
            className="text-lg font-sans border rounded-md shadow px-4 py-1 flex-grow w-full"
            placeholder="https://www.drupal.org/files/..."
          />
          <button
            className="text-white text-2xl font-semibold w-8"
            type="button"
            onClick={() => removeExtraPatch(k)}
          >
            <span>×</span>
          </button>
        </div>
      ))}
      <button
        type="button"
        className="text-base p-2 rounded-md btn-blue"
        onClick={addPatch}
      >
        Add patch
      </button>
    </div>
  );
}
Patches.propTypes = {
  patches: PropTypes.arrayOf(PropTypes.string).isRequired,
  setPatches: PropTypes.func.isRequired
};
export default Patches;
