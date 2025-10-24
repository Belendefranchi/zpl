<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $file = 'Plantilla.xlsx';

    if (file_exists($file) && is_readable($file)) {
      header('Content-Description: File Transfer');
      header('Content-Type: application/vnd.ms-excel');
      header('Content-Disposition: attachment; filename="' . basename($file) . '"');
      header('Expires: 0');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      header('Content-Length: ' . filesize($file));
      readfile($file);
    } else {
      echo 'No se puede acceder al archivo.';
    }
  }
