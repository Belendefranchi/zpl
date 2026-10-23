<?php
declare(strict_types=1);
require __DIR__ . '/trabajos_lib.php';
header('Cache-Control: no-store');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['id'])) {
            $result = leerTrabajo((string) $_GET['id']); unset($result['zpl']);
            $result['ordenImpresion'] = ordenImpresion($result);
        } else {
            $result = [];
            foreach (glob(__DIR__ . '/historial/*.json') ?: [] as $path) {
                $job = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                $result[] = array_intersect_key($job, array_flip(['id', 'fecha', 'nombre', 'cantidad']));
            }
            usort($result, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        }
        header('Content-Type: application/json; charset=utf-8'); echo json_encode($result, JSON_THROW_ON_ERROR); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $job = leerTrabajo((string) ($input['id'] ?? ''));
    $indices = $input['seleccion'] ?? [];
    if (!is_array($indices) || !$indices || count($indices) > $job['cantidad']) { throw new RuntimeException('Seleccioná al menos una etiqueta.'); }
    foreach ($indices as $index) {
        if (!is_int($index) || $index < 0 || $index >= $job['cantidad']) { throw new RuntimeException('Selección inválida.'); }
    }
    $indices = array_values(array_unique($indices)); sort($indices);
    if (($input['accion'] ?? '') === 'descargar') {
        $temp = tempnam(sys_get_temp_dir(), 'zpl_');
        try {
            $zip = new ZipArchive();
            if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('No se pudo crear el ZIP.'); }
            foreach ($indices as $index) { $zip->addFromString(sprintf('etiqueta_%05d.txt', $index + 1), $job['zpl'][$index]); }
            if (!$zip->close()) { throw new RuntimeException('No se pudo finalizar el ZIP.'); }
            header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="etiquetas.zip"');
            readfile($temp);
        } finally { unlink($temp); }
        exit;
    }
    if (($input['accion'] ?? '') !== 'imprimir') { throw new RuntimeException('Acción inválida.'); }
    $indices = array_values(array_intersect(ordenImpresion($job), $indices));
    if (count($indices) > 500) { throw new RuntimeException('Cada tanda admite hasta 500 registros.'); }
    $ip = $input['ip'] ?? '';
    if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { throw new RuntimeException('IP de impresora inválida.'); }
    $socket = @stream_socket_client('tcp://' . $ip . ':9100', $errno, $error, 5);
    if (!$socket) { throw new RuntimeException('No se pudo conectar a la impresora. El trabajo sigue guardado.'); }
    $sent = 0;
    try {
        stream_set_timeout($socket, 10);
        foreach ($indices as $index) {
            $label = $job['zpl'][$index]; $offset = 0;
            while ($offset < strlen($label)) {
                $written = @fwrite($socket, substr($label, $offset));
                if (!$written) { throw new RuntimeException('Envío incompleto en el registro ' . ($index + 1) . '. Revisá el equipo antes de reimprimir.'); }
                $offset += $written;
            }
            $sent++;
        }
    } finally {
        fclose($socket);
        file_put_contents(__DIR__ . '/historial/envios.log', json_encode(['fecha' => gmdate('c'), 'trabajo' => $job['id'], 'seleccion' => $indices, 'enviados' => $sent, 'ip' => $ip]) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['enviados' => $sent]);
} catch (Throwable $error) {
    http_response_code(400); header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $error instanceof RuntimeException ? $error->getMessage() : 'No se pudo procesar el trabajo.']);
}
