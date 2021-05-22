import React, { useEffect, useState } from "react";
import PropTypes from "prop-types";
import { useCombobox } from "downshift";

function fetchProjects(inputValue, callback) {
  fetch(`/simplytest/projects/autocomplete?string=${inputValue}`)
    .then(res => res.json())
    .then(json => {
      if (!Array.isArray(json)) {
        callback([]);
      } else {
        callback(json);
      }
    });
}

function ProjectAutocomplete({
  initialProject,
  setSelectedItem,
  additionalBtn
}) {
  const [inputItems, setInputItems] = useState([]);

  const {
    isOpen,
    getLabelProps,
    getMenuProps,
    getInputProps,
    getComboboxProps,
    highlightedIndex,
    getItemProps,
    setInputValue
  } = useCombobox({
    items: inputItems,
    itemToString: item => (item ? item.title : ""),
    onSelectedItemChange: ({ selectedItem }) => {
      setSelectedItem(selectedItem);
    },
    onInputValueChange: ({ inputValue }) =>
      fetchProjects(inputValue, setInputItems)
  });

  // If there is an initial project, kick off a query to populate list items
  // from it's shortname, and then set it's title as the input.
  // Downshift doesn't have a way to manually set the selected item without
  // forcing a component to control the selected item at all times.
  useEffect(
    () => {
      if (initialProject && initialProject.shortname) {
        fetchProjects(initialProject.shortname, items => {
          setInputItems(items);
          const matches = items.filter(
            item => item.shortname === initialProject.shortname
          );
          if (matches.length === 1) {
            setInputValue(matches[0].title);
          }
        });
      }
    },
    [initialProject]
  );

  return (
    <div className="flex-grow mr-2 relative">
      {additionalBtn === true ? (
        <label {...getLabelProps()} className="mb-2 text-sm text-white">
          Additional project name
        </label>
      ) : (
        <label {...getLabelProps()} className="evaluate-project label">
          Evaluate Drupal projects
        </label>
      )}
      <div
        {...getComboboxProps({
          className: "relative"
        })}
      >
        <input
          {...getInputProps()}
          type="text"
          tabIndex="-1"
          className="text-lg font-sans border rounded-md shadow px-4 py-1 w-full"
        />
      </div>
      <ul
        {...getMenuProps({
          className: isOpen
            ? "mt-1 w-full rounded-md bg-white shadow-lg border max-h-56 overflow-auto absolute z-10"
            : ""
        })}
      >
        {isOpen &&
          inputItems.map((item, index) => (
            <li
              className="text-gray-900 cursor-default select-none relative py-2 pl-3 pr-9"
              style={
                highlightedIndex === index ? { backgroundColor: "#bde4ff" } : {}
              }
              key={item.shortname}
              {...getItemProps({ item, index })}
            >
              <span className="font-bold">{item.title}</span>
              <br />
              {item.shortname}
            </li>
          ))}
      </ul>
    </div>
  );
}
ProjectAutocomplete.defaultProps = {
  initialProject: null,
  additionalBtn: false
};
ProjectAutocomplete.propTypes = {
  initialProject: PropTypes.shape({
    shortname: PropTypes.string.isRequired
  }),
  setSelectedItem: PropTypes.func.isRequired,
  additionalBtn: PropTypes.bool
};
export default ProjectAutocomplete;
