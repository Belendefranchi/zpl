'use strict';
const select = document.querySelector('#plantilla');
const head = document.querySelector('#cabecera');
const body = document.querySelector('#filas');
const status = document.querySelector('#status');
let templates = [], current = null, busy = false;
function message(text, error = false) { status.textContent = text; status.className = error ? 'error' : 'success'; }
function values() { return [...body.rows].map(row => [...row.querySelectorAll('input')].map(input => input.value)); }
function count() { document.querySelector('#cantidad').textContent = values().filter(row => row.some(value => value.trim())).length + ' filas con datos'; }
function addRow(values = []) {
    const tr = body.insertRow();
    tr.insertCell().className = 'number';
    current.variables.forEach((variable, index) => {
        const input = document.createElement('input'); input.type = 'text'; input.value = values[index] ?? '';
        input.setAttribute('aria-label', variable); input.autocomplete = 'off';
        tr.insertCell().append(input);
    });
    const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = '×'; remove.setAttribute('aria-label', 'Eliminar fila');
    remove.onclick = () => { tr.remove(); renumber(); count(); };
    tr.insertCell().append(remove); renumber();
}
function renumber() { [...body.rows].forEach((row, index) => { row.cells[0].textContent = index + 1; }); }
function render() {
    current = templates.find(item => item.id === select.value); head.replaceChildren(); body.replaceChildren();
    if (!current) return;
    for (const title of ['#', ...current.variables, '']) { const th = document.createElement('th'); th.textContent = title; head.append(th); }
    for (let i = 0; i < 8; i++) addRow(); count();
    message(current.variables.length ? 'Pegá los datos en el mismo orden que las columnas. CODBAR es obligatorio cuando aparece.' : 'Este diseño no tiene variables.');
}
select.addEventListener('change', () => {
    const previous = current;
    if (values().some(row => row.some(value => value.trim())) && !confirm('Cambiar el diseño borrará los datos de la planilla. ¿Continuar?')) { select.value = previous.id; return; }
    render();
});
body.addEventListener('input', count);
// TSV de Excel/Sheets: conserva celdas vacías y comillas escapadas.
function parseClipboard(text) {
    const rows = [[]]; let field = '', quoted = false;
    text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (c === '"' && (quoted || field === '')) {
            if (quoted && text[i + 1] === '"') { field += '"'; i++; } else quoted = !quoted;
        } else if (!quoted && (c === '\t' || c === '\n')) {
            rows[rows.length - 1].push(field); field = '';
            if (c === '\n') rows.push([]);
        } else field += c;
    }
    rows[rows.length - 1].push(field);
    if (text.endsWith('\n') && rows.at(-1).length === 1 && rows.at(-1)[0] === '') rows.pop();
    return rows;
}
body.addEventListener('paste', event => {
    if (!(event.target instanceof HTMLInputElement)) return;
    event.preventDefault();
    const pasted = parseClipboard(event.clipboardData.getData('text/plain'));
    const rowIndex = event.target.closest('tr').sectionRowIndex, colIndex = event.target.closest('td').cellIndex - 1;
    if (rowIndex + pasted.length > 5000 || pasted.some(row => row.length + colIndex > current.variables.length)) { message('Los datos exceden las columnas del diseño o el límite de 5000 filas.', true); return; }
    while (body.rows.length < rowIndex + pasted.length) addRow();
    pasted.forEach((row, r) => row.forEach((value, c) => { body.rows[rowIndex + r].cells[colIndex + c + 1].firstChild.value = value; }));
    count(); message('Datos pegados. Revisá las filas antes de generar o imprimir.');
});
document.querySelector('#agregar').onclick = () => { if (current && body.rows.length < 5000) { addRow(); count(); } };
document.querySelector('#limpiar').onclick = () => { if (confirm('¿Borrar los datos de la planilla?')) render(); };
document.querySelector('#descargar').onclick = () => submit('descargar');
document.querySelector('#imprimir').onclick = () => submit('imprimir');
fetch('etiquetas.php').then(response => { if (!response.ok) throw new Error('No se pudo cargar el catálogo.'); return response.json(); }).then(data => {
    templates = data; select.replaceChildren();
    data.forEach(item => { const option = document.createElement('option'); option.value = item.id; option.textContent = item.nombre; select.append(option); });
    if (!data.length) throw new Error('No hay diseños disponibles en el catálogo.'); render();
}).catch(error => message(error.message, true));
