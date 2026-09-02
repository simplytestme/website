import React, { useEffect, useMemo, useState } from "react";
import BuildLog from "./BuildLog";
import CopyButton from "./CopyButton";
import { btnPrimary, btnSecondary, btnSecondarySm } from "../../ui";

// How long to wait between polls. The backend caches computed state for the
// same window, so polling faster than this only returns cached answers.
const POLL_INTERVAL = 3000;
// Give up after this many consecutive failed status requests.
const MAX_FAILURES = 5;
// Sandboxes are deleted this long after they were created.
const SANDBOX_LIFETIME_MS = 2 * 60 * 60 * 1000;

// Stage markers echoed into the build log by the preview config.
// @see \Drupal\simplytest_tugboat\PreviewConfigGenerator
const STAGE_MARKERS = [
  "SIMPLYEST_STAGE_DOWNLOAD",
  "SIMPLYEST_STAGE_PATCHING",
  "SIMPLYEST_STAGE_INSTALLING",
  "SIMPLYEST_STAGE_FINALIZE",
  "SIMPLYEST_STAGE_FINISHED"
];

// How many lines of the log to put in front of someone whose build failed.
const EXCERPT_LINES = 3;

// After a fatal error Composer prints the synopsis of the command that failed
// ("update [--with WITH] [--prefer-source] ..."). It is the last thing in the
// log and says nothing about what went wrong, which is why the tail of the log
// is the worst possible excerpt to show.
const USAGE_SYNOPSIS = /^\s*[a-z][\w:-]*\s+\[[-<]/i;
// Composer frames fatal errors as "In Patches.php line 288:", followed by the
// exception class and the message.
const EXCEPTION_HEADER = /^In .+ line \d+:$/;
// git apply and patch say why a patch was rejected. They run before Composer
// throws, so they sit above the exception rather than at the end of the log.
const PATCH_DETAIL = /^(error: |Hunk #|\d+ out of \d+ hunk)/;
// composer-patches gives up with this once every patcher has refused.
const PATCH_FAILURE = /No available patcher was able to apply patch/;

// Flatten the log into lines, dropping blanks and Composer's usage synopsis.
function logLines(logs) {
  return logs
    .flatMap(log => (log.message || "").split("\n"))
    .map(line => line.trimEnd())
    .filter(line => line !== "" && !USAGE_SYNOPSIS.test(line));
}

// The lines that actually say what went wrong, rather than the last ones.
function failureExcerpt(logs) {
  const lines = logLines(logs);

  let header = -1;
  for (let i = lines.length - 1; i >= 0; i -= 1) {
    if (EXCEPTION_HEADER.test(lines[i])) {
      header = i;
      break;
    }
  }
  if (header === -1) {
    return lines.slice(-EXCERPT_LINES);
  }

  const detail = lines
    .slice(0, header)
    .filter(line => PATCH_DETAIL.test(line))
    .slice(-EXCERPT_LINES);
  return [...detail, ...lines.slice(header, header + 1 + EXCERPT_LINES)];
}

// Index of the last stage whose marker appears in the log, or -1.
function stageIndexFromLogs(logs) {
  let index = -1;
  logs.forEach(log => {
    const message = log.message || "";
    STAGE_MARKERS.forEach((marker, i) => {
      if (message.startsWith(marker) && i > index) {
        index = i;
      }
    });
    // Previews built before the FINISHED marker existed only log this line.
    if (message.includes("(simplytest) is ready")) {
      index = STAGE_MARKERS.length - 1;
    }
  });
  return index;
}

// The launch page forwards the submission in the query string so this page
// can label the build and offer prefilled retry links.
function readSubmission() {
  const params = new URLSearchParams(window.location.search);
  return {
    project: params.get("project"),
    version: params.get("version"),
    title: params.get("title"),
    core: params.get("core"),
    profile: params.get("profile"),
    demo: params.get("demo"),
    patches: params.getAll("patch")
  };
}

function summaryLine(submission) {
  const parts = [
    submission.project,
    submission.version,
    submission.core ? `core ${submission.core}` : null,
    submission.profile
  ].filter(Boolean);
  if (parts.length === 0 && submission.title) {
    return submission.title.toLowerCase();
  }
  return parts.join(" · ").toLowerCase();
}

function prefillUrl(submission, { includePatches = true } = {}) {
  const params = new URLSearchParams();
  params.set("project", submission.project);
  if (submission.version) {
    params.set("version", submission.version);
  }
  if (includePatches) {
    submission.patches.forEach(patch => params.append("patch", patch));
  }
  return `/?${params.toString()}`;
}

function formatDuration(from, to) {
  const start = new Date(from).getTime();
  const end = new Date(to).getTime();
  if (Number.isNaN(start) || Number.isNaN(end) || end <= start) {
    return null;
  }
  const seconds = Math.round((end - start) / 1000);
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`;
}

function formatExpiry(createdAt) {
  const created = new Date(createdAt).getTime();
  if (Number.isNaN(created)) {
    return null;
  }
  const expires = new Date(created + SANDBOX_LIFETIME_MS);
  const time = expires.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
  const prefix = expires.toDateString() === new Date().toDateString() ? "Today at" : "At";
  return `${prefix} ${time} — two hours from launch. Anything you build here is deleted then.`;
}

function buildSteps(submission, stageIndex, jobStarted) {
  const projectLabel = [submission.title || submission.project, submission.version]
    .filter(Boolean)
    .join(" ");
  const patchCount = submission.patches.length;
  const labels = [
    "Queued and assigned a server",
    submission.core ? `Downloading Drupal core ${submission.core}` : "Downloading Drupal core",
    (projectLabel ? `Installing ${projectLabel}` : "Installing your project") +
      (patchCount > 0 ? ` and applying ${patchCount} ${patchCount === 1 ? "patch" : "patches"}` : ""),
    submission.profile
      ? `Running the ${submission.profile} installer`
      : "Running the installer",
    "Starting your sandbox"
  ];
  // Marker N appears when stage N starts: earlier steps are done, N is active.
  // Step 0 has no marker; it is done once the job produces any log output.
  return labels.map((label, i) => {
    const stepStage = i - 1;
    let status = "pending";
    if (stageIndex > stepStage || (i === 0 && jobStarted)) {
      status = "done";
    }
    if (stageIndex === stepStage || (i === 0 && !jobStarted)) {
      status = "current";
    }
    return { label, status };
  });
}

function StepRow({ step }) {
  if (step.status === "done") {
    return (
      <div className="flex items-center gap-3 px-3.5 py-3">
        <span className="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-st-accent text-[11px] text-white">
          ✓
        </span>
        <span className="flex-1 text-sm text-st-muted">{step.label}</span>
      </div>
    );
  }
  if (step.status === "current") {
    return (
      <div className="flex items-center gap-3 rounded-lg border border-st-accent-line bg-white px-3.5 py-3">
        <span className="h-5 w-5 flex-none animate-spin rounded-full border-2 border-st-accent-line border-t-st-accent" />
        <span className="flex-1 text-sm font-semibold text-st-body">{step.label}</span>
        <span className="font-mono text-[11px] text-st-accent-dark">running</span>
      </div>
    );
  }
  return (
    <div className="flex items-center gap-3 px-3.5 py-3">
      <span className="h-5 w-5 flex-none rounded-full border border-st-field-line" />
      <span className="flex-1 text-sm text-st-faint">{step.label}</span>
    </div>
  );
}

function PageColumn({ children }) {
  return (
    <div className="flex justify-center px-6 pb-24 pt-14 lg:px-16 lg:pt-[72px]">
      <div className="flex w-full max-w-[780px] flex-col gap-7">{children}</div>
    </div>
  );
}

function PageHeading({ eyebrow, eyebrowClass, title, children }) {
  return (
    <div className="flex flex-col gap-3.5">
      <span className={`eyebrow ${eyebrowClass}`}>{eyebrow}</span>
      <h1 className="m-0 text-3xl font-extrabold leading-[1.1] tracking-[-0.03em] text-st-ink lg:text-[42px]">
        {title}
      </h1>
      <p className="m-0 text-base leading-relaxed text-st-sub">{children}</p>
    </div>
  );
}

function SummaryFooter({ submission, children }) {
  const line = summaryLine(submission);
  return (
    <div className="flex items-center gap-5">
      {line && <span className="font-mono text-[11px] text-st-faint">{line}</span>}
      {children}
    </div>
  );
}

function InstanceProgress() {
  const [error, setError] = useState(false);
  const [logOpen, setLogOpen] = useState(false);
  const [state, setState] = useState({
    progress: 0,
    url: null,
    logs: []
  });
  const submission = useMemo(readSubmission, []);

  useEffect(() => {
    const { stateUrl } = drupalSettings;
    let timeoutId = null;
    let stopped = false;
    let failures = 0;

    const schedule = delay => {
      if (!stopped) {
        timeoutId = setTimeout(poll, delay);
      }
    };

    // One request per tick, and the next tick is only scheduled once this one
    // finishes, so slow responses stretch the interval instead of overlapping.
    const poll = async () => {
      let json;
      try {
        const res = await fetch(stateUrl);
        if (res.status === 404) {
          setState(await res.json());
          setError(true);
          return;
        }
        if (!res.ok) {
          throw new Error(`Status request failed with ${res.status}`);
        }
        json = await res.json();
      } catch (e) {
        failures += 1;
        if (failures >= MAX_FAILURES) {
          setState(prev => ({
            ...prev,
            message:
              "We can't check on your sandbox right now. Reload the page to try again."
          }));
          setError(true);
          return;
        }
        // Back off so a struggling backend gets 6s, 12s, 24s, 48s of air.
        schedule(POLL_INTERVAL * 2 ** failures);
        return;
      }

      failures = 0;
      setState(json);
      // A preview means the job finished; a failed job never becomes one.
      // Either way the state is final and polling must stop.
      if (json.type === "preview") {
        if (json.url && json.state === "ready") {
          setTimeout(() => {
            window.location.href = json.url;
          }, 3000);
        }
        return;
      }
      if (json.state === "failed") {
        return;
      }
      schedule(POLL_INTERVAL);
    };

    poll();
    return () => {
      stopped = true;
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
    };
  }, []);

  const failed = state.state === "failed";
  const ready = state.type === "preview" && state.state === "ready" && state.url;

  // A failed build lands with the log open.
  useEffect(
    () => {
      if (failed) {
        setLogOpen(true);
      }
    },
    [failed]
  );

  if (error) {
    return (
      <PageColumn>
        <PageHeading eyebrow="Sandbox unavailable" eyebrowClass="text-st-danger" title="We lost track of this one">
          {state.message}
        </PageHeading>
        <div>
          <a href="/" className={btnSecondary}>
            Go back and try again
          </a>
        </div>
      </PageColumn>
    );
  }

  const logs = state.logs || [];

  if (ready) {
    const duration = formatDuration(state.createdAt, state.updatedAt);
    const expiry = formatExpiry(state.createdAt);
    return (
      <PageColumn>
        <PageHeading
          eyebrow={duration ? `Ready in ${duration}` : "Ready"}
          eyebrowClass="text-st-success"
          title="Your sandbox is ready"
        >
          Opening it in a moment. You&rsquo;re signed in as an administrator.
        </PageHeading>

        <div className="flex flex-col gap-4 rounded-[14px] border border-st-accent-line bg-st-accent-tint2 p-6">
          <div className="flex flex-col items-stretch gap-3.5 sm:flex-row sm:items-center">
            <span className="min-w-0 flex-1 overflow-hidden text-ellipsis whitespace-nowrap rounded-lg border border-st-field-line bg-white px-4 py-3 font-mono text-[13px] text-st-body">
              {state.url}
            </span>
            <CopyButton className={btnSecondary} text={state.url}>
              Copy link
            </CopyButton>
            <a href={state.url} className={`${btnPrimary} whitespace-nowrap px-[22px] py-3.5 text-center text-[15px]`}>
              Open sandbox
            </a>
          </div>
          {expiry && (
            <div className="flex items-center gap-2.5 border-t border-st-accent-divider pt-4">
              <span className="font-mono text-[11px] uppercase tracking-[0.1em] text-st-accent-dark">Expires</span>
              <span className="text-sm text-st-slate">{expiry}</span>
            </div>
          )}
        </div>

        {submission.project && (
          <div className="flex items-center justify-between gap-6 rounded-xl border border-st-line px-5 py-[18px]">
            <div className="flex flex-col gap-1">
              <span className="text-sm font-semibold text-st-body">Want to keep this one?</span>
              <span className="text-[13px] text-st-soft">
                Launch the same setup again any time with a shareable link.
              </span>
            </div>
            <CopyButton
              className={`${btnSecondarySm} whitespace-nowrap`}
              text={`${window.location.origin}${prefillUrl(submission)}`}
            >
              Copy launch link
            </CopyButton>
          </div>
        )}

        <SummaryFooter submission={submission}>
          <button
            type="button"
            className="text-[13px] font-semibold text-st-accent hover:text-st-accent-dark"
            onClick={() => setLogOpen(!logOpen)}
          >
            {logOpen ? "Hide build log" : "View build log"}
          </button>
        </SummaryFooter>
        {logOpen && <BuildLog logs={logs} open onToggle={() => setLogOpen(false)} />}
      </PageColumn>
    );
  }

  if (failed) {
    const excerpt = failureExcerpt(logs);
    // Only blame the patch when the log says the patch is what failed. A build
    // can carry patches and still fall over somewhere else entirely.
    const patchFailed = logLines(logs).some(line => PATCH_FAILURE.test(line));
    return (
      <PageColumn>
        <PageHeading
          eyebrow={`Build failed at ${state.progress}%`}
          eyebrowClass="text-st-danger"
          title="This one didn't build"
        >
          {patchFailed
            ? "The patch couldn't be applied. That usually means it was written against a different version of the project."
            : "Something in this build didn't come together. The log below shows where it stopped."}
        </PageHeading>

        {excerpt.length > 0 && (
          <div className="overflow-hidden rounded-[14px] border border-st-danger-line bg-st-danger-bg">
            <div className="border-b border-st-danger-line px-5 py-4">
              <span className="font-mono text-[11px] uppercase tracking-[0.12em] text-st-danger">
                Where it stopped
              </span>
            </div>
            <pre className="m-0 overflow-auto whitespace-pre-wrap px-5 py-[18px] font-mono text-[12.5px] leading-[1.75] text-st-danger-text">
              {excerpt.map((line, i) => (
                // eslint-disable-next-line react/no-array-index-key
                <code className="block" key={`${i}-${line}`}>
                  {line}
                </code>
              ))}
            </pre>
          </div>
        )}

        <div className="flex flex-wrap items-center gap-3">
          {submission.project ? (
            <>
              <a href={prefillUrl(submission)} className={`${btnPrimary} px-[22px] py-3.5 text-[15px]`}>
                Edit and try again
              </a>
              {submission.patches.length > 0 && (
                <a
                  href={prefillUrl(submission, { includePatches: false })}
                  className={`${btnSecondary} px-5 py-3.5 text-[15px]`}
                >
                  Launch without the patch
                </a>
              )}
            </>
          ) : (
            <a href="/" className={`${btnPrimary} px-[22px] py-3.5 text-[15px]`}>
              Start over
            </a>
          )}
        </div>

        <BuildLog logs={logs} open={logOpen} onToggle={() => setLogOpen(!logOpen)} />

        <SummaryFooter submission={submission}>
          <a
            href="https://www.drupal.org/project/issues/simplytest?categories=All"
            className="text-[13px] font-semibold text-st-accent hover:text-st-accent-dark"
          >
            Report this to the maintainers
          </a>
        </SummaryFooter>
      </PageColumn>
    );
  }

  // Still building.
  const stageIndex = stageIndexFromLogs(logs);
  const steps = buildSteps(submission, stageIndex, logs.length > 0);
  const currentStep = steps.find(step => step.status === "current");
  const name = submission.title || submission.project;
  return (
    <PageColumn>
      <PageHeading
        eyebrow="Building · about a minute"
        eyebrowClass="text-st-accent-dark"
        title={name ? `Setting up your ${name} sandbox` : "Setting up your sandbox"}
      >
        Keep this tab open. We&rsquo;ll send you straight there when it&rsquo;s ready.
      </PageHeading>

      <div className="flex flex-col gap-2.5">
        <div className="h-2.5 overflow-hidden rounded-full bg-st-line">
          <div
            className="h-full rounded-full bg-gradient-to-r from-st-accent to-st-accent-bright transition-[width] duration-300 ease-out"
            style={{ width: `${state.progress}%` }}
          />
        </div>
        <div className="flex items-center justify-between">
          <span className="text-sm font-semibold text-st-slate">
            {currentStep ? currentStep.label : "Working…"}
          </span>
          <span className="font-mono text-[13px] text-st-accent-dark">{state.progress}%</span>
        </div>
      </div>

      <div className="flex flex-col gap-0.5 rounded-xl border border-st-line bg-st-surface p-2">
        {steps.map(step => (
          <StepRow key={step.label} step={step} />
        ))}
      </div>

      <BuildLog logs={logs} open={logOpen} onToggle={() => setLogOpen(!logOpen)} />

      <SummaryFooter submission={submission}>
        <a href="/" className="text-[13px] font-semibold text-st-accent hover:text-st-accent-dark">
          Start over
        </a>
      </SummaryFooter>
    </PageColumn>
  );
}

export default InstanceProgress;
