(() => {
  const key = 'fusionphp.appearance';
  const defaults = { theme: 'system', accent: 'blue', font: 'inter' };
  const fonts = {
    inter: 'Inter',
    'dm-sans': 'DM+Sans',
    'source-serif': 'Source+Serif+4',
    system: null,
  };
  const options = {
    theme: ['system', 'light', 'dark'],
    accent: ['blue', 'teal', 'violet'],
    font: Object.keys(fonts),
  };
  const fields = Object.keys(defaults);
  const settings = { ...defaults };
  const root = document.documentElement;

  try {
    const stored = JSON.parse(localStorage.getItem(key));
    for (const field of fields) {
      if (options[field].includes(stored?.[field])) {
        settings[field] = stored[field];
      }
    }
  } catch {
    // The default settings also work with storage disabled.
  }

  function apply() {
    for (const field of fields) {
      root.dataset[field] = settings[field];
    }
  }

  apply();

  document.addEventListener('DOMContentLoaded', () => {
    const controls = document.getElementById('appearance-controls');
    if (!controls) return;

    const attributes = document.getElementById('appearance-example-attributes');
    const fontLine = document.getElementById('appearance-example-font-link');
    const fontUrl = fontLine.querySelector('.code-string:last-child');
    const reset = document.getElementById('appearance-reset');
    const status = document.getElementById('appearance-status');

    function render() {
      for (const field of fields) {
        controls.querySelector(`[name="${field}"]`).value = settings[field];
      }

      const markup = document.createDocumentFragment();
      for (const field of fields) {
        if (markup.childNodes.length) markup.append(' ');
        const value = document.createElement('span');
        value.className = 'code-string';
        value.textContent = `"${settings[field]}"`;
        markup.append(`data-${field}=`, value);
      }
      attributes.replaceChildren(markup);

      const font = fonts[settings.font];
      fontLine.hidden = !font;
      fontUrl.textContent = font
        ? `"https://fonts.googleapis.com/css2?family=${font}:wght@400..800&amp;display=swap"`
        : '';
    }

    function save(message, resetSettings = false) {
      apply();
      render();
      try {
        if (resetSettings) {
          localStorage.removeItem(key);
        } else {
          localStorage.setItem(key, JSON.stringify(settings));
        }
        status.textContent = message;
      } catch {
        status.textContent = 'Settings apply to this page only. Browser storage is unavailable.';
      }
    }

    controls.addEventListener('change', event => {
      const { name, value } = event.target;
      if (!fields.includes(name) || !options[name].includes(value)) return;

      settings[name] = value;
      save('Preferences saved.');
    });

    reset.addEventListener('click', () => {
      Object.assign(settings, defaults);
      save('Default appearance restored.', true);
    });

    render();
    for (const control of controls.querySelectorAll('select')) {
      control.disabled = false;
    }
    reset.hidden = false;
  });
})();
