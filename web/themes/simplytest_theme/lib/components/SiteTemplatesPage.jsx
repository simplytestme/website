import { useEffect, useState } from 'react';

import { dangerBlock } from '../ui';
import { fetchWithCallback } from '../utils';
import { TemplateBrowser } from './SiteTemplates';

/**
 * The page /templates opens: the template picker without the dialog.
 *
 * Documentation and other sites link here to offer every template at once.
 * Like the picker, nothing launches until the visitor presses a card's button.
 */
function SiteTemplatesPage() {
  const [templates, setTemplates] = useState(null);
  const [errors, setErrors] = useState([]);
  useEffect(() => {
    fetchWithCallback('/site-templates', setTemplates);
  }, []);

  return (
    <div className="flex flex-col gap-6 px-6 pb-20 pt-8 lg:px-16">
      {errors.length > 0 && (
        <div className={dangerBlock} role="alert">
          {errors.map((error) => (
            <p className="m-0 py-1" key={error}>
              {error}
            </p>
          ))}
        </div>
      )}

      <div className="flex flex-col overflow-hidden rounded-2xl border border-st-line2 bg-white shadow-card">
        {templates === null ? (
          <p className="m-0 p-6 text-center text-sm text-st-soft">
            Loading templates&hellip;
          </p>
        ) : (
          <TemplateBrowser
            templates={templates}
            setErrors={setErrors}
            Heading="h1"
          />
        )}
      </div>
    </div>
  );
}

export default SiteTemplatesPage;
