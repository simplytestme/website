import { useLauncher } from '../context/launcher';
import { selectPanel } from '../ui';

function DrupalCoreVersionSelector() {
  const {
    selectedProject,
    selectedVersion,
    drupalVersions,
    drupalVersion,
    setDrupalVersion,
  } = useLauncher();

  if (selectedProject.shortname === 'drupal') {
    return null;
  }

  return (
    <div className="flex flex-1 flex-col gap-[7px]">
      <label
        htmlFor="drupal_core_version"
        className="text-[13px] font-semibold text-st-slate"
      >
        Drupal core
      </label>
      <select
        id="drupal_core_version"
        className={selectPanel}
        disabled={!selectedVersion}
        value={drupalVersion}
        onChange={(e) => setDrupalVersion(e.target.value)}
      >
        {drupalVersions.map((release) => (
          <option value={release} key={release}>
            {release}
          </option>
        ))}
      </select>
    </div>
  );
}

export default DrupalCoreVersionSelector;
