(() => {
  'use strict';

  class AjaxError extends Error {
    constructor(response, data) {
      const message = typeof data?.error === 'string' ? data.error : `HTTP ${response.status}`;
      super(message);
      this.name = 'AjaxError';
      this.status = response.status;
      this.data = data;
      this.response = response;
    }
  }

  async function request(url, { json, headers, ...options } = {}) {
    const requestHeaders = new Headers(headers);
    if (!requestHeaders.has('Accept')) requestHeaders.set('Accept', 'application/json');
    if (json !== undefined) {
      if (options.body != null) throw new TypeError('Provide either json or body.');
      if (!requestHeaders.has('Content-Type')) requestHeaders.set('Content-Type', 'application/json');
      options.body = JSON.stringify(json);
    }

    const response = await fetch(url, { credentials: 'same-origin', ...options, headers: requestHeaders });
    const text = await response.text();
    let data = text || null;
    if (text && /(?:\/|\+)json\b/i.test(response.headers.get('Content-Type') ?? '')) {
      try {
        data = JSON.parse(text);
      } catch (error) {
        if (response.ok) throw error;
      }
    }
    if (!response.ok) throw new AjaxError(response, data);
    return data;
  }

  window.FusionPHP = {
    ajax: {
      request,
      get: (url, options) => request(url, { ...options, method: 'GET' }),
      post: (url, json, options) => request(url, { ...options, method: 'POST', json }),
      AjaxError,
    },
  };

  function setExpanded(target, expanded) {
    if (target.hasAttribute('data-fusion-navbar')) {
      target.toggleAttribute('data-fusion-collapsed', !expanded);
    } else {
      target.hidden = !expanded;
    }
    for (const toggle of document.querySelectorAll('[data-fusion-toggle]')) {
      if (toggle.dataset.fusionToggle === target.id) toggle.setAttribute('aria-expanded', String(expanded));
    }
  }

  function initializeNavbars() {
    for (const trigger of document.querySelectorAll('[data-fusion-toggle]')) {
      const target = document.getElementById(trigger.dataset.fusionToggle);
      if (!target?.hasAttribute('data-fusion-navbar')) continue;
      setExpanded(target, false);
      trigger.hidden = false;
    }
  }

  document.addEventListener('click', event => {
    const control = event.target.closest?.('[data-fusion-toggle], [data-fusion-open], [data-fusion-close], [data-fusion-dismiss]');
    if (!control) return;

    if (control.hasAttribute('data-fusion-toggle')) {
      const target = document.getElementById(control.dataset.fusionToggle);
      if (target) setExpanded(target, control.getAttribute('aria-expanded') !== 'true');
    } else if (control.hasAttribute('data-fusion-open')) {
      const dialog = document.getElementById(control.dataset.fusionOpen);
      if (dialog instanceof HTMLDialogElement && !dialog.open) dialog.showModal();
    } else if (control.hasAttribute('data-fusion-close')) {
      control.closest('dialog')?.close();
    } else {
      const alert = control.closest('.alert');
      if (alert) alert.hidden = true;
    }
  });

  function jsonFields(fields) {
    const result = Object.create(null);
    for (const [name, value] of fields) {
      if (typeof value !== 'string') throw new TypeError('Use a multipart form to upload files.');
      if (!(name in result)) result[name] = value;
      else if (Array.isArray(result[name])) result[name].push(value);
      else result[name] = [result[name], value];
    }
    return result;
  }

  const pending = new WeakSet();
  document.addEventListener('submit', async event => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !form.hasAttribute('data-fusion-ajax')) return;
    event.preventDefault();
    if (pending.has(form)) return;
    pending.add(form);

    const submitter = event.submitter;
    const method = submitter?.getAttribute('formmethod') || form.method;
    const output = document.getElementById(form.dataset.fusionResult ?? '');
    const previousBusy = form.getAttribute('aria-busy');
    form.setAttribute('aria-busy', 'true');
    if (output) output.textContent = 'Sending…';
    try {
        const url = new URL(submitter?.getAttribute('formaction') || form.action, document.baseURI);
      const fields = new FormData(form, submitter ?? undefined);
      const options = { method: method.toUpperCase() };
      if (options.method === 'GET') {
        for (const [name, value] of fields) {
          url.searchParams.append(name, typeof value === 'string' ? value : value.name);
        }
      } else if (form.dataset.fusionAjax === 'json') {
        options.json = jsonFields(fields);
      } else {
        options.body = fields;
      }
      const data = await request(url, options);
      if (output) output.textContent = data == null ? 'Request complete.'
        : typeof data === 'string' ? data : JSON.stringify(data, null, 2);
      form.dispatchEvent(new CustomEvent('fusionphp:success', { bubbles: true, detail: { data } }));
    } catch (error) {
      if (output) output.textContent = error.message;
      form.dispatchEvent(new CustomEvent('fusionphp:error', { bubbles: true, detail: { error } }));
    } finally {
      if (previousBusy === null) form.removeAttribute('aria-busy');
      else form.setAttribute('aria-busy', previousBusy);
      pending.delete(form);
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeNavbars, { once: true });
  } else {
    initializeNavbars();
  }
})();
