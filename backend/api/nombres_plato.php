<?php

require_once __DIR__ . '/../models/PlatoModel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(
        array(
            'success' => false,
            'message' => 'Metodo no permitido'
        ),
        405
    );
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($query === '') {
    responderJson(
        array(
            'success' => true,
            'data' => array()
        ),
        200
    );
}

responderJson(
    array(
        'success' => true,
        'data' => buscarNombresPlato($query)
    ),
    200
);
