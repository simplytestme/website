// Launching a one click demo or a site template. Both go through the same
// endpoint: a site template is a demo whose plugin comes from Drupal CMS's
// curated list rather than a class in this repository.

/**
 * Posts a launch and sends the browser to the progress page.
 *
 * @param {{id: string, title: string}} item
 *   The demo or template to launch. The title lets the progress page name
 *   the build.
 * @param {Function} setProcessing
 *   Receives the launching item's ID while the request is in flight, and an
 *   empty string once it fails.
 * @param {Function} setErrors
 *   Receives the messages to render when the launch is refused.
 */
export default function launch(item, setProcessing, setErrors) {
  setProcessing(item.id);
  // A site template's plugin ID is a derivative, so it carries a colon.
  fetch(`/one-click-demos/${encodeURIComponent(item.id)}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  })
    .then((res) => {
      res
        .json()
        .then((json) => {
          if (res.ok) {
            const params = new URLSearchParams({
              demo: item.id,
              title: item.title,
            });
            window.location.href = `${json.progress}?${params.toString()}`;
          } else {
            setProcessing('');
            setErrors([json.message]);
          }
        })
        .catch((error) => {
          setProcessing('');
          setErrors([error.message]);
        });
    })
    .catch((error) => {
      setProcessing('');
      setErrors([error.message]);
    });
}
