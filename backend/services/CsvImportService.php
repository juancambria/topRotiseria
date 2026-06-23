<?php

require_once __DIR__ . '/../config/conexion.php';

// Importa recetas desde CSV y devuelve métricas de procesamiento.
function importarRecetasDesdeCsv($rutaCsv)
{
    if (!file_exists($rutaCsv)) {
        throw new Exception('No existe el archivo CSV en: ' . $rutaCsv);
    }

    $delimitador = detectarDelimitadorCsv($rutaCsv);
    $handle = fopen($rutaCsv, 'r');

    if ($handle === false) {
        throw new Exception('No se pudo abrir el archivo CSV');
    }

    $lineaNumero = 0;
    $filasValidas = 0;
    $filasInvalidas = 0;
    $recetasPorPlato = array();

    while (($fila = fgetcsv($handle, 0, $delimitador)) !== false) {
        $lineaNumero++;

        if (!is_array($fila) || count($fila) < 3) {
            $filasInvalidas++;
            continue;
        }

        $codigoPlato = trim((string)$fila[0]);
        $codigoIngrediente = trim((string)$fila[1]);
        $cantidad = convertirANumeroCsv($fila[2]);

        if ($lineaNumero === 1 && !esCodigoProbableCsv($codigoPlato)) {
            continue;
        }

        if ($codigoPlato === '' || $codigoIngrediente === '' || $cantidad <= 0) {
            $filasInvalidas++;
            continue;
        }

        if (!isset($recetasPorPlato[$codigoPlato])) {
            $recetasPorPlato[$codigoPlato] = array();
        }

        if (!isset($recetasPorPlato[$codigoPlato][$codigoIngrediente])) {
            $recetasPorPlato[$codigoPlato][$codigoIngrediente] = 0;
        }

        $recetasPorPlato[$codigoPlato][$codigoIngrediente] += $cantidad;
        $filasValidas++;
    }

    fclose($handle);

    if ($filasValidas === 0) {
        throw new Exception('No se encontraron filas validas para importar');
    }

    $conexionLogin = obtenerConexionLogin();
    $conexionVentas = obtenerConexionVentas();
    mysqli_begin_transaction($conexionLogin);

    try {
        $stmtNombrePlato = mysqli_prepare(
            $conexionVentas,
            'SELECT descripcion FROM articulos WHERE codigo = ? LIMIT 1'
        );
        if (!$stmtNombrePlato) {
            throw new Exception('No se pudo preparar consulta de nombre de plato');
        }

        $stmtUpsertPlato = mysqli_prepare(
            $conexionLogin,
            'INSERT INTO platos (id, nombre, activo)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1'
        );
        if (!$stmtUpsertPlato) {
            throw new Exception('No se pudo preparar insercion de platos');
        }

        $stmtDeleteReceta = mysqli_prepare(
            $conexionLogin,
            'DELETE FROM receta_plato WHERE plato_id = ?'
        );
        if (!$stmtDeleteReceta) {
            throw new Exception('No se pudo preparar borrado de receta');
        }

        $stmtInsertReceta = mysqli_prepare(
            $conexionLogin,
            'INSERT INTO receta_plato (plato_id, ingrediente_codigo, cantidad_utilizada_gr_cc_un)
             VALUES (?, ?, ?)'
        );
        if (!$stmtInsertReceta) {
            throw new Exception('No se pudo preparar insercion de receta');
        }

        $platosProcesados = 0;
        $ingredientesInsertados = 0;

        foreach ($recetasPorPlato as $codigoPlato => $ingredientes) {
            $nombrePlato = obtenerNombrePlatoCsv($stmtNombrePlato, $codigoPlato);

            mysqli_stmt_bind_param($stmtUpsertPlato, 'ss', $codigoPlato, $nombrePlato);
            if (!mysqli_stmt_execute($stmtUpsertPlato)) {
                throw new Exception('Error guardando plato ' . $codigoPlato . ': ' . mysqli_stmt_error($stmtUpsertPlato));
            }

            mysqli_stmt_bind_param($stmtDeleteReceta, 's', $codigoPlato);
            if (!mysqli_stmt_execute($stmtDeleteReceta)) {
                throw new Exception('Error borrando receta de plato ' . $codigoPlato . ': ' . mysqli_stmt_error($stmtDeleteReceta));
            }

            foreach ($ingredientes as $codigoIngrediente => $cantidadTotal) {
                $cantidadDecimal = (float)$cantidadTotal;
                mysqli_stmt_bind_param($stmtInsertReceta, 'ssd', $codigoPlato, $codigoIngrediente, $cantidadDecimal);
                if (!mysqli_stmt_execute($stmtInsertReceta)) {
                    throw new Exception(
                        'Error guardando ingrediente ' . $codigoIngrediente .
                        ' para plato ' . $codigoPlato . ': ' . mysqli_stmt_error($stmtInsertReceta)
                    );
                }
                $ingredientesInsertados++;
            }

            $platosProcesados++;
        }

        mysqli_stmt_close($stmtNombrePlato);
        mysqli_stmt_close($stmtUpsertPlato);
        mysqli_stmt_close($stmtDeleteReceta);
        mysqli_stmt_close($stmtInsertReceta);

        mysqli_commit($conexionLogin);

        return array(
            'platos_procesados' => $platosProcesados,
            'ingredientes_insertados' => $ingredientesInsertados,
            'filas_validas' => $filasValidas,
            'filas_invalidas' => $filasInvalidas
        );
    } catch (Exception $e) {
        mysqli_rollback($conexionLogin);
        throw $e;
    }
}

// Detecta delimitador probable del CSV.
function detectarDelimitadorCsv($rutaCsv)
{
    $primerLinea = '';
    $fp = fopen($rutaCsv, 'r');
    if ($fp !== false) {
        $primerLinea = fgets($fp);
        fclose($fp);
    }

    if (!is_string($primerLinea)) {
        return ';';
    }

    $candidatos = array(';', ',', "\t", '|');
    $mejor = ';';
    $max = -1;

    foreach ($candidatos as $delim) {
        $partes = explode($delim, $primerLinea);
        $cantidad = count($partes);
        if ($cantidad > $max) {
            $max = $cantidad;
            $mejor = $delim;
        }
    }

    return $mejor;
}

// Convierte textos numéricos con coma o punto decimal.
function convertirANumeroCsv($valor)
{
    $texto = trim((string)$valor);
    $texto = str_replace('.', '', $texto);
    $texto = str_replace(',', '.', $texto);
    return (float)$texto;
}

// Detecta si el dato parece código y no cabecera.
function esCodigoProbableCsv($codigo)
{
    return preg_match('/^[0-9A-Za-z_-]+$/', (string)$codigo) === 1;
}

// Obtiene nombre de plato desde ventas.articulos.descripcion.
function obtenerNombrePlatoCsv($stmtNombrePlato, $codigoPlato)
{
    mysqli_stmt_bind_param($stmtNombrePlato, 's', $codigoPlato);
    mysqli_stmt_execute($stmtNombrePlato);
    $resultado = mysqli_stmt_get_result($stmtNombrePlato);
    $fila = mysqli_fetch_assoc($resultado);

    if (!$fila || !isset($fila['descripcion']) || trim($fila['descripcion']) === '') {
        return $codigoPlato;
    }

    return trim($fila['descripcion']);
}
