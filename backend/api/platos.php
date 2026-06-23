<?php

require_once __DIR__ . '/../models/PlatoModel.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $platoId = obtenerIdPlatoDesdeRequest();
        $plato = obtenerPlatoPorId($platoId);

        if ($plato === null) {
            responderJson(
                array(
                    'success' => false,
                    'message' => 'Plato no encontrado'
                ),
                404
            );
        }

        responderJson(
            array(
                'success' => true,
                'data' => $plato
            ),
            200
        );
    }

    responderJson(
        array(
            'success' => true,
            'data' => obtenerPlatos(obtenerOrdenListadoDesdeRequest())
        ),
        200
    );
}

if ($method === 'POST') {
    $body = leerEntradaJson();
    $nombre = isset($body['nombre']) ? trim($body['nombre']) : '';
    $codigoPlato = isset($body['codigo_plato']) ? trim($body['codigo_plato']) : '';
    $receta = isset($body['receta']) && is_array($body['receta']) ? $body['receta'] : array();

    validarEntradaPlato($nombre, $receta, $codigoPlato, true);
    $plato = crearPlato($nombre, sanitizarReceta($receta), $codigoPlato);

    responderJson(
        array(
            'success' => true,
            'message' => 'Plato creado correctamente',
            'data' => $plato
        ),
        201
    );
}

if ($method === 'PUT') {
    $id = obtenerIdPlatoDesdeRequest();
    $body = leerEntradaJson();
    $nombre = isset($body['nombre']) ? trim($body['nombre']) : '';
    $codigoPlato = isset($body['codigo_plato']) ? trim($body['codigo_plato']) : '';
    $receta = isset($body['receta']) && is_array($body['receta']) ? $body['receta'] : array();

    validarEntradaPlato($nombre, $receta, $codigoPlato, false);
    $plato = actualizarPlato($id, $nombre, sanitizarReceta($receta), $codigoPlato);

    responderJson(
        array(
            'success' => true,
            'message' => 'Plato actualizado correctamente',
            'data' => $plato
        ),
        200
    );
}

if ($method === 'DELETE') {
    $platoId = obtenerIdPlatoDesdeRequest();
    eliminarPlato($platoId);
    responderJson(
        array(
            'success' => true,
            'message' => 'Plato eliminado'
        ),
        200
    );
}

responderJson(
    array(
        'success' => false,
        'message' => 'Metodo no permitido'
    ),
    405
);

// Valida los datos base del plato antes de intentar guardarlo en la base.
function validarEntradaPlato($nombre, $receta, $codigoPlato, $esCreacion)
{
    if ($nombre === '') {
        responderJson(
            array(
                'success' => false,
                'message' => 'El nombre del plato es obligatorio'
            ),
            422
        );
    }

    if (strlen($nombre) > 150) {
        responderJson(
            array(
                'success' => false,
                'message' => 'El nombre del plato es demasiado largo'
            ),
            422
        );
    }

    if (count($receta) === 0) {
        responderJson(
            array(
                'success' => false,
                'message' => 'Debes cargar al menos un ingrediente'
            ),
            422
        );
    }

    if ($esCreacion && $codigoPlato === '') {
        responderJson(
            array(
                'success' => false,
                'message' => 'Debes seleccionar un nombre de plato desde la lista sugerida'
            ),
            422
        );
    }
}

// Obtiene y valida el id del plato enviado por query string.
function obtenerIdPlatoDesdeRequest()
{
    if (!isset($_GET['id'])) {
        responderJson(
            array(
                'success' => false,
                'message' => 'Falta el id del plato'
            ),
            400
        );
    }

    $id = trim((string)$_GET['id']);

    if ($id === '') {
        responderJson(
            array(
                'success' => false,
                'message' => 'El id del plato es invalido'
            ),
            422
        );
    }

    return $id;
}

// Obtiene el criterio de orden enviado por query string.
function obtenerOrdenListadoDesdeRequest()
{
    if (!isset($_GET['ordenar'])) {
        return '';
    }

    return trim((string)$_GET['ordenar']);
}

// Limpia la receta para evitar codigos vacios, cantidades invalidas o repetidas.
function sanitizarReceta($receta)
{
    $sanitizada = array();
    $codigos = array();

    foreach ($receta as $item) {
        $codigo = isset($item['codigo']) ? trim($item['codigo']) : '';
        $cantidad = isset($item['cantidad']) ? (float)$item['cantidad'] : 0;

        if ($codigo === '' || $cantidad <= 0) {
            continue;
        }

        if (in_array($codigo, $codigos, true)) {
            continue;
        }

        $codigos[] = $codigo;
        $sanitizada[] = array(
            'codigo' => $codigo,
            'cantidad' => $cantidad
        );
    }

    if (count($sanitizada) === 0) {
        responderJson(
            array(
                'success' => false,
                'message' => 'La receta no tiene ingredientes validos'
            ),
            422
        );
    }

    return $sanitizada;
}
