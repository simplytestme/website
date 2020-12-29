import React, { useState } from 'react'
import PropTypes from 'prop-types';
import { useCombobox } from 'downshift'

function ProjectAutocomplete({ setSelectedItem }) {
  const itemToString = (item) => (item ? item.title : "");
  const [inputItems, setInputItems] = useState([]);
  const {
    isOpen,
    getLabelProps,
    getMenuProps,
    getInputProps,
    getComboboxProps,
    highlightedIndex,
    getItemProps,
  } = useCombobox({
    items: inputItems,
    itemToString,
    onSelectedItemChange: ({ selectedItem }) => {
      setSelectedItem(selectedItem);
    },
    onInputValueChange: ({ inputValue }) => {
      fetch(`/simplytest/projects/autocomplete?string=${inputValue}`)
        .then((res) => res.json())
        .then((json) => {
          if (!Array.isArray(json)) {
            setInputItems([]);
          } else {
            setInputItems(json);
          }
        });
    },
  });

  return (
    <div className="flex-grow mr-2 relative">
      <label {...getLabelProps()} className="sr-only">
        Enter a project name
      </label>
      <div {...getComboboxProps({
        className: "relative"
      })}>
        <input
          {...getInputProps()}
          tabIndex="-1"
          autoFocus={true}
          className="text-xl font-sans border rounded-md shadow px-4 py-1 w-full"
        />
      </div>
      <ul
        {...getMenuProps({
          className: isOpen
            ? "mt-1 w-full rounded-md bg-white shadow-lg border max-h-56 overflow-auto absolute z-10"
            : "",
        })}
      >
        {isOpen &&
        inputItems.map((item, index) => (
          <li
            className="text-gray-900 cursor-default select-none relative py-2 pl-3 pr-9"
            style={
              highlightedIndex === index ? { backgroundColor: "#bde4ff" } : {}
            }
            key={`${item}${index}`}
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
ProjectAutocomplete.propTypes = {
  setSelectedItem: PropTypes.func.isRequired
}
export default ProjectAutocomplete;
