<?php

require_once "../config/database.php";

$evento_id = 1;

// ---------------------------------------------------------
// VARIABLES
// ---------------------------------------------------------

$mensaje = "";
$error = "";

$corredor = null;
$ya_inscripto = false;

$distancia_seleccionada = "";
$categoria_seleccionada = "";
$dorsal = "";

$remera = 1;
$talle_remera = "";

$precio_base = 0;
$descuento_monto = 0;
$descuento_motivo = "";
$total = 0;


// ---------------------------------------------------------
// BUSCAR CORREDOR POR DNI
// ---------------------------------------------------------

if (isset($_GET["dni"])) {

    $dni = trim($_GET["dni"]);

    if ($dni !== "") {

        $stmt = $pdo->prepare("
            SELECT *
            FROM corredores
            WHERE dni = ?
            LIMIT 1
        ");

        $stmt->execute([$dni]);

        $corredor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($corredor) {

            // Verificar si ya está inscripto en el evento
            $stmt = $pdo->prepare("
                SELECT *
                FROM inscripciones
                WHERE evento_id = ?
                  AND corredor_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $evento_id,
                $corredor["id"]
            ]);

            if ($stmt->fetch()) {
                $ya_inscripto = true;
            }
        }
    }
}


// ---------------------------------------------------------
// GUARDAR INSCRIPCIÓN
// ---------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $corredor_id = intval($_POST["corredor_id"] ?? 0);

    $distancia_id = intval(
        $_POST["distancia_id"] ?? 0
    );

    $categoria_id = !empty($_POST["categoria_id"])
        ? intval($_POST["categoria_id"])
        : null;

    $remera = isset($_POST["remera"])
        ? intval($_POST["remera"])
        : 1;

    $talle_remera = trim(
        $_POST["talle_remera"] ?? ""
    );

    $dorsal_ingresado = !empty($_POST["dorsal"])
        ? intval($_POST["dorsal"])
        : 0;

    $descuento_monto = floatval(
        $_POST["descuento_monto"] ?? 0
    );

    $descuento_motivo = trim(
        $_POST["descuento_motivo"] ?? ""
    );


    // -----------------------------------------------------
    // VALIDACIONES
    // -----------------------------------------------------

    if ($corredor_id <= 0) {

        $error = "Corredor inválido.";

    } elseif ($distancia_id <= 0) {

        $error = "Debe seleccionar una distancia.";

    } elseif ($remera === 1 && $talle_remera === "") {

        $error = "Debe seleccionar el talle de remera.";

    } elseif ($remera === 0) {

        $talle_remera = null;
    }


    // -----------------------------------------------------
    // VERIFICAR SI YA ESTÁ INSCRIPTO
    // -----------------------------------------------------

    if ($error === "") {

        $stmt = $pdo->prepare("
            SELECT id
            FROM inscripciones
            WHERE evento_id = ?
              AND corredor_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $evento_id,
            $corredor_id
        ]);

        if ($stmt->fetch()) {

            $error =
                "El corredor ya está inscripto en este evento.";
        }
    }


    // -----------------------------------------------------
    // OBTENER PERÍODO VIGENTE
    // -----------------------------------------------------

    $periodo = null;

    if ($error === "") {

        $fecha_hoy = date("Y-m-d");

        $stmt = $pdo->prepare("
            SELECT *
            FROM periodos_inscripcion
            WHERE evento_id = ?
              AND fecha_desde <= ?
              AND fecha_hasta >= ?
            ORDER BY fecha_desde DESC
            LIMIT 1
        ");

        $stmt->execute([
            $evento_id,
            $fecha_hoy,
            $fecha_hoy
        ]);

        $periodo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$periodo) {

            $error =
                "No existe un período de inscripción vigente para la fecha de hoy.";
        }
    }


    // -----------------------------------------------------
    // OBTENER DISTANCIA Y RANGO DE DORSALES
    // -----------------------------------------------------

    $distancia = null;

    if ($error === "") {

        $stmt = $pdo->prepare("
            SELECT *
            FROM distancias
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $distancia_id
        ]);

        $distancia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$distancia) {

            $error = "La distancia seleccionada no existe.";

        } elseif (
            $distancia["dorsal_desde"] === null ||
            $distancia["dorsal_hasta"] === null
        ) {

            $error =
                "La distancia no tiene configurado el rango de dorsales.";
        }
    }


    // -----------------------------------------------------
    // OBTENER PRECIO
    // -----------------------------------------------------

    if ($error === "") {

        $stmt = $pdo->prepare("
            SELECT *
            FROM precios_inscripcion
            WHERE periodo_id = ?
              AND distancia_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $periodo["id"],
            $distancia_id
        ]);

        $precio = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$precio) {

            $error =
                "No hay un precio configurado para esta distancia en el período actual.";

        } else {

            if ($remera === 1) {

                $precio_base =
                    floatval($precio["precio_con_remera"]);

            } else {

                $precio_base =
                    floatval($precio["precio_sin_remera"]);
            }
        }
    }


    // -----------------------------------------------------
    // CALCULAR DESCUENTO Y TOTAL
    // -----------------------------------------------------

    if ($error === "") {

        if ($descuento_monto < 0) {
            $descuento_monto = 0;
        }

        if ($descuento_monto > $precio_base) {
            $descuento_monto = $precio_base;
        }

        $total =
            $precio_base - $descuento_monto;
    }


    // -----------------------------------------------------
    // ASIGNAR DORSAL
    // -----------------------------------------------------

    if ($error === "") {

        $dorsal = $dorsal_ingresado;


        // -------------------------------------------------
        // DORSAL AUTOMÁTICO
        // -------------------------------------------------

        if ($dorsal <= 0) {

            $desde = intval(
                $distancia["dorsal_desde"]
            );

            $hasta = intval(
                $distancia["dorsal_hasta"]
            );

            $dorsal_encontrado = null;


            // Buscar el primer dorsal libre
            for (
                $numero = $desde;
                $numero <= $hasta;
                $numero++
            ) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM inscripciones
                    WHERE evento_id = ?
                      AND dorsal = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $evento_id,
                    $numero
                ]);

                if (!$stmt->fetch()) {

                    $dorsal_encontrado = $numero;
                    break;
                }
            }


            if ($dorsal_encontrado === null) {

                $error =
                    "No quedan dorsales disponibles para la distancia seleccionada.";

            } else {

                $dorsal = $dorsal_encontrado;
            }
        }
    }


    // -----------------------------------------------------
    // SI SE INGRESÓ DORSAL MANUAL
    // VERIFICAR QUE ESTÉ DENTRO DEL RANGO
    // -----------------------------------------------------

    if ($error === "" && $dorsal_ingresado > 0) {

        $desde = intval(
            $distancia["dorsal_desde"]
        );

        $hasta = intval(
            $distancia["dorsal_hasta"]
        );

        if (
            $dorsal_ingresado < $desde ||
            $dorsal_ingresado > $hasta
        ) {

            $error =
                "El dorsal $dorsal_ingresado está fuera del rango de la distancia. " .
                "El rango permitido es $desde a $hasta.";
        }
    }


    // -----------------------------------------------------
    // VERIFICAR QUE EL DORSAL NO ESTÉ OCUPADO
    // -----------------------------------------------------

    if ($error === "") {

        $stmt = $pdo->prepare("
            SELECT id
            FROM inscripciones
            WHERE evento_id = ?
              AND dorsal = ?
            LIMIT 1
        ");

        $stmt->execute([
            $evento_id,
            $dorsal
        ]);

        if ($stmt->fetch()) {

            $error =
                "El dorsal $dorsal ya está asignado.";
        }
    }


    // -----------------------------------------------------
    // INSERTAR INSCRIPCIÓN
    // -----------------------------------------------------

    if ($error === "") {

        $stmt = $pdo->prepare("
            INSERT INTO inscripciones
            (
                evento_id,
                corredor_id,
                distancia_id,
                periodo_id,
                categoria_id,
                dorsal,
                remera,
                talle_remera,
                precio_base,
                descuento_monto,
                descuento_motivo,
                total,
                estado
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmado'
            )
        ");

        $stmt->execute([

            $evento_id,

            $corredor_id,

            $distancia_id,

            $periodo["id"],

            $categoria_id,

            $dorsal,

            $remera,

            $talle_remera,

            $precio_base,

            $descuento_monto,

            $descuento_motivo !== ""
                ? $descuento_motivo
                : null,

            $total
        ]);


        // -------------------------------------------------
        // REDIRECCIÓN
        // -------------------------------------------------

        header(
            "Location: acreditacion.php?ok=1&dorsal=" .
            $dorsal
        );

        exit;
    }
}


// ---------------------------------------------------------
// CARGAR DISTANCIAS
// ---------------------------------------------------------

$stmt = $pdo->query("
    SELECT *
    FROM distancias
    ORDER BY distancia_km
");

$distancias =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ---------------------------------------------------------
// CARGAR CATEGORÍAS
// ---------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT *
    FROM categorias
    WHERE evento_id = ?
    ORDER BY sexo, edad_min
");

$stmt->execute([
    $evento_id
]);

$categorias =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ---------------------------------------------------------
// CARGAR PRECIOS DEL PERÍODO ACTUAL
// ---------------------------------------------------------

$precios_js = [];

$fecha_hoy = date("Y-m-d");

$stmt = $pdo->prepare("
    SELECT
        pi.distancia_id,
        pi.precio_con_remera,
        pi.precio_sin_remera
    FROM precios_inscripcion pi
    INNER JOIN periodos_inscripcion p
        ON p.id = pi.periodo_id
    WHERE p.evento_id = ?
      AND p.fecha_desde <= ?
      AND p.fecha_hasta >= ?
");

$stmt->execute([
    $evento_id,
    $fecha_hoy,
    $fecha_hoy
]);

foreach (
    $stmt->fetchAll(PDO::FETCH_ASSOC)
    as $fila
) {

    $precios_js[$fila["distancia_id"]] = [

        "con_remera" =>
            floatval($fila["precio_con_remera"]),

        "sin_remera" =>
            floatval($fila["precio_sin_remera"])
    ];
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Acreditación - CronoTrail</title>

    <link
        rel="stylesheet"
        href="../css/estilos.css"
    >

    <link
        rel="stylesheet"
        href="../css/acreditacion.css"
    >

</head>

<body>

<?php require_once "../includes/menu.php"; ?>


<main class="acreditacion-contenedor">


    <!-- ================================================= -->
    <!-- CABECERA -->
    <!-- ================================================= -->

    <div class="acreditacion-cabecera">

        <h1>Acreditación</h1>

        <p>
            Buscar corredor y completar su inscripción.
        </p>

    </div>


    <!-- ================================================= -->
    <!-- MENSAJE DE ÉXITO -->
    <!-- ================================================= -->

    <?php if (isset($_GET["ok"])): ?>

        <div class="acreditacion-mensaje">

            Inscripción realizada correctamente.

            <br>

            Dorsal asignado:

            <strong>
                <?= htmlspecialchars($_GET["dorsal"] ?? "") ?>
            </strong>

        </div>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- MENSAJE DE ERROR -->
    <!-- ================================================= -->

    <?php if ($error !== ""): ?>

        <div class="acreditacion-error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- BUSCAR CORREDOR -->
    <!-- ================================================= -->

    <section class="acreditacion-panel">

        <form method="GET">

            <div class="busqueda-dni">

                <div class="campo campo-dni">

                    <label for="dni">
                        DNI del corredor
                    </label>

                    <input
                        type="text"
                        name="dni"
                        id="dni"
                        value="<?= htmlspecialchars($_GET["dni"] ?? "") ?>"
                        placeholder="Ingrese el DNI"
                        autofocus
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="boton"
                >
                    Buscar corredor
                </button>

            </div>

        </form>

    </section>


    <!-- ================================================= -->
    <!-- CORREDOR ENCONTRADO -->
    <!-- ================================================= -->

    <?php if ($corredor): ?>

        <section class="acreditacion-panel">


            <!-- DATOS DEL CORREDOR -->

            <div class="datos-corredor">

                <span class="nombre">

                    <?= htmlspecialchars($corredor["apellido"]) ?>,
                    <?= htmlspecialchars($corredor["nombre"]) ?>

                </span>

                DNI:
                <?= htmlspecialchars($corredor["dni"]) ?>


                <?php if (!empty($corredor["email"])): ?>

                    <br>

                    Email:
                    <?= htmlspecialchars($corredor["email"]) ?>

                <?php endif; ?>

            </div>


            <!-- ================================================= -->
            <!-- YA INSCRIPTO -->
            <!-- ================================================= -->

            <?php if ($ya_inscripto): ?>

                <div class="acreditacion-error">

                    Este corredor ya está inscripto en el evento.

                </div>


            <?php else: ?>


                <!-- ================================================= -->
                <!-- FORMULARIO DE INSCRIPCIÓN -->
                <!-- ================================================= -->

                <form
                    method="POST"
                    class="formulario-acreditacion"
                >

                    <input
                        type="hidden"
                        name="corredor_id"
                        value="<?= htmlspecialchars($corredor["id"]) ?>"
                    >


                    <!-- DISTANCIA -->

                    <div class="campo">

                        <label for="distancia_id">
                            Distancia
                        </label>

                        <select
                            name="distancia_id"
                            id="distancia_id"
                            required
                        >

                            <option value="">
                                Seleccionar distancia
                            </option>

                            <?php foreach ($distancias as $distancia): ?>

                                <option
                                    value="<?= htmlspecialchars($distancia["id"]) ?>"
                                >

                                    <?= htmlspecialchars($distancia["nombre"]) ?>

                                    -

                                    <?= htmlspecialchars($distancia["distancia_km"]) ?>

                                    km

                                </option>

                            <?php endforeach; ?>

                        </select>


                        <div
                            id="rango_dorsal"
                            class="rango-dorsal"
                        ></div>

                    </div>


                    <!-- REMERA -->

                    <div class="campo">

                        <label>
                            Remera
                        </label>

                        <div class="opciones-radio">

                            <label>

                                <input
                                    type="radio"
                                    name="remera"
                                    value="1"
                                    checked
                                >

                                Con remera

                            </label>


                            <label>

                                <input
                                    type="radio"
                                    name="remera"
                                    value="0"
                                >

                                Sin remera

                            </label>

                        </div>

                    </div>


                    <!-- TALLE -->

                    <div
                        class="campo"
                        id="campo_talle"
                    >

                        <label for="talle_remera">
                            Talle de remera
                        </label>

                        <select
                            name="talle_remera"
                            id="talle_remera"
                        >

                            <option value="">
                                Seleccionar talle
                            </option>

                            <option value="XS">XS</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>

                        </select>

                    </div>


                    <!-- CATEGORIA -->

                    <div class="campo">

                        <label for="categoria_id">
                            Categoría
                        </label>

                        <select
                            name="categoria_id"
                            id="categoria_id"
                        >

                            <option value="">
                                Seleccionar categoría
                            </option>

                            <?php foreach ($categorias as $categoria): ?>

                                <option
                                    value="<?= htmlspecialchars($categoria["id"]) ?>"
                                >

                                    <?= htmlspecialchars($categoria["nombre"]) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- DORSAL -->

                    <div class="campo campo-completo">

                        <label for="dorsal">
                            Dorsal
                        </label>

                        <input
                            type="number"
                            name="dorsal"
                            id="dorsal"
                            min="1"
                            placeholder="Vacío = asignación automática"
                        >

                        <div class="rango-dorsal">

                            Si se deja vacío, se asignará automáticamente
                            el primer dorsal libre del rango de la distancia.

                        </div>

                    </div>


                    <!-- DESCUENTO -->

                    <div class="campo">

                        <label for="descuento_monto">
                            Descuento
                        </label>

                        <input
                            type="number"
                            name="descuento_monto"
                            id="descuento_monto"
                            value="0"
                            min="0"
                            step="0.01"
                        >

                    </div>


                    <!-- MOTIVO DEL DESCUENTO -->

                    <div class="campo">

                        <label for="descuento_motivo">
                            Motivo del descuento
                        </label>

                        <input
                            type="text"
                            name="descuento_motivo"
                            id="descuento_motivo"
                            placeholder="Ej.: TEAM, sponsor, cortesía..."
                        >

                    </div>


                    <!-- TOTAL -->

                    <div class="campo campo-completo">

                        <div class="precio-panel">

                            <label>
                                Total a pagar
                            </label>

                            <div
                                class="precio"
                                id="precio"
                            >
                                $0,00
                            </div>

                        </div>

                    </div>


                    <!-- BOTÓN -->

                    <div class="acciones-acreditacion campo-completo">

                        <button
                            type="submit"
                            class="boton-acreditar"
                        >
                            Confirmar inscripción
                        </button>

                    </div>

                </form>


            <?php endif; ?>

        </section>

    <?php endif; ?>


</main>


<!-- ===================================================== -->
<!-- JAVASCRIPT -->
<!-- ===================================================== -->

<script>

// ---------------------------------------------------------
// DATOS DE PHP PARA JAVASCRIPT
// ---------------------------------------------------------

const precios =
<?= json_encode($precios_js) ?>;


const distancias =
<?= json_encode($distancias) ?>;


// ---------------------------------------------------------
// FORMATO MONEDA
// ---------------------------------------------------------

function formatoMoneda(valor) {

    return new Intl.NumberFormat("es-AR", {

        style: "currency",

        currency: "ARS",

        minimumFractionDigits: 2

    }).format(valor);

}


// ---------------------------------------------------------
// ACTUALIZAR INFORMACIÓN DE DISTANCIA
// ---------------------------------------------------------

function actualizarDistancia() {

    const select =
        document.getElementById("distancia_id");

    const rango =
        document.getElementById("rango_dorsal");

    if (!select || !rango) {
        return;
    }


    const id =
        parseInt(select.value);


    if (!id) {

        rango.textContent = "";

        actualizarPrecio();

        return;
    }


    const distancia =
        distancias.find(
            d => parseInt(d.id) === id
        );


    if (!distancia) {

        rango.textContent = "";

        actualizarPrecio();

        return;
    }


    rango.textContent =
        "Dorsales disponibles: " +
        distancia.dorsal_desde +
        " a " +
        distancia.dorsal_hasta;


    actualizarPrecio();
}


// ---------------------------------------------------------
// ACTUALIZAR PRECIO
// ---------------------------------------------------------

function actualizarPrecio() {

    const select =
        document.getElementById("distancia_id");

    const precioElemento =
        document.getElementById("precio");

    const descuentoElemento =
        document.getElementById("descuento_monto");

    if (!select || !precioElemento) {
        return;
    }


    const distanciaId =
        select.value;


    const remera =
        document.querySelector(
            'input[name="remera"]:checked'
        );


    if (!distanciaId || !remera) {

        precioElemento.textContent =
            formatoMoneda(0);

        return;
    }


    // -----------------------------------------------------
    // TALLE
    // -----------------------------------------------------

    const campoTalle =
        document.getElementById("campo_talle");

    const talle =
        document.getElementById("talle_remera");


    if (remera.value === "1") {

        campoTalle.style.display =
            "block";

        talle.disabled =
            false;

    } else {

        campoTalle.style.display =
            "none";

        talle.disabled =
            true;

        talle.value =
            "";
    }


    // -----------------------------------------------------
    // PRECIO BASE
    // -----------------------------------------------------

    if (!precios[distanciaId]) {

        precioElemento.textContent =
            "Precio no configurado";

        return;
    }


    let precioBase;


    if (remera.value === "1") {

        precioBase =
            parseFloat(
                precios[distanciaId].con_remera
            );

    } else {

        precioBase =
            parseFloat(
                precios[distanciaId].sin_remera
            );
    }


    // -----------------------------------------------------
    // DESCUENTO
    // -----------------------------------------------------

    const descuento =
        parseFloat(
            descuentoElemento?.value || 0
        );


    let total =
        precioBase - descuento;


    if (total < 0) {
        total = 0;
    }


    precioElemento.textContent =
        formatoMoneda(total);
}


// ---------------------------------------------------------
// EVENTOS
// ---------------------------------------------------------

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const distancia =
            document.getElementById(
                "distancia_id"
            );


        if (distancia) {

            distancia.addEventListener(
                "change",
                actualizarDistancia
            );

        }


        document
            .querySelectorAll(
                'input[name="remera"]'
            )
            .forEach(
                elemento => {

                    elemento.addEventListener(
                        "change",
                        actualizarPrecio
                    );

                }
            );


        const descuento =
            document.getElementById(
                "descuento_monto"
            );


        if (descuento) {

            descuento.addEventListener(
                "input",
                actualizarPrecio
            );

        }


        actualizarDistancia();

    }
);

</script>


</body>

</html>