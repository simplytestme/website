import { useState } from 'react';

import launch from '../launch';
import { btnPrimary, dangerBlock } from '../ui';
import Spinner from './Spinner';

/**
 * The landing page a demo's link opens, such as /demo/agent-access.
 *
 * Other sites link here to offer one demo. Nothing launches until the visitor
 * presses the button: a GET that started a build would let every link preview
 * and crawler start one too.
 */
function DemoPage({ demo }) {
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
          <div className="flex aspect-[4/3] items-center justify-center bg-[repeating-linear-gradient(135deg,#e4edf5_0_8px,#f2f7fb_8px_16px)]">
            <span className="font-mono text-xs tracking-[0.08em] text-st-faint">
              {demo.title.toLowerCase()}
            </span>
          </div>
        </div>

        <div className="flex flex-col gap-4">
          <span className="eyebrow text-st-soft">One click demo</span>
          <h1 className="m-0 text-4xl font-bold tracking-[-0.03em] text-st-body">
            {demo.title}
          </h1>
          <p className="m-0 text-base leading-[1.6] text-st-muted">
            {demo.description}
          </p>

          <button
            type="button"
            disabled={processing !== ''}
            className={`${btnPrimary} mt-2 flex items-center justify-center gap-2 self-start`}
            onClick={() => launch(demo, setProcessing, setErrors)}
          >
            <span>
              {processing === '' ? `Launch ${demo.title}` : 'Launching…'}
            </span>
            {processing !== '' ? <Spinner /> : null}
          </button>
          <p className="m-0 text-[13px] text-st-soft">
            Launches fully installed with demo content. The sandbox expires
            after 2 hours.
          </p>

          <div className="mt-2 flex flex-wrap gap-x-6 gap-y-2 border-t border-st-line pt-4 text-[13px] font-semibold">
            <a href="/" className="text-st-accent hover:text-st-accent-dark">
              Browse all demos
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}

export default DemoPage;
