import CopyButton from './CopyButton';

function BuildLog({ logs, open, onToggle }) {
  const text = logs
    .map((log) => (log.message || '').replace(/^\s+|\s+$/g, ''))
    .join('\n');
  return (
    <div className="overflow-hidden rounded-xl border border-st-line">
      <div
        className={`flex items-center justify-between bg-st-surface px-[18px] py-3.5 ${
          open ? 'border-b border-st-line' : ''
        }`}
      >
        <span className="flex items-center gap-2.5">
          <span className="font-mono text-[11px] uppercase tracking-[0.12em] text-st-faint">
            Build log
          </span>
          <span className="font-mono text-[11px] text-st-faint">
            {logs.length} lines
          </span>
        </span>
        <span className="flex gap-4 text-[13px] font-semibold text-st-accent-dark">
          {open && (
            <CopyButton className="hover:text-st-accent" text={text}>
              Copy
            </CopyButton>
          )}
          <button
            type="button"
            className="hover:text-st-accent"
            onClick={onToggle}
          >
            {open ? 'Hide ▴' : 'Show ▾'}
          </button>
        </span>
      </div>
      {open && (
        <pre className="m-0 h-[260px] overflow-auto bg-st-body px-5 py-[18px] font-mono text-xs leading-[1.7] text-st-button-line">
          {logs.map((log) => (
            <code className="block" key={log.id ?? log.message}>
              {(log.message || '').replace(/^\s+|\s+$/g, '')}
            </code>
          ))}
        </pre>
      )}
    </div>
  );
}

export default BuildLog;
