import { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';

function CopyButton({ text, className, children }) {
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
CopyButton.propTypes = {
  text: PropTypes.string.isRequired,
  className: PropTypes.string,
  children: PropTypes.node.isRequired,
};
CopyButton.defaultProps = {
  className: null,
};

export default CopyButton;
