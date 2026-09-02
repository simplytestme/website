import React from "react";
import PropTypes from "prop-types";
import { btnDashed, removeButton } from "../ui";

// NOTE: We receive patches and setPatches as props, since this component is
// shared between the root project and any additional projects.
function Patches({ patches, setPatches, idPrefix }) {
  // The launch form renders this component once for the root project and again
  // for every additional project. Every id below is built from idPrefix so the
  // instances do not collide: shared ids sent each additional project's label
  // and aria-describedby to the root project's field instead of its own.
  const hintId = `${idPrefix}_hint`;

  // A project holds no patches until someone types, but it always shows one
  // field so it is there the moment the project is selected. See #3571405.
  //
  // Derive that row instead of pushing it into `patches`. The prop is the
  // parent's own state array, so `patches.push("")` wrote into React state
  // during render. It happened to behave, because every read afterwards saw
  // the same mutated array, but it left the component relying on that and gave
  // the parent a state value it never set.
  const rows = patches.length === 0 ? [""] : patches;

  function addPatch() {
    setPatches([...rows, '']);
  }

  function removeExtraPatch(k) {
    const patchesCopy = rows.slice();
    patchesCopy.splice(k, 1);
    setPatches(patchesCopy);
  }

  return (
    <div className="flex w-full flex-col items-start gap-3">
      {rows.map((patch, k) => (
        // NOTE: we should not use `k`, but if we use `patch`, the value is
        // constantly modified onChange as the array is rebuilt. This is a major
        // peformance problem as we're constantly rebuilding the entire component
        // whenever someone types.
        // eslint-disable-next-line react/no-array-index-key
        <div key={k} id={`${idPrefix}_${k}`} className="flex w-full flex-row gap-2.5">
          <label className="sr-only" htmlFor={`${idPrefix}_url_${k}`}>
            Project patch {k + 1}
          </label>
          <input
            id={`${idPrefix}_url_${k}`}
            type="url"
            value={patch}
            onChange={event => {
              const newPatches = [...rows];
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
            aria-label={`Remove patch ${k + 1}`}
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
  setPatches: PropTypes.func.isRequired,
  // Must be unique across the page. There is no default on purpose: a shared
  // fallback is exactly the collision this prop exists to prevent.
  idPrefix: PropTypes.string.isRequired
};
export default Patches;
