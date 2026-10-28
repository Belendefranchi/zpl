<?php
declare(strict_types=1);
function ordenImpresion(array $job): array {
    $column = null;
    foreach (['CODBAR', 'CANAL', 'BARRAS', 'CODIGO', 'ALTERNATIVO'] as $candidate) {
        foreach ($job['variables'] as $index => $variable) {
            if (strtoupper($variable) === $candidate) { $column = $index; break 2; }
        }
    }
    $indices = range(0, $job['cantidad'] - 1);
    usort($indices, function ($a, $b) use ($job, $column) {
        $nameA = $column === null ? sprintf('etiqueta_%05d.txt', $a + 1) : $job['filas'][$a][$column] . '.txt';
        $nameB = $column === null ? sprintf('etiqueta_%05d.txt', $b + 1) : $job['filas'][$b][$column] . '.txt';
        return strcmp($nameB, $nameA) ?: $b <=> $a;
    });
    return $indices;
}
function leerTrabajo(string $id): array {
    if (!preg_match('/^[a-f0-9]{32}$/D', $id)) { throw new RuntimeException('Trabajo inválido.'); }
    $path = __DIR__ . '/historial/' . $id . '.json';
    if (!is_file($path)) { throw new RuntimeException('No se encontró el trabajo.'); }
    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}
function guardarTrabajo(array $template, array $rows, array $labels): string {
    $id = bin2hex(random_bytes(16));
    $job = ['id' => $id, 'fecha' => gmdate('c'), 'nombre' => $template['nombre'], 'variables' => $template['variables'], 'filas' => $rows, 'zpl' => $labels, 'cantidad' => count($labels)];
    $path = __DIR__ . '/historial/' . $id . '.json';
    $json = json_encode($job, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) { @unlink($path); throw new RuntimeException('No se pudo guardar el trabajo. No se envió a imprimir.'); }
    return $id;
}
