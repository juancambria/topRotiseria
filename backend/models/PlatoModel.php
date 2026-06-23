<?php

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/helpers/IngredienteCalculoHelper.php';

// Obtiene todos los platos activos, calcula costos/margen y aplica el orden solicitado.
function obtenerPlatos($ordenarPor = '')
{
    asegurarTablasLogin();
    $conexion = obtenerConexionLogin();
    $sql = 'SELECT id, nombre, fecha_creacion FROM platos WHERE activo = 1 ORDER BY nombre ASC';
    $resultado = mysqli_query($conexion, $sql);
    $platos = array();
    $codigosPlato = array();

    if (!$resultado) {
        responderJson(
            array(
                'success' => false,
                'message' => 'No se pudieron cargar los platos'
            ),
            500
        );
    }

    while ($fila = mysqli_fetch_assoc($resultado)) {
        $detalle = obtenerRecetaConCosto($fila['id']);

        $platos[] = array(
            'id' => $fila['id'],
            'nombre' => $fila['nombre'],
            'fecha_creacion' => $fila['fecha_creacion'],
            'cantidad_ingredientes' => count($detalle['receta']),
            'costo_total' => $detalle['costo_total'],
            'costo_total_con_sobrecarga' => $detalle['costo_total_con_sobrecarga'],
            'costo_total_sin_sobrecarga' => $detalle['costo_total_sin_sobrecarga']
        );

        $codigosPlato[] = $fila['id'];
    }

    $preciosVenta = obtenerPreciosVentaPorCodigos($codigosPlato);

    foreach ($platos as $indice => $plato) {
        $codigo = $plato['id'];
        $precioVenta = isset($preciosVenta[$codigo]) ? (float)$preciosVenta[$codigo] : 0;
        $costoTotalConSobrecarga = (float)$plato['costo_total_con_sobrecarga'];
        $costoTotalSinSobrecarga = (float)$plato['costo_total_sin_sobrecarga'];
        $platos[$indice]['precio_venta'] = round($precioVenta, 2);
        $platos[$indice]['margen'] = calcularMargenPorcentaje($precioVenta, $costoTotalSinSobrecarga);
        $platos[$indice]['margen_con_sobrecarga'] = calcularMargenPorcentaje($precioVenta, $costoTotalConSobrecarga);
        $platos[$indice]['margen_sin_sobrecarga'] = $platos[$indice]['margen'];
    }

    ordenarPlatosSegunCriterio($platos, $ordenarPor);
    return $platos;
}

// Busca un plato por id y adjunta su receta con costos calculados.
function obtenerPlatoPorId($id)
{
    asegurarTablasLogin();
    $conexion = obtenerConexionLogin();
    $stmt = mysqli_prepare($conexion, 'SELECT id, nombre, fecha_creacion FROM platos WHERE id = ? AND activo = 1 LIMIT 1');

    if (!$stmt) {
        responderJson(array('success' => false, 'message' => 'Error interno al buscar plato'), 500);
    }

    mysqli_stmt_bind_param($stmt, 's', $id);
    mysqli_stmt_execute($stmt);
    $plato = obtenerFilaStmt($stmt);
    mysqli_stmt_close($stmt);

    if (!$plato) {
        return null;
    }

    $detalleReceta = obtenerRecetaConCosto($id);
    $precioVenta = obtenerPrecioVentaPorCodigo($id);
    $margenConSobrecarga = calcularMargenPorcentaje((float)$precioVenta, (float)$detalleReceta['costo_total_con_sobrecarga']);
    $margenSinSobrecarga = calcularMargenPorcentaje((float)$precioVenta, (float)$detalleReceta['costo_total_sin_sobrecarga']);

    return array(
        'id' => $plato['id'],
        'nombre' => $plato['nombre'],
        'fecha_creacion' => $plato['fecha_creacion'],
        'receta' => $detalleReceta['receta'],
        'costo_total' => $detalleReceta['costo_total'],
        'costo_total_con_sobrecarga' => $detalleReceta['costo_total_con_sobrecarga'],
        'costo_total_sin_sobrecarga' => $detalleReceta['costo_total_sin_sobrecarga'],
        'precio_venta' => round((float)$precioVenta, 2),
        'margen' => $margenSinSobrecarga,
        'margen_con_sobrecarga' => $margenConSobrecarga,
        'margen_sin_sobrecarga' => $margenSinSobrecarga
    );
}

// Crea un plato nuevo usando como id el codigo del plato seleccionado.
function crearPlato($nombre, $receta, $codigoPlato)
{
    asegurarTablasLogin();
    $conexion = obtenerConexionLogin();
    mysqli_begin_transaction($conexion);

    try {
        $idPlato = trim((string)$codigoPlato);
        $stmt = mysqli_prepare($conexion, 'INSERT INTO platos (id, nombre, activo) VALUES (?, ?, 1)');
        if (!$stmt) {
            throw new Exception('No se pudo crear el plato');
        }

        mysqli_stmt_bind_param($stmt, 'ss', $idPlato, $nombre);
        $ejecucion = mysqli_stmt_execute($stmt);
        if (!$ejecucion) {
            throw new Exception('No se pudo crear el plato. Verifica que el codigo no exista.');
        }
        mysqli_stmt_close($stmt);

        guardarReceta($idPlato, $receta);
        mysqli_commit($conexion);

        return obtenerPlatoPorId($idPlato);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        responderJson(
            array(
                'success' => false,
                'message' => $e->getMessage()
            ),
            500
        );
    }
}

// Actualiza el nombre del plato y reemplaza completamente su receta.
function actualizarPlato($id, $nombre, $receta, $codigoPlato)
{
    asegurarTablasLogin();
    $conexion = obtenerConexionLogin();
    mysqli_begin_transaction($conexion);

    try {
        $stmt = mysqli_prepare($conexion, 'UPDATE platos SET nombre = ? WHERE id = ?');
        if (!$stmt) {
            throw new Exception('No se pudo actualizar el plato');
        }

        mysqli_stmt_bind_param($stmt, 'ss', $nombre, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $deleteStmt = mysqli_prepare($conexion, 'DELETE FROM receta_plato WHERE plato_id = ?');
        if (!$deleteStmt) {
            throw new Exception('No se pudo actualizar la receta');
        }

        mysqli_stmt_bind_param($deleteStmt, 's', $id);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        guardarReceta($id, $receta);
        mysqli_commit($conexion);

        return obtenerPlatoPorId($id);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        responderJson(
            array(
                'success' => false,
                'message' => $e->getMessage()
            ),
            500
        );
    }
}

// Inserta cada ingrediente de la receta con su cantidad utilizada.
function guardarReceta($platoId, $receta)
{
    asegurarTablasLogin();
    $conexion = obtenerConexionLogin();

    if (count($receta) === 0) {
        return;
    }

    $stmt = mysqli_prepare(
        $conexion,
        'INSERT INTO receta_plato (plato_id, ingrediente_codigo, cantidad_utilizada_gr_cc_un) VALUES (?, ?, ?)'
    );

    if (!$stmt) {
        throw new Exception('No se pudo guardar ingredientes de la receta');
    }

    foreach ($receta as $item) {
        $codigo = $item['codigo'];
        $cantidad = (float)$item['cantidad'];
        mysqli_stmt_bind_param($stmt, 'ssd', $platoId, $codigo, $cantidad);
        mysqli_stmt_execute($stmt);
    }

    mysqli_stmt_close($stmt);
}

// Elimina el plato de forma fisica y por cascada su receta asociada.
function eliminarPlato($id)
{
    asegurarTablasLogin();
    $conexion = obtenerConexionLogin();
    mysqli_begin_transaction($conexion);

    try {
        $stmtReceta = mysqli_prepare($conexion, 'DELETE FROM receta_plato WHERE plato_id = ?');
        if (!$stmtReceta) {
            throw new Exception('No se pudo eliminar la receta del plato');
        }

        mysqli_stmt_bind_param($stmtReceta, 's', $id);
        mysqli_stmt_execute($stmtReceta);
        mysqli_stmt_close($stmtReceta);

        $stmtPlato = mysqli_prepare($conexion, 'DELETE FROM platos WHERE id = ?');
        if (!$stmtPlato) {
            throw new Exception('No se pudo eliminar el plato');
        }

        mysqli_stmt_bind_param($stmtPlato, 's', $id);
        mysqli_stmt_execute($stmtPlato);
        mysqli_stmt_close($stmtPlato);

        mysqli_commit($conexion);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        responderJson(
            array(
                'success' => false,
                'message' => $e->getMessage()
            ),
            500
        );
    }
}

// Carga la receta del plato y calcula costo unitario e importe por ingrediente.
function obtenerRecetaConCosto($platoId)
{
    asegurarTablasLogin();
    $conexion = obtenerConexionLogin();
    $stmt = mysqli_prepare(
        $conexion,
        'SELECT ingrediente_codigo, cantidad_utilizada_gr_cc_un FROM receta_plato WHERE plato_id = ? ORDER BY id ASC'
    );

    if (!$stmt) {
        responderJson(array('success' => false, 'message' => 'No se pudo cargar la receta'), 500);
    }

    mysqli_stmt_bind_param($stmt, 's', $platoId);
    mysqli_stmt_execute($stmt);
    $filasReceta = obtenerFilasStmt($stmt);

    $recetaBase = array();
    $codigos = array();

    foreach ($filasReceta as $fila) {
        $codigo = $fila['ingrediente_codigo'];
        $cantidad = (float)$fila['cantidad_utilizada_gr_cc_un'];

        $recetaBase[] = array(
            'codigo' => $codigo,
            'cantidad' => $cantidad
        );

        $codigos[] = $codigo;
    }

    mysqli_stmt_close($stmt);

    $ingredientes = obtenerIngredientesPorCodigos($codigos);
    $receta = array();
    $costoTotalConSobrecarga = 0;
    $costoTotalSinSobrecarga = 0;

    foreach ($recetaBase as $item) {
        $codigo = $item['codigo'];
        $ingrediente = isset($ingredientes[$codigo]) ? $ingredientes[$codigo] : null;

        if ($ingrediente === null) {
            continue;
        }

        $cantidadUtilizada = (float)$item['cantidad'];
        $precioBase = (float)$ingrediente['precio'];
        $porcentajeAumento = obtenerPorcentajeAumentoIngrediente($ingrediente);
        $precioConSobrecarga = aplicarAumentoPorcentual($precioBase, $porcentajeAumento);
        $cantidadMedida = (float)$ingrediente['cantidad_medida'];
        $cantidadNormalizada = normalizarCantidadPorUnidad($cantidadUtilizada, $ingrediente['unidad_medida']);
        $costoUnitarioBase = $cantidadMedida > 0 ? $precioBase / $cantidadMedida : 0;
        $costoUnitarioConSobrecarga = $cantidadMedida > 0 ? $precioConSobrecarga / $cantidadMedida : 0;
        $costoIngredienteSinSobrecarga = $cantidadNormalizada * $costoUnitarioBase;
        $costoIngredienteConSobrecarga = $cantidadNormalizada * $costoUnitarioConSobrecarga;
        $costoTotalSinSobrecarga += $costoIngredienteSinSobrecarga;
        $costoTotalConSobrecarga += $costoIngredienteConSobrecarga;

        $receta[] = array(
            'codigo' => $codigo,
            'codigo_corto' => $ingrediente['codigo_corto'],
            'descripcion' => $ingrediente['descripcion'],
            'nombre' => resolverNombreIngrediente($ingrediente),
            'unidad_medida' => $ingrediente['unidad_medida'],
            'cantidad_utilizada_gr_cc_un' => $cantidadUtilizada,
            'cantidad_utilizada' => $cantidadUtilizada,
            'cantidad_normalizada' => $cantidadNormalizada,
            'precio_base' => round($precioBase, 2),
            'porcentaje_aumento' => $porcentajeAumento,
            'precio' => round($precioConSobrecarga, 2),
            'cantidad_medida' => $cantidadMedida,
            'costo_ingrediente' => round($costoIngredienteConSobrecarga, 2),
            'costo_ingrediente_con_sobrecarga' => round($costoIngredienteConSobrecarga, 2),
            'costo_ingrediente_sin_sobrecarga' => round($costoIngredienteSinSobrecarga, 2)
        );
    }

    return array(
        'receta' => $receta,
        'costo_total' => round($costoTotalConSobrecarga, 2),
        'costo_total_con_sobrecarga' => round($costoTotalConSobrecarga, 2),
        'costo_total_sin_sobrecarga' => round($costoTotalSinSobrecarga, 2)
    );
}

// Busca ingredientes por codigo o nombre corto en la base de ventas.
function buscarIngredientes($search)
{
    $conexion = obtenerConexionVentas();
    $term = '%' . $search . '%';

    $stmt = mysqli_prepare(
        $conexion,
        'SELECT codigo, codigo_corto, descripcion, precio, unidad_medida, cantidad_medida
         FROM articulos
         WHERE habilitado = 1
           AND (codigo LIKE ? OR codigo_corto LIKE ? OR descripcion LIKE ?)
         ORDER BY descripcion ASC
         LIMIT 25'
    );

    if (!$stmt) {
        responderJson(array('success' => false, 'message' => 'No se pudieron buscar ingredientes'), 500);
    }

    mysqli_stmt_bind_param($stmt, 'sss', $term, $term, $term);
    mysqli_stmt_execute($stmt);
    $filasIngredientes = obtenerFilasStmt($stmt);
    $ingredientes = array();

    foreach ($filasIngredientes as $fila) {
        $ingredientes[] = array(
            'codigo' => $fila['codigo'],
            'codigo_corto' => $fila['codigo_corto'],
            'descripcion' => $fila['descripcion'],
            'nombre' => resolverNombreIngrediente($fila),
            'precio' => (float)$fila['precio'],
            'unidad_medida' => $fila['unidad_medida'],
            'cantidad_medida' => (float)$fila['cantidad_medida']
        );
    }

    mysqli_stmt_close($stmt);

    return $ingredientes;
}

// Busca posibles nombres de plato desde articulos filtrando por rubro/subrubro habilitados.
function buscarNombresPlato($search)
{
    $conexion = obtenerConexionVentas();
    $term = '%' . $search . '%';
    $consulta = obtenerConsultaNombresPlatoCompatible($conexion);
    $stmt = mysqli_prepare($conexion, $consulta);

    if (!$stmt) {
        responderJson(
            array(
                'success' => false,
                'message' => 'No se pudieron buscar nombres de plato',
                'error' => mysqli_error($conexion)
            ),
            500
        );
    }

    mysqli_stmt_bind_param($stmt, 'sss', $term, $term, $term);
    $ejecucionOk = mysqli_stmt_execute($stmt);

    if (!$ejecucionOk) {
        responderJson(
            array(
                'success' => false,
                'message' => 'No se pudieron buscar nombres de plato',
                'error' => mysqli_stmt_error($stmt)
            ),
            500
        );
    }

    $filasNombres = obtenerFilasStmt($stmt);
    $nombres = array();

    foreach ($filasNombres as $fila) {
        $descripcion = isset($fila['descripcion']) ? trim($fila['descripcion']) : '';

        if ($descripcion === '') {
            continue;
        }

        $nombres[] = array(
            'codigo' => $fila['codigo'],
            'nombre' => $descripcion
        );
    }

    mysqli_stmt_close($stmt);

    return $nombres;
}

// Obtiene el precio de venta de cada plato desde ventas.articulos usando su codigo.
function obtenerPreciosVentaPorCodigos($codigos)
{
    $mapa = array();

    if (count($codigos) === 0) {
        return $mapa;
    }

    $codigosUnicos = array_values(array_unique($codigos));
    $placeholders = implode(',', array_fill(0, count($codigosUnicos), '?'));
    $tipos = str_repeat('s', count($codigosUnicos));
    $params = array_merge(array($tipos), $codigosUnicos);

    $conexion = obtenerConexionVentas();
    $sql = 'SELECT codigo, precio FROM articulos WHERE codigo IN (' . $placeholders . ')';
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        return $mapa;
    }

    call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt), obtenerReferencias($params)));
    mysqli_stmt_execute($stmt);
    $filasPrecios = obtenerFilasStmt($stmt);

    foreach ($filasPrecios as $fila) {
        $mapa[$fila['codigo']] = (float)$fila['precio'];
    }

    mysqli_stmt_close($stmt);
    return $mapa;
}

// Obtiene el precio de venta individual para un codigo de plato.
function obtenerPrecioVentaPorCodigo($codigoPlato)
{
    $mapa = obtenerPreciosVentaPorCodigos(array($codigoPlato));
    return isset($mapa[$codigoPlato]) ? $mapa[$codigoPlato] : 0;
}

// Ordena el arreglo de platos segun criterio seleccionado en pantalla.
function ordenarPlatosSegunCriterio(&$platos, $criterio)
{
    if (!is_array($platos) || count($platos) <= 1) {
        return;
    }

    usort($platos, function ($a, $b) use ($criterio) {
        if ($criterio === 'precio_venta_alto') {
            if ($a['precio_venta'] == $b['precio_venta']) {
                return strcasecmp($a['nombre'], $b['nombre']);
            }
            return ($a['precio_venta'] < $b['precio_venta']) ? 1 : -1;
        }

        if ($criterio === 'precio_venta_bajo') {
            if ($a['precio_venta'] == $b['precio_venta']) {
                return strcasecmp($a['nombre'], $b['nombre']);
            }
            return ($a['precio_venta'] > $b['precio_venta']) ? 1 : -1;
        }

        if ($criterio === 'margen_alto') {
            if ($a['margen'] == $b['margen']) {
                return strcasecmp($a['nombre'], $b['nombre']);
            }
            return ($a['margen'] < $b['margen']) ? 1 : -1;
        }

        if ($criterio === 'margen_bajo') {
            if ($a['margen'] == $b['margen']) {
                return strcasecmp($a['nombre'], $b['nombre']);
            }
            return ($a['margen'] > $b['margen']) ? 1 : -1;
        }

        return strcasecmp($a['nombre'], $b['nombre']);
    });
}

// Calcula margen como porcentaje de ganancia sobre costo de preparacion.
function calcularMargenPorcentaje($precioVenta, $costoTotal)
{
    $precio = (float)$precioVenta;
    $costo = (float)$costoTotal;

    if ($costo <= 0) {
        if ($precio > 0) {
            return 100;
        }

        return 0;
    }

    $margenPorcentaje = (($precio - $costo) / $costo) * 100;
    return round($margenPorcentaje, 2);
}

// Devuelve una consulta compatible con distintas variantes de columnas rubro/subrubro.
function obtenerConsultaNombresPlatoCompatible($conexion)
{
    $columnas = obtenerColumnasTablaArticulos($conexion);
    $columnaRubro = buscarPrimeraColumnaDisponible(
        $columnas,
        array('rubro', 'rubro_id', 'cod_rubro', 'id_rubro', 'idrubro')
    );
    $columnaSubrubro = buscarPrimeraColumnaDisponible(
        $columnas,
        array('sub_rubro', 'subrubro', 'subrubro_id', 'cod_subrubro', 'id_subrubro', 'idsubrubro')
    );

    // Si no encuentra un nombre exacto, busca por patron para compatibilidad con esquemas legacy.
    if ($columnaRubro === null) {
        $columnaRubro = buscarColumnaPorPatron($columnas, array('rubro'), array('sub', 'sucursales'));
    }

    if ($columnaSubrubro === null) {
        $columnaSubrubro = buscarColumnaPorPatron($columnas, array('sub', 'rubro'), array('sucursales'));
    }

    if ($columnaRubro === null || $columnaSubrubro === null) {
        responderJson(
            array(
                'success' => false,
                'message' => 'No se pudo filtrar nombres de plato: faltan columnas rubro/subrubro en ventas.articulos',
                'columnas_detectadas' => $columnas
            ),
            500
        );
    }

    return 'SELECT codigo, descripcion
            FROM articulos
            WHERE habilitado = 1
              AND `' . $columnaRubro . '` = 8
              AND `' . $columnaSubrubro . '` = 35
              AND (descripcion LIKE ? OR codigo LIKE ? OR codigo_corto LIKE ?)
            ORDER BY descripcion ASC
            LIMIT 25';
}

// Obtiene el listado real de columnas de ventas.articulos para armar consultas compatibles.
function obtenerColumnasTablaArticulos($conexion)
{
    $resultado = mysqli_query($conexion, 'SHOW COLUMNS FROM articulos');

    if (!$resultado) {
        responderJson(
            array(
                'success' => false,
                'message' => 'No se pudieron leer columnas de ventas.articulos',
                'error' => mysqli_error($conexion)
            ),
            500
        );
    }

    $columnas = array();

    while ($fila = mysqli_fetch_assoc($resultado)) {
        if (isset($fila['Field'])) {
            $columnas[] = strtolower(trim($fila['Field']));
        }
    }

    return $columnas;
}

// Busca la primera columna candidata que realmente exista en la tabla.
function buscarPrimeraColumnaDisponible($columnasDisponibles, $columnasCandidatas)
{
    foreach ($columnasCandidatas as $columna) {
        if (in_array(strtolower($columna), $columnasDisponibles, true)) {
            return $columna;
        }
    }

    return null;
}

// Busca una columna por texto contenido para soportar nombres no estandar.
function buscarColumnaPorPatron($columnasDisponibles, $textosObligatorios, $textosProhibidos)
{
    if (!is_array($textosObligatorios)) {
        $textosObligatorios = array($textosObligatorios);
    }

    foreach ($columnasDisponibles as $columna) {
        $columnaNormalizada = strtolower($columna);

        $cumpleObligatorios = true;
        foreach ($textosObligatorios as $obligatorio) {
            if (strpos($columnaNormalizada, strtolower($obligatorio)) === false) {
                $cumpleObligatorios = false;
                break;
            }
        }

        if (!$cumpleObligatorios) {
            continue;
        }

        $bloqueada = false;

        foreach ($textosProhibidos as $prohibido) {
            if (strpos($columnaNormalizada, strtolower($prohibido)) !== false) {
                $bloqueada = true;
                break;
            }
        }

        if ($bloqueada) {
            continue;
        }

        return $columna;
    }

    return null;
}

// Obtiene un mapa de ingredientes por codigo para acelerar el calculo de costos.
function obtenerIngredientesPorCodigos($codigos)
{
    $mapa = array();

    if (count($codigos) === 0) {
        return $mapa;
    }

    $codigosUnicos = array_values(array_unique($codigos));
    $placeholders = implode(',', array_fill(0, count($codigosUnicos), '?'));
    $tipos = str_repeat('s', count($codigosUnicos));
    $params = array_merge(array($tipos), $codigosUnicos);

    $conexion = obtenerConexionVentas();
    $sql = 'SELECT codigo, codigo_corto, descripcion, precio, unidad_medida, cantidad_medida, rubro, sub_rubro FROM articulos WHERE codigo IN (' . $placeholders . ')';
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        return $mapa;
    }

    call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt), obtenerReferencias($params)));
    mysqli_stmt_execute($stmt);
    $filasPrecios = obtenerFilasStmt($stmt);

    foreach ($filasPrecios as $fila) {
        $mapa[$fila['codigo']] = array(
            'codigo' => $fila['codigo'],
            'codigo_corto' => $fila['codigo_corto'],
            'descripcion' => $fila['descripcion'],
            'precio' => (float)$fila['precio'],
            'rubro' => isset($fila['rubro']) ? (int)$fila['rubro'] : 0,
            'sub_rubro' => isset($fila['sub_rubro']) ? (int)$fila['sub_rubro'] : 0,
            'unidad_medida' => $fila['unidad_medida'],
            'cantidad_medida' => (float)$fila['cantidad_medida']
        );
    }

    mysqli_stmt_close($stmt);

    return $mapa;
}

// Convierte un arreglo en referencias para bind_param dinamico en PHP 5.6.
function obtenerReferencias($arr)
{
    $refs = array();

    foreach ($arr as $key => $value) {
        $refs[$key] = &$arr[$key];
    }

    return $refs;
}

// Crea las tablas del sistema en login si todavia no existen.
function asegurarTablasLogin()
{
    static $tablasAseguradas = false;

    if ($tablasAseguradas) {
        return;
    }

    $conexion = obtenerConexionLogin();

    $sqlPlatos = "CREATE TABLE IF NOT EXISTS platos (
        id VARCHAR(50) NOT NULL,
        nombre VARCHAR(150) NOT NULL,
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    $sqlReceta = "CREATE TABLE IF NOT EXISTS receta_plato (
        id INT(11) NOT NULL AUTO_INCREMENT,
        plato_id VARCHAR(50) NOT NULL,
        ingrediente_codigo VARCHAR(50) NOT NULL,
        cantidad_utilizada_gr_cc_un DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (id),
        KEY idx_plato (plato_id),
        KEY idx_ingrediente_codigo (ingrediente_codigo),
        UNIQUE KEY uk_plato_ingrediente (plato_id, ingrediente_codigo),
        CONSTRAINT fk_receta_plato
            FOREIGN KEY (plato_id)
            REFERENCES platos(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    if (!mysqli_query($conexion, $sqlPlatos)) {
        responderJson(
            array(
                'success' => false,
                'message' => 'No se pudo crear la tabla platos en login',
                'error' => mysqli_error($conexion)
            ),
            500
        );
    }

    if (!mysqli_query($conexion, $sqlReceta)) {
        responderJson(
            array(
                'success' => false,
                'message' => 'No se pudo crear la tabla receta_plato en login',
                'error' => mysqli_error($conexion)
            ),
            500
        );
    }

    $tablasAseguradas = true;
}

