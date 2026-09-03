import { useEffect, useState } from 'react';
import { useCombobox } from 'downshift';
import PropTypes from 'prop-types';

import { inputHero, inputPanel } from '../ui';
import { debounce } from '../utils';

function fetchProjects(inputValue, callback) {
  fetch(`/simplytest/projects/autocomplete?string=${inputValue}`)
    .then((res) => res.json())
    .then((json) => {
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
  additionalBtn,
}) {
  const [inputItems, setInputItems] = useState([]);
  const [searched, setSearched] = useState(false);
  // null | "looking" | {message} for a failed lookup.
  const [lookup, setLookup] = useState(null);

  const {
    isOpen,
    getLabelProps,
    getMenuProps,
    getInputProps,
    getComboboxProps,
    highlightedIndex,
    getItemProps,
    inputValue,
    setInputValue,
    selectItem,
    closeMenu,
  } = useCombobox({
    items: inputItems,
    itemToString: (item) => (item ? item.title : ''),
    onSelectedItemChange: ({ selectedItem }) => {
      setSelectedItem(selectedItem);
    },
    onInputValueChange: ({ inputValue: value }) => {
      setLookup(null);
      setSearched(false);
      debounce(() => {
        fetchProjects(value, (items) => {
          setInputItems(items);
          setSearched(true);
        });
      })();
    },
  });

  // The autocomplete only searches projects the site already knows about.
  // Anything else needs an explicit lookup against Drupal.org.
  function lookupOnDrupalOrg(name) {
    setLookup('looking');
    fetch('/simplytest/projects/lookup', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ name }),
    })
      .then((res) =>
        res.json().then((json) => {
          if (res.ok) {
            setLookup(null);
            setInputItems([json]);
            // selectItem() alone leaves the menu open; close it so the
            // selection reads as done.
            selectItem(json);
            closeMenu();
          } else {
            setLookup({
              message:
                json.message || 'The lookup failed. Try again in a minute.',
            });
          }
        }),
      )
      .catch(() => {
        setLookup({ message: 'The lookup failed. Try again in a minute.' });
      });
  }

  // If there is an initial project, kick off a query to populate list items
  // from it's shortname, and then set it's title as the input.
  // Downshift doesn't have a way to manually set the selected item without
  // forcing a component to control the selected item at all times.
  useEffect(() => {
    if (initialProject && initialProject.shortname) {
      fetchProjects(initialProject.shortname, (items) => {
        setInputItems(items);
        const matches = items.filter(
          (item) => item.shortname === initialProject.shortname,
        );
        if (matches.length === 1) {
          setInputValue(matches[0].title);
        } else if (items.length === 0 && !initialProject.title) {
          // A deep link (?project=…) to a project the site does not know
          // yet. The link is explicit intent, so run the lookup for it —
          // additional-project rows always carry a title and never end up
          // here.
          lookupOnDrupalOrg(initialProject.shortname);
        }
      });
    }
    // Only a new initial project should trigger this. The Downshift setter
    // and the lookup helper are recreated on every render and must not.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialProject]);

  const showLookup =
    searched && inputItems.length === 0 && inputValue.trim().length >= 3;

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
          className: 'relative',
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
            ? 'absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-[10px] border border-st-field-line bg-white p-0 shadow-card'
            : '',
        })}
      >
        {isOpen &&
          inputItems.map((item, index) => (
            <li
              className={`cursor-default select-none list-none px-3.5 py-3 ${
                highlightedIndex === index ? 'bg-st-accent-tint' : ''
              }`}
              key={item.shortname}
              {...getItemProps({ item, index })}
            >
              <span className="block text-sm font-semibold text-st-body">
                {item.title}
              </span>
              <span className="block font-mono text-[11px] text-st-faint">
                {item.shortname}
              </span>
            </li>
          ))}
        {isOpen && showLookup && (
          <li className="list-none px-3.5 py-3">
            {lookup === null && (
              <button
                type="button"
                className="text-sm font-semibold text-st-accent-dark hover:text-st-accent"
                onClick={lookupOnDrupalOrg}
              >
                Look up “{inputValue.trim()}” on drupal.org
              </button>
            )}
            {lookup === 'looking' && (
              <span className="text-sm text-st-muted">
                Checking drupal.org…
              </span>
            )}
            {lookup !== null && lookup !== 'looking' && (
              <span className="text-sm text-st-muted">{lookup.message}</span>
            )}
          </li>
        )}
      </ul>
    </div>
  );
}
ProjectAutocomplete.defaultProps = {
  initialProject: null,
  additionalBtn: false,
};
ProjectAutocomplete.propTypes = {
  initialProject: PropTypes.shape({
    shortname: PropTypes.string.isRequired,
  }),
  setSelectedItem: PropTypes.func.isRequired,
  additionalBtn: PropTypes.bool,
};
export default ProjectAutocomplete;
