(() => {
  const form = document.querySelector('[data-borrador]');
  if (!form) return;
  const key = form.dataset.borrador;
  try {
    if (form.dataset.guardado === '1') localStorage.removeItem(key);
    const saved = JSON.parse(localStorage.getItem(key) || '{}');
    if (!form.querySelector('#lineas') && form.querySelector('[data-errores]')?.dataset.errores !== '1') Object.entries(saved).forEach(([name,value]) => {
      const field = form.elements.namedItem(name);
      if (field) field.value = value;
    });
  } catch (e) {}
  function save() {
    const data = {};
    if (form.querySelector('#lineas')) {
      data.promocion_id = form.elements.namedItem('promocion_id')?.value || '';
      data.lineas = Array.from(form.querySelectorAll('.linea')).map(line => {
        const result = {};
        line.querySelectorAll('[data-campo]').forEach(field => result[field.dataset.campo] = field.value);
        return result;
      });
    } else {
      Array.from(form.elements).forEach(field => { if (field.name && field.name !== '_token') data[field.name] = field.value; });
    }
    try { localStorage.setItem(key, JSON.stringify(data)); } catch (e) {}
  }
  form.addEventListener('input', save);
  form.addEventListener('change', save);
  form.addEventListener('submit', save);
  form.querySelector('[data-descartar]')?.addEventListener('click', () => {
    try { localStorage.removeItem(key); } catch (e) {}
    location.href = form.dataset.cancelar || form.action;
  });
})();
