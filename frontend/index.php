<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Rotiseria - Recetas</title>
    <link rel="stylesheet" href="/frontend/css/app.css">
</head>
<body>
<div class="app">
    <header class="app-header">
        <h1>TOP ROTISERIA</h1>
        <div class="header-actions">
            <select id="selectOrden" class="select-orden">
                <option value="">Ordenar por nombre</option>
                <option value="precio_venta_alto">Precio venta mas alto</option>
                <option value="precio_venta_bajo">Precio venta mas bajo</option>
                <option value="margen_alto">Margen mas alto</option>
                <option value="margen_bajo">Margen mas chico</option>
            </select>
            <button id="btnImportarCsv" class="btn-secondary">Importar CSV</button> <!--si se comenta esta linea desaparece el boton y el input para importar el csv-->
            <input type="file" id="inputCsvFile" accept=".csv" class="hidden-file-input">
            <button id="btnNuevoPlato" class="btn-primary">+ Nuevo Plato</button>
        </div>
    </header>

    <main class="layout">
        <section class="panel panel-list">
            <h2>Platos</h2>
            <div id="platosList" class="platos-list"></div>
        </section>

        <section class="panel panel-detail">
            <h2>Detalle de receta</h2>
            <div id="detallePlato" class="empty-state">
                Selecciona un plato para ver su receta y costo.
            </div>
        </section>
    </main>
</div>

<div id="modalPlato" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="formTitle">Nuevo plato</h3>
            <button id="btnCerrarModal" class="btn-secondary">Cerrar</button>
        </div>

        <div class="field">
            <label for="inputNombre">Nombre del plato</label>
            <input type="text" id="inputNombre" maxlength="150" placeholder="Ej: Milanesa Napolitana">
            <div id="nombresPlatoResultados" class="search-results"></div>
        </div>

        <div class="field">
            <label for="inputBuscarIngrediente">Buscar ingrediente</label>
            <input type="text" id="inputBuscarIngrediente" placeholder="Buscar por codigo o nombre corto">
            <div id="ingredientesResultados" class="search-results"></div>
        </div>

        <div class="field">
            <label>Ingredientes de la receta</label>
            <div id="recetaEditor" class="receta-editor"></div>
        </div>

        <div class="form-actions">
            <button id="btnGuardarPlato" class="btn-primary">Guardar receta</button>
        </div>
    </div>
</div>

<script src="/frontend/js/app.js"></script>
</body>
</html>
