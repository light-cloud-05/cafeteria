document.querySelectorAll('.paquete-boton').forEach((boton) => {
    boton.addEventListener('click', () => {
        const abrir = boton.getAttribute('aria-expanded') !== 'true';
        document.querySelectorAll('.paquete-boton').forEach((otro) => {
            otro.setAttribute('aria-expanded', 'false');
            document.getElementById(otro.getAttribute('aria-controls')).hidden = true;
        });
        boton.setAttribute('aria-expanded', String(abrir));
        document.getElementById(boton.getAttribute('aria-controls')).hidden = !abrir;
    });
});

document.getElementById('menu-principal')?.addEventListener('keydown', (evento) => {
    if (evento.key !== 'Escape') return;
    const boton = document.querySelector('.paquete-boton[aria-expanded="true"]');
    if (boton) {
        boton.click();
        boton.focus();
    }
});
