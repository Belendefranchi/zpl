<?php
declare(strict_types=1);
require __DIR__ . '/trabajos_lib.php';

function catalogo(): array {
    $items = [];
    foreach (glob(__DIR__ . '/etiquetas/*.[zZ][pP][lL]') ?: [] as $path) {
        $zpl = file_get_contents($path);
        preg_match_all('/\[([A-Za-z_][A-Za-z0-9_]*)\]/', $zpl, $matches);
        $id = pathinfo($path, PATHINFO_FILENAME);
        $nombre = str_replace('_', ' ', $id);
        if (preg_match('/^etiqueta_(\d+)x(\d+)_(.+)$/i', $id, $parts)) {
            $nombre = $parts[1] . ' × ' . $parts[2] . ' mm — ' . ucfirst(str_replace('_', ' ', $parts[3]));
        }
        $items[$id] = ['id' => $id, 'nombre' => $nombre, 'variables' => array_values(array_unique($matches[1])), 'zpl' => $zpl];
    }
    return $items;
}

try {
    $catalogo = catalogo();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_values(array_map(function ($item) { unset($item['zpl']); return $item; }, $catalogo)), JSON_THROW_ON_ERROR);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $template = $catalogo[$input['plantilla'] ?? ''] ?? null;
    if (!$template) { throw new RuntimeException('Elegí una etiqueta válida.'); }
    $rows = $input['filas'] ?? [];
    if (!is_array($rows) || !$rows || count($rows) > 5000) { throw new RuntimeException('Ingresá entre 1 y 5000 filas.'); }
    $labels = []; $used = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row) || count($row) !== count($template['variables'])) { throw new RuntimeException('Las columnas no coinciden con la etiqueta.'); }
        $replace = [];
        foreach ($template['variables'] as $col => $variable) {
            if (!is_string($row[$col]) || strlen($row[$col]) > 2000 || preg_match('/[\x00-\x1F\x7F\^~]/', $row[$col])) { throw new RuntimeException('Fila ' . ($index + 1) . ': valor inválido o comandos ZPL en los datos.'); }
            $replace['[' . $variable . ']'] = $row[$col];
        }
        $barcodeVariable = null;
        foreach ($template['variables'] as $variable) {
            if (strtoupper($variable) === 'CODBAR') { $barcodeVariable = '[' . $variable . ']'; }
        }
        if ($barcodeVariable !== null) {
            $code = $replace[$barcodeVariable];
            if (trim($code) === '') { throw new RuntimeException('Fila ' . ($index + 1) . ': falta CODBAR.'); }
            if (isset($used[$code])) { throw new RuntimeException('Fila ' . ($index + 1) . ': CODBAR repetido.'); }
            $used[$code] = true;
        }
        $labels[] = strtr($template['zpl'], $replace);
    }
    $action = $input['accion'] ?? '';
    if ($action === 'guardar') {
        $id = guardarTrabajo($template, $rows, $labels);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['id' => $id]);
        exit;
    }
    if ($action === 'descargar') {
        $temp = tempnam(sys_get_temp_dir(), 'zpl_');
        try {
            $zip = new ZipArchive();
            if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('No se pudo crear el ZIP.'); }
            foreach ($labels as $index => $label) {
                $zip->addFromString(sprintf('etiqueta_%05d.txt', $index + 1), $label);
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="etiquetas.zip"');
            header('Content-Length: ' . filesize($temp));
            readfile($temp);
        } finally { if (is_file($temp)) { unlink($temp); } }
        exit;
    }
    if ($action !== 'imprimir') { throw new RuntimeException('Acción inválida.'); }
    $ip = $input['ip'] ?? '';
    if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { throw new RuntimeException('Ingresá una IP válida para la impresora.'); }
    $socket = @stream_socket_client('tcp://' . $ip . ':9100', $errno, $error, 5);
    if (!$socket) { throw new RuntimeException('No se pudo conectar a la impresora.'); }
    try {
        stream_set_timeout($socket, 10);
        foreach ($labels as $label) {
            $offset = 0;
            while ($offset < strlen($label)) {
                $written = @fwrite($socket, substr($label, $offset));
                if (!$written) { throw new RuntimeException('El envío quedó incompleto. Revisá la impresora antes de reintentar para evitar duplicados.'); }
                $offset += $written;
            }
        }
    } finally { fclose($socket); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['mensaje' => count($labels) . ' etiquetas enviadas. Verificá la impresión en el equipo.']);
} catch (Throwable $error) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $error instanceof RuntimeException ? $error->getMessage() : 'No se pudo procesar el trabajo. Revisá los datos.']);
}
