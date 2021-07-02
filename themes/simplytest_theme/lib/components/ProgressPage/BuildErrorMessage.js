import React from "react";
import PropTypes from "prop-types";

function BuildErrorMessage({ logs }) {
  const lastLogs = logs.slice(-3, -1);
  return (
    <div className="bg-red-600 text-red-100 p-4 overflow-scroll">
      <p className="font-bold mb-4">This may be the error:</p>
      <pre className="text-sm">
        {lastLogs.map(log => (
          <code className="block">{log.message}</code>
        ))}
      </pre>
    </div>
  );
}
BuildErrorMessage.propTypes = {
  logs: PropTypes.arrayOf(
    PropTypes.shape({
      message: PropTypes.string.isRequired
    })
  )
};
BuildErrorMessage.defaultProps = {
  logs: []
};
export default BuildErrorMessage;
