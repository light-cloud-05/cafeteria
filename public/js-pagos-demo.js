// Mejora progresiva: los formularios funcionan sin JavaScript. Nunca reenvía ni crea intentos.
document.querySelectorAll('[data-pago-inicio], [data-pago-accion]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.enviando === '1') { event.preventDefault(); return; }
        form.dataset.enviando = '1';
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => {
            button.disabled = true;
            button.textContent = 'Procesando solicitud local…';
        });
    });
});
window.addEventListener('pageshow', event => {
    // Restaurar controles al volver desde la caché del navegador; el backend mantiene idempotencia.
    if (event.persisted) window.location.reload();
});
