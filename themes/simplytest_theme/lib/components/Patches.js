import React from 'react';

function Patches({ patches, setPatches }) {
  if (patches.length === 0) {
    patches.push("")
  }

  function addPatch() {
    setPatches([...patches, []]);
  }

  return (
    <div>
      <h3 className="font-bold mb-2">Patches</h3>
      {patches.map((patch, k) => (
        <div key={k} className="mb-2 flex flex-row">
          <input type="text" value={patch} onChange={event => {
            const newPatches = [...patches];
            newPatches[k] = event.target.value;
            debugger;
            setPatches(newPatches);
          }} className="text-lg font-sans border rounded-md shadow px-4 py-1 flex-grow w-full" placeholder="https://www.drupal.org/files/..."/>
        </div>
      ))}
      <button type="button" className="text-base p-2 rounded-md shadow-sm border border-gray-300" onClick={addPatch}>Add patch</button>
    </div>
  )
}
export default Patches
