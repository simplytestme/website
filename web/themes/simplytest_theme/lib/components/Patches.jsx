import React from "react";
import PropTypes from "prop-types";
import { btnDashed, removeButton } from "../ui";

// NOTE: We receive patches and setPatches as props, since this component is
// shared between the root project and any additional projects.
function Patches({ patches, setPatches }) {
  if (patches.length === 0) {
    patches.push("");
  }

  function addPatch() {
    setPatches([...patches, '']);
  }

  function removeExtraPatch(k) {
    const patchesCopy = patches.slice();
    patchesCopy.splice(k, 1);
    setPatches(patchesCopy);
  }

  return (
    <div className="flex w-full flex-col items-start gap-3">
      {patches.map((patch, k) => (
        // NOTE: we should not use `k`, but if we use `patch`, the value is
        // constantly modified onChange as the array is rebuilt. This is a major
        // peformance problem as we're constantly rebuilding the entire component
        // whenever someone types.
        // eslint-disable-next-line react/no-array-index-key
        <div key={k} id={`project_patch_${k}`} className="flex w-full flex-row gap-2.5">
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
            className="min-w-0 flex-1 rounded-lg border border-st-field-line bg-white px-3.5 py-3 font-mono text-[13px] text-st-body"
            placeholder="https://www.drupal.org/files/..."
          />
          <button
            className={removeButton}
            type="button"
            aria-label={`Remove patch ${k}`}
            onClick={() => removeExtraPatch(k)}
          >
            <span aria-hidden="true">×</span>
          </button>
        </div>
      ))}
      <button type="button" className={btnDashed} onClick={addPatch}>
        + Add another patch
      </button>
    </div>
  );
}
Patches.propTypes = {
  patches: PropTypes.arrayOf(PropTypes.string).isRequired,
  setPatches: PropTypes.func.isRequired
};
export default Patches;
