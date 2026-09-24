import { useState } from 'react';

import launch from '../launch';
import { btnPrimary, dangerBlock } from '../ui';
import { CMS_MAJOR } from './SiteTemplates';
import Spinner from './Spinner';

function Screenshot({ template }) {
  // Screenshots are hosted by whoever made the template, so fall back to the
  // same striped block the picker uses when one is missing or broken.
  const [failed, setFailed] = useState(false);
  if (!template.screenshot || failed) {
    return (
      <div className="flex aspect-[4/3] items-center justify-center bg-[repeating-linear-gradient(135deg,#e4edf5_0_8px,#f2f7fb_8px_16px)]">
        <span className="font-mono text-xs tracking-[0.08em] text-st-faint">
          {template.name.toLowerCase()}
        </span>
      </div>
    );
  }
  return (
    <img
      src={template.screenshot}
      alt={`Screenshot of the ${template.name} site template`}
      className="aspect-[4/3] w-full object-cover object-top"
      onError={() => setFailed(true)}
    />
  );
}

/**
 * The page a site template's permalink opens.
 *
 * Other sites link here to offer a demo of one template. Nothing launches
 * until the visitor presses the button: a GET that started a build would let
 * every link preview and crawler start one too.
 */
function SiteTemplatePage({ template }) {
  const [processing, setProcessing] = useState('');
  const [errors, setErrors] = useState([]);

  return (
    <div className="flex flex-col gap-6 px-6 pb-20 pt-8 lg:px-16">
      {errors.length > 0 && (
        <div className={dangerBlock} role="alert">
          {errors.map((error) => (
            <p className="m-0 py-1" key={error}>
              {error}
            </p>
          ))}
        </div>
      )}

      <div className="grid grid-cols-1 items-start gap-10 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <div className="overflow-hidden rounded-2xl border border-st-line2 bg-white shadow-card">
          <Screenshot template={template} />
        </div>

        <div className="flex flex-col gap-4">
          <span className="eyebrow text-st-soft">
            Drupal CMS &middot; Site template
          </span>
          <h1 className="m-0 text-4xl font-bold tracking-[-0.03em] text-st-body">
            {template.name}
          </h1>
          <p className="m-0 text-base leading-[1.6] text-st-muted">
            {template.description}
          </p>
          <span className="font-mono text-xs text-st-faint">
            by {template.creator || 'the community'}
          </span>

          <button
            type="button"
            disabled={processing !== ''}
            className={`${btnPrimary} mt-2 flex items-center justify-center gap-2 self-start`}
            onClick={() =>
              launch(
                { id: template.id, title: template.name },
                setProcessing,
                setErrors,
              )
            }
          >
            <span>
              {processing === '' ? `Launch ${template.name}` : 'Launching…'}
            </span>
            {processing !== '' ? <Spinner /> : null}
          </button>
          <p className="m-0 text-[13px] text-st-soft">
            Installs Drupal CMS {CMS_MAJOR} with this template in about a
            minute. The sandbox expires after 2 hours.
          </p>

          <div className="mt-2 flex flex-wrap gap-x-6 gap-y-2 border-t border-st-line pt-4 text-[13px] font-semibold">
            {template.links.map((link) => (
              <a
                key={link.url}
                href={link.url}
                target="_blank"
                rel="noreferrer noopener"
                className="text-st-accent hover:text-st-accent-dark"
              >
                {link.text}
              </a>
            ))}
            <a href="/" className="text-st-accent hover:text-st-accent-dark">
              Browse all templates
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}

export default SiteTemplatePage;
