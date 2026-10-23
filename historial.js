'use strict';
let savedJob = null, stopSending = false;
const jobSelect = document.querySelector('#trabajo');
const historyBody = document.querySelector('#hist-filas');
function historyMessage(text, error = false) {
    const node = document.querySelector('#hist-status'); node.textContent = text; node.style.color = error ? '#b02c37' : '#147d72';
    message(text, error);
}
async function request(url, payload) {
    const response = await fetch(url, payload ? {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)} : {});
    if (!response.ok) { const data = await response.json(); throw new Error(data.error || 'No se pudo completar la operación.'); }
    return response;
}
function selectedIndices() { return [...historyBody.querySelectorAll('input:checked')].map(input => Number(input.value)); }
function selectionCount() { document.querySelector('#seleccionados').textContent = selectedIndices().length + ' registros seleccionados'; }
async function refreshHistory(id = jobSelect.value) {
    const jobs = await (await request('trabajos.php')).json(); jobSelect.replaceChildren();
    jobs.forEach(job => {
        const option = document.createElement('option'); option.value = job.id;
        option.textContent = new Date(job.fecha).toLocaleString('es-AR', {timeZone:'America/Argentina/Buenos_Aires'}) + ' · ' + job.nombre + ' · ' + job.cantidad + ' registros';
        jobSelect.append(option);
    });
    if (jobs.some(job => job.id === id)) jobSelect.value = id;
    await openJob();
}
async function openJob() {
    savedJob = null; historyBody.replaceChildren(); document.querySelector('#hist-cabecera').replaceChildren();
    if (!jobSelect.value) { document.querySelector('#detalle-trabajo').textContent = 'Todavía no hay trabajos guardados.'; selectionCount(); return; }
    const job = await (await request('trabajos.php?id=' + encodeURIComponent(jobSelect.value))).json();
    // Ignorar respuestas de una selección anterior.
    if (jobSelect.value !== job.id) return;
    savedJob = job;
    document.querySelector('#detalle-trabajo').textContent = job.nombre + ' — ' + job.cantidad + ' registros guardados';
    for (const title of ['Elegir', '#', ...job.variables]) { const th = document.createElement('th'); th.textContent = title; document.querySelector('#hist-cabecera').append(th); }
    const fragment = document.createDocumentFragment();
    job.filas.forEach((row, index) => {
        const tr = document.createElement('tr'); const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.value = index; checkbox.checked = true;
        checkbox.setAttribute('aria-label', 'Seleccionar registro ' + (index + 1)); tr.insertCell().append(checkbox);
        for (const value of [index + 1, ...row]) { const td = tr.insertCell(); td.textContent = value; td.style.padding = '8px'; }
        fragment.append(tr);
    });
    historyBody.append(fragment); document.querySelector('#hasta').value = job.cantidad; selectionCount();
}
function lockActions(locked) {
    busy = locked;
    document.querySelectorAll('.submit, #trabajo, #actualizar').forEach(node => node.disabled = locked);
}
async function performSaved(action, id, indices) {
    if (!indices.length) throw new Error('Seleccioná al menos un registro.');
    if (action === 'descargar') {
        const response = await request('trabajos.php', {accion:action, id, seleccion:indices});
        const url = URL.createObjectURL(await response.blob()); const a = document.createElement('a'); a.href = url; a.download = 'etiquetas.zip'; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        historyMessage(indices.length + ' registros descargados. El trabajo queda guardado.'); return;
    }
    const size = Number(document.querySelector('#tanda').value), pause = Number(document.querySelector('#pausa').value), ip = document.querySelector('#ip').value;
    if (!Number.isInteger(size) || size < 1 || size > 500 || !Number.isFinite(pause) || pause < 0 || pause > 60) throw new Error('Usá tandas de 1 a 500 y pausas de 0 a 60 segundos.');
    const job = savedJob?.id === id ? savedJob : await (await request('trabajos.php?id=' + encodeURIComponent(id))).json();
    const selected = new Set(indices);
    indices = job.ordenImpresion.filter(index => selected.has(index));
    stopSending = false; document.querySelector('#detener').disabled = false;
    let sent = 0;
    try {
        for (let offset = 0; offset < indices.length; offset += size) {
            if (stopSending) break;
            const batch = indices.slice(offset, offset + size);
            historyMessage('Enviando tanda de ' + batch.length + ' registros en orden inverso por código…');
            try { await request('trabajos.php', {accion:'imprimir', id, seleccion:batch, ip}); }
            catch (error) { throw new Error(error.message + ' Tanda afectada: registros ' + batch.map(index => index + 1).join(', ') + '. No se reintenta automáticamente.'); }
            sent += batch.length;
            historyMessage(sent + ' de ' + indices.length + ' registros enviados. Verificá la impresión en el equipo.');
            if (offset + size < indices.length) {
                const end = Date.now() + pause * 1000;
                while (!stopSending && Date.now() < end) await new Promise(resolve => setTimeout(resolve, 200));
            }
        }
        if (stopSending) historyMessage('Envío detenido: ' + sent + ' registros enviados. El trabajo completo sigue guardado.');
    } finally { document.querySelector('#detener').disabled = true; }
}
async function submit(action) {
    if (busy || !current) return;
    const rows = values().filter(row => row.some(value => value.trim()));
    if (!rows.length) { message('Ingresá al menos una fila.', true); return; }
    lockActions(true); historyMessage('Guardando trabajo…');
    try {
        const job = await (await request('etiquetas.php', {accion:'guardar', plantilla:current.id, filas:rows})).json();
        await refreshHistory(job.id);
        await performSaved(action, job.id, rows.map((_, index) => index));
    } catch (error) { historyMessage(error.message, true); }
    finally { lockActions(false); }
}
async function repeat(action) {
    if (busy || !savedJob) return;
    const id = savedJob.id, indices = selectedIndices(); lockActions(true);
    try { await performSaved(action, id, indices); } catch (error) { historyMessage(error.message, true); } finally { lockActions(false); }
}
historyBody.addEventListener('change', selectionCount);
jobSelect.onchange = () => openJob().catch(error => historyMessage(error.message, true));
document.querySelector('#actualizar').onclick = () => refreshHistory().catch(error => historyMessage(error.message, true));
document.querySelector('#todos').onclick = () => { historyBody.querySelectorAll('input').forEach(input => input.checked = true); selectionCount(); };
document.querySelector('#ninguno').onclick = () => { historyBody.querySelectorAll('input').forEach(input => input.checked = false); selectionCount(); };
document.querySelector('#rango').onclick = () => {
    const from = Number(document.querySelector('#desde').value), to = Number(document.querySelector('#hasta').value);
    if (!savedJob || !Number.isInteger(from) || !Number.isInteger(to) || from < 1 || to < from || to > savedJob.cantidad) { historyMessage('Ingresá un rango válido dentro del trabajo.', true); return; }
    historyBody.querySelectorAll('input').forEach(input => input.checked = Number(input.value) >= from - 1 && Number(input.value) < to); selectionCount();
};
document.querySelector('#hist-descargar').onclick = () => repeat('descargar');
document.querySelector('#hist-imprimir').onclick = () => repeat('imprimir');
document.querySelector('#detener').onclick = () => { stopSending = true; };
refreshHistory().catch(error => historyMessage(error.message, true));
