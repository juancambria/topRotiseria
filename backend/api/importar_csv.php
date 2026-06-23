<?php

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../services/CsvImportService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(
        array(
            'success' => false,
            'message' => 'Metodo no permitido'
        ),
        405
    );
}

if (!isset($_FILES['csv_file'])) {
    responderJson(
        array(
            'success' => false,
            'message' => 'Debes adjuntar un archivo CSV'
        ),
        422
    );
}

$archivo = $_FILES['csv_file'];

if (!isset($archivo['error']) || (int)$archivo['error'] !== UPLOAD_ERR_OK) {
    responderJson(
        array(
            'success' => false,
            'message' => 'Error al subir el archivo CSV'
        ),
        400
    );
}

$nombreArchivo = isset($archivo['name']) ? (string)$archivo['name'] : '';
$extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));

if ($extension !== 'csv') {
    responderJson(
        array(
            'success' => false,
            'message' => 'El archivo debe tener extension .csv'
        ),
        422
    );
}

try {
    $resultado = importarRecetasDesdeCsv($archivo['tmp_name']);

    responderJson(
        array(
            'success' => true,
            'message' => 'CSV importado correctamente',
            'data' => $resultado
        ),
        200
    );
} catch (Exception $e) {
    responderJson(
        array(
            'success' => false,
            'message' => 'Error en importacion CSV',
            'error' => $e->getMessage()
        ),
        500
    );
}
