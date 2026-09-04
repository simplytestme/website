import { useEffect, useRef, useState } from 'react';

function CopyButton({ text, className = null, children }) {
  const [copied, setCopied] = useState(false);
  const timerRef = useRef(null);
  useEffect(() => () => clearTimeout(timerRef.current), []);

  function onClick() {
    if (!navigator.clipboard) {
      return;
    }
    navigator.clipboard.writeText(text).then(() => {
      setCopied(true);
      clearTimeout(timerRef.current);
      timerRef.current = setTimeout(() => setCopied(false), 2000);
    });
  }

  return (
    <button type="button" className={className} onClick={onClick}>
      {copied ? 'Copied!' : children}
    </button>
  );
}

export default CopyButton;
