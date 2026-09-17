import { useEffect, useState } from 'react';

import launch from '../launch';
import { btnPrimarySm, btnSecondarySm } from '../ui';
import { fetchWithCallback } from '../utils';
import SiteTemplates from './SiteTemplates';
import Spinner from './Spinner';

function TilePreview({ caption, accent }) {
  return (
    <div
      className={
        accent
          ? 'flex h-[108px] items-center justify-center border-b border-[#dcebf7] bg-[repeating-linear-gradient(135deg,#eaf4fc_0_8px,#f6fbff_8px_16px)]'
          : 'flex h-[108px] items-center justify-center border-b border-st-line bg-[repeating-linear-gradient(135deg,#eef2f6_0_8px,#f8fafc_8px_16px)]'
      }
    >
      <span className="font-mono text-[10px] tracking-[0.08em] text-st-faint">
        {caption}
      </span>
    </div>
  );
}

function DemoTile({ demo, processing, setProcessing, setErrors }) {
  const recommended = Boolean(demo.recommended);
  return (
    <div
      className={
        recommended
          ? 'flex flex-col overflow-hidden rounded-[14px] border border-st-accent bg-white shadow-tile'
          : 'flex flex-col overflow-hidden rounded-[14px] border border-st-line2 bg-white'
      }
    >
      <TilePreview
        caption={`screenshot: ${demo.title.toLowerCase()}`}
        accent={recommended}
      />
      <div className="flex flex-1 flex-col gap-2.5 p-5">
        {recommended && (
          <span className="self-start rounded bg-st-accent px-2 py-1 font-mono text-[10px] uppercase tracking-[0.12em] text-white">
            Recommended
          </span>
        )}
        <h3 className="m-0 text-lg font-bold tracking-[-0.015em] text-st-body">
          {demo.title}
        </h3>
        <p className="m-0 flex-1 text-[13.5px] leading-[1.55] text-st-muted">
          {demo.description}
        </p>
        <button
          type="button"
          disabled={processing !== ''}
          className={`${recommended ? btnPrimarySm : btnSecondarySm} mt-1.5 flex items-center justify-center gap-2`}
          onClick={(event) => {
            event.preventDefault();
            launch(demo, setProcessing, setErrors);
          }}
        >
          <span>Launch demo</span>
          {processing === demo.id ? <Spinner /> : null}
        </button>
      </div>
    </div>
  );
}

function OneClickDemos({ setErrors }) {
  const [demos, setDemos] = useState([]);
  const [processing, setProcessing] = useState('');
  useEffect(() => {
    fetchWithCallback('/one-click-demos', setDemos);
  }, []);

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-6 border-t border-st-line pt-6 sm:flex-row sm:items-end sm:justify-between">
        <div className="flex flex-col gap-2">
          <span className="eyebrow text-st-soft">One click</span>
          <h2 className="m-0 text-3xl font-bold tracking-[-0.025em] text-st-body">
            Start from a ready-made site
          </h2>
        </div>
        <p className="m-0 mb-1 max-w-[300px] text-sm text-st-soft sm:text-right">
          Nothing to configure. Each one launches fully installed with demo
          content.
        </p>
      </div>
      <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        {demos.map((demo) => (
          <DemoTile
            key={demo.id}
            demo={demo}
            processing={processing}
            setProcessing={setProcessing}
            setErrors={setErrors}
          />
        ))}
        {demos.length > 0 && <SiteTemplates setErrors={setErrors} />}
      </div>
    </section>
  );
}

export default OneClickDemos;
