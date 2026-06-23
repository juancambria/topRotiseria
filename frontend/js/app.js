var state = {
    platos: [],
    platoSeleccionadoId: null,
    modoEdicionId: null,
    codigoPlatoSeleccionado: null,
    criterioOrden: '',
    recetaActual: [],
    debounceTimer: null,
    debounceNombrePlatoTimer: null
};

var platosList = document.getElementById('platosList');
var detallePlato = document.getElementById('detallePlato');
var selectOrden = document.getElementById('selectOrden');
var btnImportarCsv = document.getElementById('btnImportarCsv');
var inputCsvFile = document.getElementById('inputCsvFile');
var btnNuevoPlato = document.getElementById('btnNuevoPlato');
var modalPlato = document.getElementById('modalPlato');
var btnCerrarModal = document.getElementById('btnCerrarModal');
var formTitle = document.getElementById('formTitle');
var inputNombre = document.getElementById('inputNombre');
var nombresPlatoResultados = document.getElementById('nombresPlatoResultados');
var inputBuscarIngrediente = document.getElementById('inputBuscarIngrediente');
var ingredientesResultados = document.getElementById('ingredientesResultados');
var recetaEditor = document.getElementById('recetaEditor');
var btnGuardarPlato = document.getElementById('btnGuardarPlato');

btnNuevoPlato.addEventListener('click', function () {
    abrirModalNuevo();
});

btnCerrarModal.addEventListener('click', function () {
    cerrarModal();
});

btnGuardarPlato.addEventListener('click', function () {
    guardarPlato();
});

btnImportarCsv.addEventListener('click', function () {
    inputCsvFile.click();
});

inputCsvFile.addEventListener('change', function (event) {
    var archivo = event.target.files && event.target.files[0] ? event.target.files[0] : null;

    if (!archivo) {
        return;
    }

    importarCsv(archivo);
});

selectOrden.addEventListener('change', function (event) {
    state.criterioOrden = event.target.value;
    loadPlatos();
});

inputBuscarIngrediente.addEventListener('input', function (event) {
    var value = event.target.value.trim();

    if (state.debounceTimer) {
        clearTimeout(state.debounceTimer);
    }

    if (value.length < 2) {
        ingredientesResultados.innerHTML = '';
        return;
    }

    state.debounceTimer = setTimeout(function () {
        buscarIngredientes(value);
    }, 250);
});

inputNombre.addEventListener('input', function (event) {
    var value = event.target.value.trim();
    state.codigoPlatoSeleccionado = null;

    if (state.debounceNombrePlatoTimer) {
        clearTimeout(state.debounceNombrePlatoTimer);
    }

    if (value.length < 2) {
        nombresPlatoResultados.innerHTML = '';
        return;
    }

    state.debounceNombrePlatoTimer = setTimeout(function () {
        buscarNombresPlato(value);
    }, 250);
});

loadPlatos();

function request(url, options) {
    return fetch(url, options)
        .then(function (response) {
            return response.text().then(function (rawBody) {
                var body = null;

                if (rawBody && rawBody.trim() !== '') {
                    try {
                        body = JSON.parse(rawBody);
                    } catch (errorParseo) {
                        throw new Error('La API devolvio una respuesta invalida (no JSON).');
                    }
                }

                if (!response.ok) {
                    if (body && body.message) {
                        throw new Error(body.message);
                    }

                    throw new Error('Error HTTP ' + response.status + ' en ' + url);
                }

                if (!body || typeof body.success === 'undefined') {
                    throw new Error('La API no devolvio datos validos.');
                }

                if (!body.success) {
                    throw new Error(body.message || 'Error de servidor');
                }

                return body;
            });
        });
}

function loadPlatos() {
    var url = '../backend/api/platos.php';

    if (state.criterioOrden !== '') {
        url += '?ordenar=' + encodeURIComponent(state.criterioOrden);
    }

    request(url)
        .then(function (body) {
            state.platos = body.data;
            renderPlatos();
        })
        .catch(function (error) {
            alert(error.message);
        });
}

function renderPlatos() {
    if (state.platos.length === 0) {
        platosList.innerHTML = '<p class="empty-state">No hay platos registrados.</p>';
        return;
    }

    var html = '';

    state.platos.forEach(function (plato) {
        var activeClass = state.platoSeleccionadoId === plato.id ? 'active' : '';
        var costoConSobrecarga = obtenerCostoConSobrecarga(plato);
        var costoSinSobrecarga = obtenerCostoSinSobrecarga(plato);
        var margenConSobrecarga = obtenerMargenConSobrecarga(plato);
        var margenSinSobrecarga = obtenerMargenSinSobrecarga(plato);
        html += '<div class="plato-card ' + activeClass + '" data-id="' + plato.id + '">';
        html += '<p class="plato-nombre">' + escapeHtml(plato.nombre) + '</p>';
        html += '<p class="plato-meta">Ingredientes: ' + plato.cantidad_ingredientes + '</p>';
        html += '<p class="plato-meta">Precio venta: $' + formatCurrency(plato.precio_venta) + '</p>';
        html += '<p class="plato-meta">Costo (sin sobrecarga): $' + formatCurrency(costoSinSobrecarga) + '</p>';
        html += '<p class="plato-meta">Costo (con sobrecarga): $' + formatCurrency(costoConSobrecarga) + '</p>';
        html += '<p class="plato-meta">Margen (sin sobrecarga): ' + formatPercent(margenSinSobrecarga) + '%</p>';
        html += '<p class="plato-meta">Margen (con sobrecarga): ' + formatPercent(margenConSobrecarga) + '%</p>';
        html += '</div>';
    });

    platosList.innerHTML = html;

    var cards = platosList.querySelectorAll('.plato-card');
    for (var i = 0; i < cards.length; i++) {
        cards[i].addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            state.platoSeleccionadoId = id;
            renderPlatos();
            loadDetalle(id);
        });
    }
}

function loadDetalle(id) {
    request('../backend/api/platos.php?id=' + id)
        .then(function (body) {
            renderDetalle(body.data);
        })
        .catch(function (error) {
            alert(error.message);
        });
}

function renderDetalle(plato) {
    var costoConSobrecarga = obtenerCostoConSobrecarga(plato);
    var costoSinSobrecarga = obtenerCostoSinSobrecarga(plato);
    var margenConSobrecarga = obtenerMargenConSobrecarga(plato);
    var margenSinSobrecarga = obtenerMargenSinSobrecarga(plato);
    var html = '';
    html += '<h3>' + escapeHtml(plato.nombre) + '</h3>';

    if (plato.receta.length === 0) {
        html += '<p class="empty-state">Este plato no tiene ingredientes.</p>';
    } else {
        html += '<table class="detalle-table">';
        html += '<thead><tr><th>Ingrediente</th><th>Cantidad</th><th>Costo</th></tr></thead>';
        html += '<tbody>';
        plato.receta.forEach(function (item) {
            var cantidad = typeof item.cantidad_utilizada_gr_cc_un !== 'undefined'
                ? item.cantidad_utilizada_gr_cc_un
                : item.cantidad_utilizada;
            var unidadMostrada = obtenerUnidadCargaSegunUnidad(item.unidad_medida);
            html += '<tr>';
            html += '<td>' + escapeHtml(item.nombre) + '</td>';
            html += '<td>' + formatNumber(cantidad) + ' ' + escapeHtml(unidadMostrada) + '</td>';
            html += '<td>$' + formatCurrency(item.costo_ingrediente) + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
    }

    html += '<p class="detalle-total">Costo total (sin sobrecarga): $' + formatCurrency(costoSinSobrecarga) + '</p>';
    html += '<p class="detalle-total">Costo total (con sobrecarga): $' + formatCurrency(costoConSobrecarga) + '</p>';
    html += '<p class="plato-meta">Precio venta: $' + formatCurrency(plato.precio_venta) + '</p>';
    html += '<p class="plato-meta">Margen (sin sobrecarga): ' + formatPercent(margenSinSobrecarga) + '%</p>';
    html += '<p class="plato-meta">Margen (con sobrecarga): ' + formatPercent(margenConSobrecarga) + '%</p>';
    html += '<div class="detalle-actions">';
    html += '<button class="btn-primary" id="btnEditarPlato">Editar receta</button>';
    html += '<button class="btn-danger" id="btnEliminarPlato">Eliminar plato</button>';
    html += '</div>';

    detallePlato.innerHTML = html;

    document.getElementById('btnEditarPlato').addEventListener('click', function () {
        abrirModalEditar(plato);
    });

    document.getElementById('btnEliminarPlato').addEventListener('click', function () {
        eliminarPlato(plato.id);
    });
}

function abrirModalNuevo() {
    state.modoEdicionId = null;
    state.codigoPlatoSeleccionado = null;
    state.recetaActual = [];
    formTitle.textContent = 'Nuevo plato';
    inputNombre.value = '';
    nombresPlatoResultados.innerHTML = '';
    inputBuscarIngrediente.value = '';
    ingredientesResultados.innerHTML = '';
    renderRecetaEditor();
    modalPlato.classList.remove('hidden');
}

function abrirModalEditar(plato) {
    state.modoEdicionId = plato.id;
    state.codigoPlatoSeleccionado = null;
    state.recetaActual = plato.receta.map(function (item) {
        var cantidad = typeof item.cantidad_utilizada_gr_cc_un !== 'undefined'
            ? item.cantidad_utilizada_gr_cc_un
            : item.cantidad_utilizada;
        return {
            codigo: item.codigo,
            nombre: item.nombre,
            unidad_medida: item.unidad_medida,
            cantidad: cantidad
        };
    });

    formTitle.textContent = 'Editar plato';
    inputNombre.value = plato.nombre;
    nombresPlatoResultados.innerHTML = '';
    inputBuscarIngrediente.value = '';
    ingredientesResultados.innerHTML = '';
    renderRecetaEditor();
    modalPlato.classList.remove('hidden');
}

function cerrarModal() {
    modalPlato.classList.add('hidden');
    nombresPlatoResultados.innerHTML = '';
}

function buscarNombresPlato(query) {
    request('../backend/api/nombres_plato.php?q=' + encodeURIComponent(query))
        .then(function (body) {
            renderResultadosNombresPlato(body.data);
        })
        .catch(function (error) {
            alert(error.message);
        });
}

function renderResultadosNombresPlato(nombres) {
    if (!nombres || nombres.length === 0) {
        nombresPlatoResultados.innerHTML = '<div class="search-item">Sin resultados</div>';
        return;
    }

    var html = '';

    nombres.forEach(function (item) {
        html += '<div class="search-item" data-codigo="' + escapeHtml(item.codigo) + '" data-nombre="' + escapeHtml(item.nombre) + '">';
        html += '<strong>' + escapeHtml(item.nombre) + '</strong><br>';
        html += '<small>Codigo: ' + escapeHtml(item.codigo) + '</small>';
        html += '</div>';
    });

    nombresPlatoResultados.innerHTML = html;

    var items = nombresPlatoResultados.querySelectorAll('.search-item');
    for (var i = 0; i < items.length; i++) {
        items[i].addEventListener('click', function () {
            inputNombre.value = this.getAttribute('data-nombre');
            state.codigoPlatoSeleccionado = this.getAttribute('data-codigo');
            nombresPlatoResultados.innerHTML = '';
        });
    }
}

function buscarIngredientes(query) {
    request('../backend/api/ingredientes.php?q=' + encodeURIComponent(query))
        .then(function (body) {
            renderResultadosBusqueda(body.data);
        })
        .catch(function (error) {
            alert(error.message);
        });
}

function renderResultadosBusqueda(ingredientes) {
    if (ingredientes.length === 0) {
        ingredientesResultados.innerHTML = '<div class="search-item">Sin resultados</div>';
        return;
    }

    var html = '';

    ingredientes.forEach(function (item) {
        html += '<div class="search-item" data-codigo="' + escapeHtml(item.codigo) + '"';
        html += ' data-nombre="' + escapeHtml(item.nombre) + '"';
        html += ' data-unidad="' + escapeHtml(item.unidad_medida) + '">';
        html += '<strong>' + escapeHtml(item.nombre) + '</strong><br>';
        html += '<small>Codigo: ' + escapeHtml(item.codigo) + ' | $' + formatCurrency(item.precio) + ' / ' + formatNumber(item.cantidad_medida) + ' ' + escapeHtml(item.unidad_medida) + '</small>';
        html += '</div>';
    });

    ingredientesResultados.innerHTML = html;

    var items = ingredientesResultados.querySelectorAll('.search-item');
    for (var i = 0; i < items.length; i++) {
        items[i].addEventListener('click', function () {
            agregarIngrediente({
                codigo: this.getAttribute('data-codigo'),
                nombre: this.getAttribute('data-nombre'),
                unidad_medida: this.getAttribute('data-unidad'),
                cantidad: ''
            });

            inputBuscarIngrediente.value = '';
            ingredientesResultados.innerHTML = '';
        });
    }
}

function agregarIngrediente(ingrediente) {
    var exists = state.recetaActual.some(function (item) {
        return item.codigo === ingrediente.codigo;
    });

    if (exists) {
        enfocarInputCantidadPorIndex(obtenerIndiceIngrediente(ingrediente.codigo));
        return;
    }

    state.recetaActual.push(ingrediente);
    renderRecetaEditor(state.recetaActual.length - 1);
}

function renderRecetaEditor(focoIndex) {
    if (state.recetaActual.length === 0) {
        recetaEditor.innerHTML = '<p class="empty-state" style="padding:8px;">Agrega ingredientes para comenzar la receta.</p>';
        return;
    }

    var html = '';

    state.recetaActual.forEach(function (item, index) {
        var textoCantidad = obtenerTextoCantidadSegunUnidad(item.unidad_medida);
        var valorCantidad = item.cantidad === '' || item.cantidad === null ? '' : formatNumber(item.cantidad);

        html += '<div class="receta-row">';
        html += '<div><strong>' + escapeHtml(item.nombre) + '</strong><br><small>' + escapeHtml(item.codigo) + '</small></div>';
        html += '<div>';
        html += '<small>' + escapeHtml(textoCantidad) + '</small>';
        html += '<input type="number" min="0.01" step="0.01" data-index="' + index + '" class="input-cantidad" value="' + valorCantidad + '" placeholder="' + escapeHtml(textoCantidad) + '">';
        html += '</div>';
        html += '<button type="button" class="btn-danger btn-remove" data-index="' + index + '">Quitar</button>';
        html += '</div>';
    });

    recetaEditor.innerHTML = html;

    var inputs = recetaEditor.querySelectorAll('.input-cantidad');
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].addEventListener('change', function () {
            var index = parseInt(this.getAttribute('data-index'), 10);
            var value = parseFloat(this.value);

            if (isNaN(value) || value <= 0) {
                state.recetaActual[index].cantidad = '';
                this.value = '';
                return;
            }

            state.recetaActual[index].cantidad = value;
            this.value = formatNumber(value);
        });
    }

    var btnRemove = recetaEditor.querySelectorAll('.btn-remove');
    for (var j = 0; j < btnRemove.length; j++) {
        btnRemove[j].addEventListener('click', function () {
            var index = parseInt(this.getAttribute('data-index'), 10);
            state.recetaActual.splice(index, 1);
            renderRecetaEditor();
        });
    }

    if (typeof focoIndex !== 'undefined' && focoIndex !== null) {
        enfocarInputCantidadPorIndex(focoIndex);
    }
}

function guardarPlato() {
    var nombre = inputNombre.value.trim();
    var receta = state.recetaActual.map(function (item) {
        return {
            codigo: item.codigo,
            cantidad: parseFloat(item.cantidad)
        };
    });

    var payload = {
        nombre: nombre,
        codigo_plato: state.codigoPlatoSeleccionado || '',
        receta: receta
    };

    var isEdit = state.modoEdicionId !== null;
    var url = '../backend/api/platos.php' + (isEdit ? '?id=' + state.modoEdicionId : '');
    var method = isEdit ? 'PUT' : 'POST';

    request(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
        .then(function () {
            cerrarModal();
            loadPlatos();
            if (state.platoSeleccionadoId) {
                loadDetalle(state.platoSeleccionadoId);
            }
        })
        .catch(function (error) {
            alert(error.message);
        });
}

function eliminarPlato(id) {
    if (!confirm('¿Seguro que deseas eliminar este plato?')) {
        return;
    }

    request('../backend/api/platos.php?id=' + id, {
        method: 'DELETE'
    })
        .then(function () {
            state.platoSeleccionadoId = null;
            detallePlato.innerHTML = 'Selecciona un plato para ver su receta y costo.';
            loadPlatos();
        })
        .catch(function (error) {
            alert(error.message);
        });
}

function importarCsv(archivoCsv) {
    var formData = new FormData();
    formData.append('csv_file', archivoCsv);

    request('../backend/api/importar_csv.php', {
        method: 'POST',
        body: formData
    })
        .then(function (body) {
            var data = body.data || {};
            var resumen = 'Importacion completada.\n';
            resumen += 'Platos: ' + (data.platos_procesados || 0) + '\n';
            resumen += 'Ingredientes: ' + (data.ingredientes_insertados || 0) + '\n';
            resumen += 'Filas validas: ' + (data.filas_validas || 0) + '\n';
            resumen += 'Filas invalidas: ' + (data.filas_invalidas || 0);
            alert(resumen);
            inputCsvFile.value = '';
            loadPlatos();
        })
        .catch(function (error) {
            inputCsvFile.value = '';
            alert(error.message);
        });
}

function escapeHtml(value) {
    if (value === null || typeof value === 'undefined') {
        return '';
    }

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatCurrency(value) {
    var num = parseFloat(value || 0);
    return num.toFixed(2);
}

function formatNumber(value) {
    var num = parseFloat(value || 0);
    return num % 1 === 0 ? String(num.toFixed(0)) : String(num.toFixed(2));
}

function formatPercent(value) {
    var num = parseFloat(value || 0);
    return num.toFixed(2);
}

function obtenerCostoConSobrecarga(plato) {
    if (!plato) {
        return 0;
    }

    if (typeof plato.costo_total_con_sobrecarga !== 'undefined') {
        return plato.costo_total_con_sobrecarga;
    }

    return plato.costo_total;
}

function obtenerCostoSinSobrecarga(plato) {
    if (!plato) {
        return 0;
    }

    if (typeof plato.costo_total_sin_sobrecarga !== 'undefined') {
        return plato.costo_total_sin_sobrecarga;
    }

    return plato.costo_total;
}

function obtenerMargenConSobrecarga(plato) {
    if (!plato) {
        return 0;
    }

    if (typeof plato.margen_con_sobrecarga !== 'undefined') {
        return plato.margen_con_sobrecarga;
    }

    return plato.margen;
}

function obtenerMargenSinSobrecarga(plato) {
    if (!plato) {
        return 0;
    }

    if (typeof plato.margen_sin_sobrecarga !== 'undefined') {
        return plato.margen_sin_sobrecarga;
    }

    return plato.margen;
}

function obtenerTextoCantidadSegunUnidad(unidadMedida) {
    var unidad = (unidadMedida || '').toUpperCase();

    if (unidad === 'CC' || unidad === 'LT' || unidad === 'L' || unidad === 'ML') {
        return 'Cantidad de centimetros cubicos';
    }

    if (unidad === 'GR' || unidad === 'KG') {
        return 'Cantidad de gramos';
    }

    if (unidad === 'UN') {
        return 'Cantidad de unidades';
    }

    if (unidad === '') {
        return 'Cantidad utilizada';
    }

    return 'Cantidad en ' + unidad;
}

function obtenerUnidadCargaSegunUnidad(unidadMedida) {
    var unidad = (unidadMedida || '').toUpperCase();

    if (unidad === 'CC' || unidad === 'LT' || unidad === 'L' || unidad === 'ML') {
        return 'CC';
    }

    if (unidad === 'GR' || unidad === 'KG') {
        return 'GR';
    }

    return unidad;
}

function obtenerIndiceIngrediente(codigoIngrediente) {
    for (var i = 0; i < state.recetaActual.length; i++) {
        if (state.recetaActual[i].codigo === codigoIngrediente) {
            return i;
        }
    }

    return -1;
}

function enfocarInputCantidadPorIndex(index) {
    if (index < 0) {
        return;
    }

    var inputCantidad = recetaEditor.querySelector('.input-cantidad[data-index="' + index + '"]');

    if (!inputCantidad) {
        return;
    }

    inputCantidad.focus();
    inputCantidad.select();
}
