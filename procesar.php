<?php
require 'src/Spout/Autoloader/autoload.php';

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $target_dir = "uploads/";
    $excel_file = $target_dir . basename($_FILES["archivo_excel"]["name"]);
    $zpl_file = $target_dir . basename($_FILES["codigo_zpl"]["name"]);

    // Subir archivos
    move_uploaded_file($_FILES["archivo_excel"]["tmp_name"], $excel_file);
    move_uploaded_file($_FILES["codigo_zpl"]["tmp_name"], $zpl_file);

    // Leer archivo Excel
    $reader = ReaderEntityFactory::createXLSXReader();
    $reader->open($excel_file);
    $dataArray = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rowData = [];
            foreach ($row->getCells() as $cell) {
                $rowData[] = $cell->getValue();
            }
            $dataArray[] = $rowData;
        }
    }
    $reader->close();

    // Variables a reemplazar en el código ZPL
    $variables = ['[socio]', '[nombre]', '[localidad]', '[codbar]', '[tipo]'];

    // Leer código ZPL base
    $codigo_zpl_base = file_get_contents($zpl_file);

    // Generar códigos ZPL modificados
    $codigos_zpl_modificados = [];
    foreach (array_slice($dataArray, 1) as $data) {
        $codigo_zpl_modificado = $codigo_zpl_base;
        foreach ($variables as $variable) {
            $indice = array_search($variable, $variables);
            $valor = $data[$indice];

            // Convertir DateTime a cadena de texto si es necesario
            if ($valor instanceof DateTime) {
                $valor = $valor->format('d/m/Y'); // Cambiar el formato a 'd/m/Y'
            }
            
            $codigo_zpl_modificado = str_replace($variable, $valor, $codigo_zpl_modificado);
        }

        // Nombre del archivo .txt será igual a [CODBAR]
        $codBar = $data[array_search('[codbar]', $variables)];
        $archivo_modificado = $target_dir . $codBar . ".txt";
        file_put_contents($archivo_modificado, $codigo_zpl_modificado);
        $codigos_zpl_modificados[] = $archivo_modificado;
    }

    // Comprimir archivos en un archivo ZIP
    $zip = new ZipArchive();
    $zip_file = "codigos_zpl_modificados.zip";
    if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
        foreach ($codigos_zpl_modificados as $archivo) {
            $zip->addFile($archivo, basename($archivo));
        }
        $zip->close();
    }

    // Descargar archivo ZIP
    header("Content-Type: application/zip");
    header("Content-Disposition: attachment; filename=$zip_file");
    header("Content-Length: " . filesize($zip_file));
    readfile($zip_file);

    // Eliminar archivos temporales
    unlink($excel_file);
    unlink($zpl_file);
    unlink($zip_file);
}
?>
