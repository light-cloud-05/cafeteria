(() => {
    const normalizar = valor => valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    function campo(etiqueta, alCambiar) {
        const label = document.createElement('label');
        label.textContent = etiqueta;
        const input = document.createElement('input');
        input.type = 'search'; input.placeholder = 'Escribe el nombre del producto';
        label.append(input); input.addEventListener('input', () => alCambiar(normalizar(input.value)));
        return label;
    }
    function prepararSelect(select) {
        if (select.dataset.busquedaLista || !Array.from(select.options).some(o => o.textContent.includes('stock') || o.dataset.precio) && !/producto/.test(select.name)) return;
        select.dataset.busquedaLista = '1';
        select.before(campo('Buscar producto 🔍 ', termino => {
            Array.from(select.options).forEach(o => { o.hidden = !!o.value && !o.selected && !normalizar(o.textContent).includes(termino); });
        }));
    }
    document.querySelectorAll('table').forEach(tabla => {
        if (tabla.dataset.busquedaRemota) return;
        const encabezados = Array.from(tabla.querySelectorAll('thead th'));
        const columna = encabezados.findIndex(th => /producto/i.test(th.textContent));
        const listadoProductos = /\/productos$|\/stocks$|\/stock-minimo$|\/agotados$|\/catalogo$/.test(location.pathname);
        if (columna < 0 && !listadoProductos) return;
        tabla.before(campo('Buscar producto 🔍 ', termino => {
            tabla.querySelectorAll('tbody tr').forEach(fila => {
                const texto = fila.cells[Math.max(0,columna)]?.textContent || '';
                fila.hidden = !normalizar(texto).includes(termino);
            });
        }));
    });
    document.querySelectorAll('select').forEach(prepararSelect);
    new MutationObserver(cambios => cambios.forEach(c => c.addedNodes.forEach(n => {
        if (n.nodeType !== 1) return;
        if (n.matches('select')) prepararSelect(n);
        n.querySelectorAll('select').forEach(prepararSelect);
    }))).observe(document.body,{childList:true,subtree:true});
})();
