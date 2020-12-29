import React from 'react';
import PropTypes from 'prop-types';

function Fieldset({ summary, children }) {
  return (
    <fieldset className="flex flex-col mt-4">
      <summary className="font-medium text-xl pb-2 border-b mb-2">{summary}</summary>
      {children}
    </fieldset>
  )
}
Fieldset.propTypes = {
  summary: PropTypes.string.isRequired
}
export default Fieldset;
