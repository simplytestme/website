import React from "react";
import PropTypes from "prop-types";

function BuildSuccessMessage({ url }) {
  return (
    <div className="bg-green-600 text-green-100 p-4">
      <p className="font-bold mb-4">
        You will be redirected to the sandbox shortly
      </p>
      <pre className="text-sm">
        <code>{url}</code>
      </pre>
    </div>
  );
}
BuildSuccessMessage.propTypes = {
  url: PropTypes.string.isRequired
};
export default BuildSuccessMessage;
