import React from "react";
import PropTypes from "prop-types";

function Fieldset({ summary, children }) {
  return (
    <fieldset className="flex flex-col mt-4">
      <summary className="font-medium text-xl pb-2 p-0 mb-2 text-white">
        {summary}
      </summary>
      {children}
    </fieldset>
  );
}
Fieldset.propTypes = {
  summary: PropTypes.string.isRequired,
  children: PropTypes.oneOfType([
    PropTypes.arrayOf(PropTypes.node),
    PropTypes.node
  ]).isRequired
};
export default Fieldset;
