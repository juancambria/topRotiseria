<?php

define('DB_HOST', '192.168.10.204');
define('DB_USER', 'soporte');
define('DB_PASS', 'soportedesarrollo975');
define('DB_LOGIN', 'login');
define('DB_VENTAS', 'ventas');

// Crea una conexion mysqli para la base indicada y corta el flujo si falla.
function conectarBaseDatos($database)
{
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF);
    }

    $conexion = false;

    try {
        $conexion = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, $database);
    } catch (Exception $errorConexion) {
        responderJson(
            array(
                'success' => false,
                'message' => 'No se pudo conectar con la base de datos ' . $database,
                'error' => $errorConexion->getMessage()
            ),
            500
        );
    }

    if (!$conexion) {
        $detalleError = mysqli_connect_error();

        if (!$detalleError) {
            $detalleError = 'Error de conexion no especificado';
        }

        responderJson(
            array(
                'success' => false,
                'message' => 'No se pudo conectar con la base de datos ' . $database,
                'error' => $detalleError
            ),
            500
        );
    }

    mysqli_set_charset($conexion, 'utf8');

    return $conexion;
}

// Reutiliza una unica conexion para operar sobre la base login.
function obtenerConexionLogin()
{
    static $loginConnection = null;

    if ($loginConnection === null) {
        $loginConnection = conectarBaseDatos(DB_LOGIN);
    }

    return $loginConnection;
}

// Reutiliza una unica conexion para consultar ingredientes en la base ventas.
function obtenerConexionVentas()
{
    static $ventasConnection = null;

    if ($ventasConnection === null) {
        $ventasConnection = conectarBaseDatos(DB_VENTAS);
    }

    return $ventasConnection;
}

// Lee el body JSON de la request y valida que tenga una estructura correcta.
function leerEntradaJson()
{
    $rawInput = file_get_contents('php://input');

    if (!$rawInput) {
        return array();
    }

    $decoded = json_decode($rawInput, true);

    if (!is_array($decoded)) {
        responderJson(
            array(
                'success' => false,
                'message' => 'JSON invalido'
            ),
            400
        );
    }

    return $decoded;
}

// Devuelve una respuesta JSON con codigo HTTP y finaliza la ejecucion.
function responderJson($payload, $statusCode)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}