import React from 'react';
import { createRoot } from 'react-dom/client';
import { LauncherProvider } from './context/launcher';
import InstanceProgress from './components/ProgressPage/InstanceProgress';
import Launcher from './components/Launcher/Launcher';

const launcherMount = document.getElementById('launcher_mount');
if (launcherMount) {
  const root = createRoot(launcherMount);
  root.render(
    <LauncherProvider>
      <Launcher />
    </LauncherProvider>
  );
}

const progressMount = document.getElementById('progress_mount');
if (progressMount) {
  const root = createRoot(progressMount);
  root.render(<InstanceProgress />);
}
