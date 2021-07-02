import React from "react";
import ReactDOM from "react-dom";
import { LauncherProvider } from "./context/launcher";
import InstanceProgress from "./components/ProgressPage/InstanceProgress";
import Launcher from "./components/Launcher/Launcher";

const launcherMount = document.getElementById("launcher_mount");
if (launcherMount) {
  ReactDOM.render(
    <LauncherProvider>
      <Launcher />
    </LauncherProvider>,
    launcherMount
  );
}

const progressMount = document.getElementById("progress_mount");
if (progressMount) {
  ReactDOM.render(<InstanceProgress />, progressMount);
}
