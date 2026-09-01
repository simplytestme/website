import React, { useId } from "react";
import PropTypes from "prop-types";
import { btnDashed, removeButton } from "../ui";

// NOTE: We receive patches and setPatches as props, since this component is
// shared between the root project and any additional projects.
function Patches({ patches, setPatches }) {
  // The launch form renders this component once for the root project and again
  // for every additional project, so the hint below the fields needs an id that
  // is unique per instance before aria-describedby can point at it.
  const hintId = `${useId()}patch-hint`;

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
            aria-describedby={hintId}
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
      {/* The placeholder vanishes as soon as someone types, taking the only
          hint about the expected format with it. See #3494635. */}
      <p
        id={hintId}
        className="m-0 w-full break-words text-[13px] leading-normal text-st-muted"
      >
        Paste the full URL of a patch file, like{" "}
        <span className="font-mono">
          https://www.drupal.org/files/issues/3494635-12.patch
        </span>
      </p>
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
