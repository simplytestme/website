import React, { useEffect, useState } from "react";
import PropTypes from "prop-types";
import { useCombobox } from "downshift";
import { debounce } from "../utils";
import { inputHero, inputPanel } from "../ui";

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
    onInputValueChange: ({ inputValue }) => {
      debounce(() => fetchProjects(inputValue, setInputItems))();
    }
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
    <div className="relative flex-1">
      {additionalBtn === true ? (
        <label {...getLabelProps()} className="sr-only">
          Additional project name
        </label>
      ) : (
        <label {...getLabelProps()} className="field-label mb-2 block">
          Module, theme or distribution
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
          className={additionalBtn ? inputPanel : inputHero}
          placeholder="Start typing the name of a project"
        />
      </div>
      <ul
        {...getMenuProps({
          className: isOpen
            ? "absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-[10px] border border-st-field-line bg-white p-0 shadow-card"
            : ""
        })}
      >
        {isOpen &&
          inputItems.map((item, index) => (
            <li
              className={`cursor-default select-none list-none px-3.5 py-3 ${
                highlightedIndex === index ? "bg-st-accent-tint" : ""
              }`}
              key={item.shortname}
              {...getItemProps({ item, index })}
            >
              <span className="block text-sm font-semibold text-st-body">{item.title}</span>
              <span className="block font-mono text-[11px] text-st-faint">{item.shortname}</span>
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
