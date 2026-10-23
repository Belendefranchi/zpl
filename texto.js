'use strict';
function analizarSocios(text) {
    const normalized = text.replace(/\u00a0/g, ' ').trim();
    const starts = [...normalized.matchAll(/\bSocio\s*:?[ \t]*([A-Za-z]+\d+)\s*[:,]/gi)];
    if (!starts.length) throw new Error('No se encontraron socios. Usá el formato: Socio L1467, Razón Social: …, Dirección: …, Localidad: …');
    if (normalized.slice(0, starts[0].index).trim()) throw new Error('Hay texto sin reconocer antes del primer socio.');
    const result = [], seen = new Set();
    starts.forEach((match, index) => {
        const block = normalized.slice(match.index + match[0].length, starts[index + 1]?.index ?? normalized.length).trim();
        const fields = block.match(/^Raz[oó]n\s+Social\s*:\s*([\s\S]+?)\s*,\s*Direcci[oó]n\s*:\s*([\s\S]+?)\s*,\s*Localidad\s*:\s*([\s\S]+?)\s*$/i);
        if (!fields || /\bSocio\s*[: ]/i.test(block)) throw new Error('No se pudo interpretar completamente el registro de ' + match[1] + '. Revisá razón social, dirección y localidad.');
        const socio = match[1].toUpperCase();
        if (seen.has(socio)) throw new Error('El socio ' + socio + ' está repetido en el texto.');
        seen.add(socio);
        for (const tipo of ['MF', 'BF']) result.push({socio, tipo, codbar:socio + ' ' + tipo, nombre:fields[1].trim(), localidad:fields[3].trim()});
    });
    return result;
}
// Exportar el analizador permite verificar el formato sin depender del navegador.
if (typeof module !== 'undefined') module.exports = {analizarSocios};
if (typeof document !== 'undefined') {
    const panel = document.querySelector('#texto-panel');
    const preview = document.querySelector('#texto-preview');
    const feedback = document.querySelector('#texto-status');
    const apply = document.querySelector('#texto-aplicar');
    let parsed = null;
    function invalidate() { parsed = null; preview.replaceChildren(); apply.disabled = true; feedback.textContent = ''; }
    function updatePanel() { panel.hidden = current?.id !== 'etiqueta_70x34_masfarmacias'; invalidate(); }
    select.addEventListener('change', updatePanel);
    new MutationObserver(updatePanel).observe(head, {childList:true});
    document.querySelector('#texto-entrada').addEventListener('input', invalidate);
    document.querySelector('#texto-analizar').onclick = () => {
        invalidate();
        try {
            if (current?.id !== 'etiqueta_70x34_masfarmacias') throw new Error('Elegí la etiqueta Más Farmacias.');
            parsed = analizarSocios(document.querySelector('#texto-entrada').value);
            const table = document.createElement('table'); const heading = table.createTHead().insertRow();
            for (const variable of current.variables) { const th = document.createElement('th'); th.textContent = variable; heading.append(th); }
            const tbody = table.createTBody();
            parsed.forEach(row => { const tr = tbody.insertRow(); current.variables.forEach(variable => { const td = tr.insertCell(); td.textContent = row[variable.toLowerCase()] || '(pendiente)'; td.style.padding = '8px'; }); });
            preview.append(table); apply.disabled = false;
            feedback.textContent = parsed.length / 2 + ' socios → ' + parsed.length + ' registros: MF y BF para cada socio. El código de barras se forma uniendo socio y tipo, separados por un espacio. Se conserva el código postal en localidad; la dirección no se usa en este diseño.';
        } catch (error) { parsed = null; feedback.textContent = error.message; }
    };
    apply.onclick = () => {
        if (!parsed || current?.id !== 'etiqueta_70x34_masfarmacias') return;
        const existing = values().filter(row => row.some(value => value.trim()));
        if (existing.length + parsed.length > 5000) { feedback.textContent = 'Se supera el límite de 5000 filas.'; return; }
        const socioIndex = current.variables.findIndex(variable => variable.toLowerCase() === 'socio');
        const tipoIndex = current.variables.findIndex(variable => variable.toLowerCase() === 'tipo');
        if (parsed.some(item => existing.some(row => row[socioIndex].trim().toUpperCase() === item.socio && row[tipoIndex].trim().toUpperCase() === item.tipo))) { feedback.textContent = 'La planilla ya contiene alguno de estos socios y tipos. Revisala antes de agregar.'; return; }
        body.replaceChildren(); existing.forEach(row => addRow(row));
        parsed.forEach(row => addRow(current.variables.map(variable => row[variable.toLowerCase()] ?? '')));
        count(); invalidate(); feedback.textContent = 'Registros agregados con sus códigos de barras. Revisá la planilla antes de generar o imprimir.';
    };
}
