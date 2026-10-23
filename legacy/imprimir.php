<?php
$files = glob('uploads/*.txt');
$files = array_reverse($files); //para imprimir del ultimo al primero

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $printerIP = $_POST['printerIP']; // IP de la impresora Zebra
    $printerPort = 9100;

    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        die("No se pudo crear el socket: " . socket_strerror(socket_last_error()));
    }

    $result = socket_connect($socket, $printerIP, $printerPort);
    if ($result === false) {
        die('<br><br>No se pudo conectar a la impresora Zebra: ' . socket_strerror(socket_last_error($socket)) . '<br><br><a href="index.html"><button>Volver</button></a>');
    }
    foreach ($files as $file){
        $selected_file = $file;
        $codigo_zpl = file_get_contents($selected_file);
        socket_write($socket, $codigo_zpl, strlen($codigo_zpl));
    }
    socket_close($socket);
    
    if($files){
        echo "Archivo enviado e impreso correctamente.";
    } else {
        echo "No se encontraron archivos para imprimir.";
    }
}
echo '<br><br><a href="index.html"><button>Volver</button></a>';

foreach ($files as $file) {
    unlink($file);
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Archivos</title>

