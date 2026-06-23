<?php

/**
 * BLOQUE TEMPORAL DE IMPORTACION MASIVA (USO PUNTUAL)
 *
 * Formato esperado del CSV:
 * - Columna 1: codigo_plato
 * - Columna 2: codigo_ingrediente
 * - Columna 3: cantidad_utilizada_gr_cc_un
 *
 * Uso:
 * 1) Cambiar $EJECUTAR_IMPORTACION a true.
 * 2) Ajustar $RUTA_CSV al archivo real.
 * 3) Ejecutar: php backend/importar_recetas_csv.php
 * 4) Volver a dejar $EJECUTAR_IMPORTACION en false (o comentar el archivo).
 */

require_once __DIR__ . '/services/CsvImportService.php';

// ===================== CONFIG TEMPORAL =====================
$EJECUTAR_IMPORTACION = false;
$RUTA_CSV = __DIR__ . '/../database/import_recetas.csv';
// ===========================================================

if (php_sapi_name() !== 'cli') {
    echo "Este script es solo para ejecutar por CLI.\n";
    exit(1);
}

if (!$EJECUTAR_IMPORTACION) {
    echo "Importacion deshabilitada. Cambia \$EJECUTAR_IMPORTACION a true para ejecutar.\n";
    exit(0);
}

if (!file_exists($RUTA_CSV)) {
    echo "No existe el archivo CSV en: {$RUTA_CSV}\n";
    exit(1);
}

try {
    $resultado = importarRecetasDesdeCsv($RUTA_CSV);

    echo "Importacion completada.\n";
    echo "Platos procesados: " . $resultado['platos_procesados'] . "\n";
    echo "Ingredientes insertados: " . $resultado['ingredientes_insertados'] . "\n";
    echo "Filas validas CSV: " . $resultado['filas_validas'] . "\n";
    echo "Filas invalidas CSV: " . $resultado['filas_invalidas'] . "\n";
} catch (Exception $e) {
    echo "Error en importacion: " . $e->getMessage() . "\n";
    exit(1);
}
