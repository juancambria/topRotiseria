<?php

// Resuelve el nombre visible del ingrediente priorizando descripcion.
function resolverNombreIngrediente($ingrediente)
{
    $descripcion = isset($ingrediente['descripcion']) ? trim($ingrediente['descripcion']) : '';
    $codigoCorto = isset($ingrediente['codigo_corto']) ? trim($ingrediente['codigo_corto']) : '';
    $codigo = isset($ingrediente['codigo']) ? trim($ingrediente['codigo']) : '';

    if ($descripcion !== '' && $descripcion !== '0') {
        return $descripcion;
    }

    if ($codigoCorto !== '' && $codigoCorto !== '0') {
        return $codigoCorto;
    }

    return $codigo;
}

// Define el aumento porcentual segun reglas de rubro/subrubro y codigo corto.
function obtenerPorcentajeAumentoIngrediente($ingrediente)
{
    $rubro = isset($ingrediente['rubro']) ? (int)$ingrediente['rubro'] : 0;
    $subRubro = isset($ingrediente['sub_rubro']) ? (int)$ingrediente['sub_rubro'] : 0;
    $codigoCorto = isset($ingrediente['codigo_corto']) ? trim($ingrediente['codigo_corto']) : '';

    if ($rubro === 8 && $subRubro === 13) {
        return 15;
    }

    if ($codigoCorto !== '' && $codigoCorto !== '0') {
        return 30;
    }

    return 50;
}

// Aplica un porcentaje de aumento sobre un precio base.
function aplicarAumentoPorcentual($precioBase, $porcentaje)
{
    $factor = 1 + ((float)$porcentaje / 100);
    return (float)$precioBase * $factor;
}

// Normaliza cantidades para tratar LT/KG como CC/GR dividiendo por 1000.
function normalizarCantidadPorUnidad($cantidad, $unidadMedida)
{
    $unidad = strtoupper(trim((string)$unidadMedida));
    $cantidadNumerica = (float)$cantidad;

    if ($unidad === 'LT' || $unidad === 'KG') {
        return $cantidadNumerica / 1000;
    }

    // Para UN se usa la cantidad tal cual: 1 significa 1 unidad (ej. 1 huevo).
    if ($unidad === 'UN') {
        return $cantidadNumerica;
    }

    return $cantidadNumerica;
}
