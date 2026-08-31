import React, { useEffect, useState } from "react";
import { selectHero, selectPanel } from "../ui";

function versionWithoutCoreModifier(version) {
  if (version.indexOf(".x-") !== -1) {
    return version.substr(4);
  }
  return version;
}

function isEmptyList(list) {
  return (
    list.latest.length === 0 &&
    list.branches.length === 0 &&
    list.core.length === 0
  );
}

// A version fetch can legitimately return an empty list while a deep-linked
// project is still being imported; retry a few times before accepting it.
const EMPTY_RETRIES = 3;
const EMPTY_RETRY_DELAY = 1500;

// @todo this might be better coupled within the ProjectAutocomplete component?
function VersionSelector({
  selectedProject,
  selectedVersion,
  setSelectedVersion,
  appliedCoreConstraint,
  initialVersion,
  rootProjectVersion,
  compact
}) {
  const [versions, setVersions] = useState(null);
  const [attempt, setAttempt] = useState(0);

  // Side effect: when we have a project shortname, and no core constraints
  // AKA the root project, fetch the direct versions.
  useEffect(
    () => {
      if (selectedProject && !appliedCoreConstraint) {
        // A deep link fetches once for the query-string placeholder and again
        // once the project is imported; a stale, possibly empty response must
        // not clobber the fresh list, and the browser cache must not serve
        // the pre-import empty response back.
        let stale = false;
        let retryTimer = null;
        fetch(`/simplytest/project/${selectedProject.shortname}/versions`, { cache: "no-cache" })
          .then(res => res.json())
          .then(json => {
            if (stale) {
              return;
            }
            setVersions(json.list);
            if (isEmptyList(json.list) && attempt < EMPTY_RETRIES) {
              retryTimer = setTimeout(() => setAttempt(attempt + 1), EMPTY_RETRY_DELAY);
            }
          });
        return () => {
          stale = true;
          if (retryTimer) {
            clearTimeout(retryTimer);
          }
        };
      }
      return undefined;
    },
    [selectedProject, appliedCoreConstraint, attempt]
  );

  // Side effect: when we have a project shortname AND core constraints, we know
  // this is a dependent/additional project. If the root project version changes,
  // we want to update for new compatibility.
  useEffect(
    () => {
      if (selectedProject && appliedCoreConstraint) {
        let stale = false;
        fetch(
          `/simplytest/project/${
            selectedProject.shortname
          }/compatibility/${appliedCoreConstraint}`,
          { cache: "no-cache" }
        )
          .then(res => res.json())
          .then(json => {
            if (!stale) {
              setVersions(json.list);
            }
          });
        return () => {
          stale = true;
        };
      }
      return undefined;
    },
    [selectedProject, appliedCoreConstraint, rootProjectVersion]
  );

  useEffect(
    () => {
      if (!initialVersion && versions) {
        // Fall back through the groups: a dev-only project has no tagged
        // release, and without a selection the select displays its first
        // option while nothing is actually chosen.
        const first =
          versions.latest[0] ||
          versions.branches[0] ||
          versions.core.flatMap(core => core.versions)[0];
        if (first) {
          setSelectedVersion(first.version);
        }
      }
    },
    [versions, initialVersion]
  );
  if (selectedProject === null || versions === null) {
    return null;
  }
  return (
    <div className={compact ? "w-full lg:w-[180px]" : "w-full lg:w-[200px]"}>
      <label htmlFor="project_version" className={compact ? "sr-only" : "field-label mb-2 block"}>
        Version
      </label>
      <select
        id="project_version"
        className={compact ? selectPanel : selectHero}
        value={selectedVersion}
        onChange={e => {
          setSelectedVersion(e.target.value);
        }}
      >
        <optgroup label="Latest">
          {versions.latest.map(version => {
            return (
              <option value={version.version} key={version.version}>
                {versionWithoutCoreModifier(version.version)} ({version.core_compatibility})
              </option>
            );
          })}
        </optgroup>
        <optgroup label="Branches">
          {versions.branches.map(version => {
            return (
              <option value={version.version} key={version.version}>
                {version.version}
              </option>
            );
          })}
        </optgroup>
        {versions.core.map(core => {
          return (
            <optgroup label={core.label} key={core.label}>
              {core.versions.map(version => {
                return (
                  <option value={version.version} key={version.version}>
                    {version.version}
                  </option>
                );
              })}
            </optgroup>
          )
        })}
      </select>
    </div>
  );
}

export default VersionSelector;
