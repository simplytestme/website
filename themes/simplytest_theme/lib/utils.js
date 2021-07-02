export function fetchWithCallback(url, callback) {
  fetch(url)
    .then(res => res.json())
    .then(json => callback(json));
}
