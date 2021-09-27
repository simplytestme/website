export function fetchWithCallback(url, callback) {
  fetch(url)
    .then(res => res.json())
    .then(json => callback(json));
}

let timer;
export function debounce(func) {
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      func.apply(this, args);
    }, 300);
  };
}
