import { createRoot } from 'react-dom/client';

import Launcher from './components/Launcher/Launcher';
import InstanceProgress from './components/ProgressPage/InstanceProgress';
import SiteTemplatePage from './components/SiteTemplatePage';
import { LauncherProvider } from './context/launcher';

import './tailwind.pcss';

const launcherMount = document.getElementById('launcher_mount');
if (launcherMount) {
  const root = createRoot(launcherMount);
  root.render(
    <LauncherProvider>
      <Launcher />
    </LauncherProvider>,
  );
}

const progressMount = document.getElementById('progress_mount');
if (progressMount) {
  const root = createRoot(progressMount);
  root.render(<InstanceProgress />);
}

const siteTemplateMount = document.getElementById('site_template_mount');
if (siteTemplateMount) {
  const root = createRoot(siteTemplateMount);
  root.render(<SiteTemplatePage template={drupalSettings.siteTemplate} />);
}
