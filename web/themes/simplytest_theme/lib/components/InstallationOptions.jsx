import { useLauncher } from '../context/launcher';
import { selectPanel } from '../ui';

export function ManualInstallCheckbox() {
  const { manualInstall, setManualInstall } = useLauncher();
  return (
    <label className="flex w-full cursor-pointer items-start gap-3 rounded-[10px] border border-st-line bg-white px-4 py-3.5">
      <input
        type="checkbox"
        className="mt-[3px] h-4 w-4 accent-st-accent"
        checked={manualInstall}
        onChange={(event) => setManualInstall(event.target.checked)}
      />
      <span className="flex flex-col gap-[3px]">
        <span className="text-sm font-semibold text-st-body">
          Run the installer myself
        </span>
        <span className="text-[13px] leading-normal text-st-soft">
          Land on Drupal&rsquo;s install screen instead of a finished site.
          Useful for testing install-time options.
        </span>
      </span>
    </label>
  );
}

// Umami shipped in core 8.6. Majors are listed rather than open-ended so a new
// major has to opt in once someone confirms it still ships the profile.
function shipsUmami(drupalVersion) {
  const [major, minor] = drupalVersion
    .split('.')
    .map((part) => parseInt(part, 10));
  if (major === 8) {
    return minor >= 6;
  }
  return major >= 9 && major <= 11;
}

export function SelectProfile() {
  const { selectedProject, drupalVersion, installProfile, setInstallProfile } =
    useLauncher();

  if (!selectedProject) {
    return null;
  }
  if (selectedProject.type === 'Distribution') {
    return null;
  }

  const isUmamiAllowed = shipsUmami(drupalVersion || '');

  return (
    <div className="flex flex-1 flex-col gap-[7px]">
      <label
        htmlFor="install_profile"
        className="text-[13px] font-semibold text-st-slate"
      >
        Install profile
      </label>
      <select
        id="install_profile"
        className={selectPanel}
        value={installProfile}
        onChange={(e) => setInstallProfile(e.target.value)}
        disabled={!selectedProject}
      >
        <option key="standard" value="standard">
          Standard
        </option>
        <option key="minimal" value="minimal">
          Minimal
        </option>
        {isUmamiAllowed
          ? [
              <option key="demo_umami" value="demo_umami">
                Umami Demo
              </option>,
            ]
          : null}
      </select>
    </div>
  );
}
