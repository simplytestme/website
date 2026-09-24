import { useEffect, useRef, useState } from 'react';

import launch from '../launch';
import { btnPrimarySm, btnSecondarySm } from '../ui';
import { fetchWithCallback } from '../utils';
import Spinner from './Spinner';

// Every template is a Drupal CMS install, so the picker says so once in the
// footer rather than on each card.
export const CMS_MAJOR = '2.x';

/**
 * The striped block a card shows when it has no screenshot, or it fails.
 */
function CardPlaceholder({ name }) {
  return (
    <div className="flex h-32 items-center justify-center bg-[repeating-linear-gradient(135deg,#e4edf5_0_8px,#f2f7fb_8px_16px)]">
      <span className="font-mono text-[10px] tracking-[0.08em] text-st-faint">
        {name.toLowerCase()}
      </span>
    </div>
  );
}

function CardPreview({ template }) {
  // Screenshots are hosted by whoever made the template, so a broken one is a
  // question of when, not if.
  const [failed, setFailed] = useState(false);
  if (!template.screenshot || failed) {
    return <CardPlaceholder name={template.name} />;
  }
  return (
    <img
      src={template.screenshot}
      alt=""
      loading="lazy"
      className="h-32 w-full object-cover object-top"
      onError={() => setFailed(true)}
    />
  );
}

function TemplateCard({ template, primary, processing, onLaunch }) {
  return (
    <div className="flex flex-col overflow-hidden rounded-xl border border-st-line2 bg-white hover:border-st-accent">
      <CardPreview template={template} />
      <div className="flex flex-1 flex-col gap-2 border-t border-st-line2 p-4">
        <h3 className="m-0 text-base font-bold tracking-[-0.015em] text-st-body">
          {template.name}
        </h3>
        <p className="m-0 flex-1 text-[13px] leading-[1.5] text-st-muted">
          {template.description}
        </p>
        <div className="mt-1 flex items-center justify-between gap-3">
          <span className="truncate font-mono text-[11px] text-st-faint">
            {template.creator || 'community'}
          </span>
          <button
            type="button"
            disabled={processing !== ''}
            className={`${primary ? btnPrimarySm : btnSecondarySm} flex flex-none items-center justify-center gap-2`}
            onClick={(event) => {
              event.preventDefault();
              onLaunch(template);
            }}
          >
            <span>Launch</span>
            {processing === template.id ? <Spinner /> : null}
          </button>
        </div>
      </div>
    </div>
  );
}

/**
 * Keeps tab focus inside the dialog while it is open.
 */
function useFocusTrap(open, dialogRef, onClose) {
  useEffect(() => {
    if (!open) {
      return undefined;
    }
    const previous = document.activeElement;
    // The page behind must not scroll while the grid inside does.
    const { overflow } = document.body.style;
    document.body.style.overflow = 'hidden';
    dialogRef.current?.focus();

    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        onClose();
        return;
      }
      if (event.key !== 'Tab') {
        return;
      }
      const focusable = dialogRef.current?.querySelectorAll(
        'a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])',
      );
      if (!focusable || focusable.length === 0) {
        return;
      }
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = overflow;
      if (previous instanceof HTMLElement) {
        previous.focus();
      }
    };
  }, [open, dialogRef, onClose]);
}

function TemplatePicker({ templates, onClose, setErrors }) {
  const dialogRef = useRef(null);
  const [processing, setProcessing] = useState('');
  const [filter, setFilter] = useState('');
  useFocusTrap(true, dialogRef, onClose);

  const needle = filter.trim().toLowerCase();
  const shown = needle
    ? templates.filter((template) =>
        `${template.name} ${template.description} ${template.creator || ''}`
          .toLowerCase()
          .includes(needle),
      )
    : templates;

  return (
    <div
      className="fixed inset-0 z-50 overflow-y-auto bg-[rgba(7,23,38,0.55)] px-6 py-6 sm:py-24"
      role="presentation"
      onClick={(event) => {
        if (event.target === event.currentTarget) {
          onClose();
        }
      }}
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="site-template-picker-title"
        tabIndex={-1}
        className="mx-auto flex w-full max-w-[1000px] flex-col rounded-2xl bg-white shadow-xl"
      >
        <div className="flex items-start justify-between gap-6 border-b border-st-line p-6">
          <div className="flex flex-col gap-2">
            <span className="eyebrow text-st-soft">
              Drupal CMS &middot; Site templates
            </span>
            <h2
              id="site-template-picker-title"
              className="m-0 text-2xl font-bold tracking-[-0.025em] text-st-body"
            >
              Pick a starting point
            </h2>
            <p className="m-0 max-w-[560px] text-sm text-st-muted">
              Each template installs Drupal CMS with the recipes, content types
              and demo content for that kind of site.
            </p>
          </div>
          <button
            type="button"
            aria-label="Close"
            onClick={onClose}
            className="h-9 w-9 flex-none rounded-full border border-st-line bg-white text-lg leading-none text-st-soft hover:border-st-accent hover:text-st-accent"
          >
            &times;
          </button>
        </div>

        <div className="border-b border-st-line px-6 py-4">
          <label htmlFor="site-template-filter" className="field-label">
            Filter
          </label>
          <input
            id="site-template-filter"
            type="search"
            value={filter}
            autoComplete="off"
            placeholder="Search templates"
            onChange={(event) => setFilter(event.target.value)}
            className="mt-2 w-full rounded-lg border border-st-field-line bg-st-field px-3.5 py-2.5 text-[15px] text-st-body"
          />
        </div>

        <div className="max-h-[520px] overflow-y-auto bg-st-surface p-6">
          {shown.length === 0 ? (
            <p className="m-0 py-8 text-center text-sm text-st-soft">
              No templates match &ldquo;{filter.trim()}&rdquo;.
            </p>
          ) : (
            <div className="grid grid-cols-1 gap-[18px] md:grid-cols-2 lg:grid-cols-3">
              {shown.map((template, index) => (
                <TemplateCard
                  key={template.id}
                  template={template}
                  primary={index === 0}
                  processing={processing}
                  onLaunch={(item) =>
                    launch(
                      { id: item.id, title: item.name },
                      setProcessing,
                      setErrors,
                    )
                  }
                />
              ))}
            </div>
          )}
        </div>

        <div className="flex flex-col gap-2 border-t border-st-line px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
          <span className="font-mono text-[11px] lowercase text-st-faint">
            every template runs on drupal cms {CMS_MAJOR} &middot; sandbox
            expires after 2 hours
          </span>
          <a
            href="https://new.drupal.org/drupal-cms/site-templates"
            target="_blank"
            rel="noreferrer noopener"
            className="text-[13px] font-semibold text-st-accent hover:text-st-accent-dark"
          >
            What is a site template?
          </a>
        </div>
      </div>
    </div>
  );
}

/**
 * The home page tile, and the picker it opens.
 */
function SiteTemplates({ setErrors }) {
  const [templates, setTemplates] = useState([]);
  const [open, setOpen] = useState(false);
  useEffect(() => {
    fetchWithCallback('/site-templates', setTemplates);
  }, []);

  const count = templates.length;
  return (
    <>
      <div className="flex flex-col overflow-hidden rounded-[14px] border border-dashed border-st-dash bg-st-field">
        <div className="grid h-[108px] flex-none grid-cols-2 grid-rows-2 gap-1 border-b border-st-line2 p-3">
          {templates
            .slice(0, 4)
            .map((template) =>
              template.screenshot ? (
                <img
                  key={template.id}
                  src={template.screenshot}
                  alt=""
                  loading="lazy"
                  className="h-full w-full rounded-[3px] object-cover object-top"
                />
              ) : (
                <div
                  key={template.id}
                  className="rounded-[3px] bg-[repeating-linear-gradient(135deg,#e4edf5_0_6px,#f2f7fb_6px_12px)]"
                />
              ),
            )}
          {/* Keeps the 2x2 grid whole while the list is loading or short. */}
          {Array.from({ length: Math.max(0, 4 - count) }).map((_, i) => (
            <div
              key={`placeholder-${i}`}
              className="rounded-[3px] bg-[repeating-linear-gradient(135deg,#e4edf5_0_6px,#f2f7fb_6px_12px)]"
            />
          ))}
        </div>
        <div className="flex flex-1 flex-col gap-2.5 p-5">
          <h3 className="m-0 text-lg font-bold tracking-[-0.015em] text-st-body">
            Site templates
          </h3>
          <p className="m-0 flex-1 text-[13.5px] leading-[1.55] text-st-muted">
            Prebuilt starting points for common site types. Pick one and launch
            it.
          </p>
          <span className="font-mono text-[11px] text-st-faint">
            {count === 0
              ? 'loading templates'
              : `${count} template${count === 1 ? '' : 's'}`}
          </span>
          <button
            type="button"
            disabled={count === 0}
            className={`${btnSecondarySm} mt-1.5`}
            onClick={() => setOpen(true)}
          >
            Browse templates
          </button>
        </div>
      </div>
      {open && (
        <TemplatePicker
          templates={templates}
          setErrors={setErrors}
          onClose={() => setOpen(false)}
        />
      )}
    </>
  );
}

export default SiteTemplates;
