<?php
require_once "../config/database.php";

$evento_id = 1;

$mensaje = "";
$error = "";

$inscripcion = null;
$corredor = null;

$distancias = [];
$categorias = [];
$precios = [];
$pagos = [];

$pagado = 0;
$saldo = 0;
$edad_evento = null;
$fecha_evento = null;

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Busca la inscripción por DNI.
 */
function cargarInscripcion(PDO $pdo, int $evento_id, string $dni) {

    $stmt = $pdo->prepare("
        SELECT
            i.*,

            c.nombre,
            c.apellido,
            c.dni,
            c.sexo,
            c.fecha_nacimiento,
            c.email,
            c.telefono,

            d.nombre AS distancia_nombre,
            d.distancia_km,
            d.desnivel_m,
            d.dorsal_desde,
            d.dorsal_hasta,

            cat.nombre AS categoria_nombre

        FROM inscripciones i

        INNER JOIN corredores c
            ON c.id = i.corredor_id

        INNER JOIN distancias d
            ON d.id = i.distancia_id

        LEFT JOIN categorias cat
            ON cat.id = i.categoria_id

        WHERE i.evento_id = ?
          AND c.dni = ?

        LIMIT 1
    ");

    $stmt->execute([$evento_id, $dni]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Obtiene el total pagado.
 */
function obtenerPagado(PDO $pdo, int $inscripcion_id): float {

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(monto), 0)
        FROM pagos
        WHERE inscripcion_id = ?
    ");

    $stmt->execute([$inscripcion_id]);

    return (float)$stmt->fetchColumn();
}

/**
 * Calcula la edad en una fecha determinada.
 */
function calcularEdad($fecha_nacimiento, $fecha_evento) {

    if (empty($fecha_nacimiento) || empty($fecha_evento)) {
        return null;
    }

    try {

        $nacimiento = new DateTime($fecha_nacimiento);
        $evento = new DateTime($fecha_evento);

        return $nacimiento->diff($evento)->y;

    } catch (Exception $e) {
        return null;
    }
}

/**
 * Verifica que una categoría sea válida para:
 * - evento
 * - distancia
 * - sexo
 * - edad
 */
function categoriaValida(
    PDO $pdo,
    int $categoria_id,
    int $evento_id,
    int $distancia_id,
    string $sexo,
    $edad
) {

    $stmt = $pdo->prepare("
        SELECT
            cat.*
        FROM categorias cat

        INNER JOIN categoria_distancia cd
            ON cd.categoria_id = cat.id

        WHERE cat.id = ?
          AND cat.evento_id = ?
          AND cd.distancia_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $categoria_id,
        $evento_id,
        $distancia_id
    ]);

    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$categoria) {
        return false;
    }

    // Sexo
    if (!empty($categoria["sexo"]) && $categoria["sexo"] !== $sexo) {
        return false;
    }

    // Edad
    if ($edad !== null) {

        if ($categoria["edad_min"] !== null &&
            $edad < (int)$categoria["edad_min"]) {

            return false;
        }

        if ($categoria["edad_max"] !== null &&
            $edad > (int)$categoria["edad_max"]) {

            return false;
        }
    }

    return true;
}


/* =========================================================
   DATOS DEL EVENTO
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT *
    FROM eventos
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$evento_id]);

$evento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evento) {
    die("No existe el evento seleccionado.");
}

$fecha_evento = $evento["fecha"];


/* =========================================================
   BUSQUEDA
   ========================================================= */

$dni_busqueda = trim(
    $_GET["dni"] ??
    $_POST["dni"] ??
    ""
);


/* =========================================================
   ACCIONES POST
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";

    $dni_busqueda = trim($_POST["dni"] ?? "");

    /*
     * Buscar nuevamente la inscripción.
     */
    if ($dni_busqueda === "") {

        $error = "Debe ingresar un DNI.";

    } else {

        $inscripcion = cargarInscripcion(
            $pdo,
            $evento_id,
            $dni_busqueda
        );

        if (!$inscripcion) {
            $error = "No existe una inscripción para este DNI.";
        }
    }


    /* =====================================================
       GUARDAR ACREDITACION
       ===================================================== */

    if ($error === "" && $accion === "guardar") {

        $inscripcion_id = (int)$inscripcion["id"];

        $distancia_nueva =
            (int)($_POST["distancia_id"] ?? 0);

        $categoria_nueva =
            !empty($_POST["categoria_id"])
                ? (int)$_POST["categoria_id"]
                : null;

        $remera_nueva =
            (int)($_POST["remera"] ?? 1);

        $talle_nuevo =
            trim($_POST["talle_remera"] ?? "");

        $dorsal_solicitado =
            (int)($_POST["dorsal"] ?? 0);

        $descuento_nuevo =
            max(
                0,
                (float)($_POST["descuento_monto"] ?? 0)
            );

        $motivo_nuevo =
            trim($_POST["descuento_motivo"] ?? "");


        /* ---------------------------------------------
           VALIDACIONES BASICAS
        --------------------------------------------- */

        if ($distancia_nueva <= 0) {

            $error = "Debe seleccionar una distancia.";

        } elseif (
            $remera_nueva === 1 &&
            $talle_nuevo === ""
        ) {

            $error = "Debe seleccionar el talle de remera.";

        } elseif ($remera_nueva === 0) {

            $talle_nuevo = null;
        }


        /* ---------------------------------------------
           DATOS DEL CORREDOR
        --------------------------------------------- */

        $edad = calcularEdad(
            $inscripcion["fecha_nacimiento"],
            $fecha_evento
        );

        $sexo_corredor =
            strtoupper(trim($inscripcion["sexo"] ?? ""));


        /* ---------------------------------------------
           DISTANCIA
        --------------------------------------------- */

        $distancia_nueva_datos = null;

        if ($error === "") {

            $stmt = $pdo->prepare("
                SELECT *
                FROM distancias
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$distancia_nueva]);

            $distancia_nueva_datos =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$distancia_nueva_datos) {

                $error =
                    "La distancia seleccionada no existe.";

            } elseif (
                $distancia_nueva_datos["dorsal_desde"] === null ||
                $distancia_nueva_datos["dorsal_hasta"] === null
            ) {

                $error =
                    "La distancia no tiene configurado el rango de dorsales.";
            }
        }


        /* ---------------------------------------------
           VALIDAR CATEGORIA
        --------------------------------------------- */

        if (
            $error === "" &&
            $categoria_nueva !== null
        ) {

            if (
                !categoriaValida(
                    $pdo,
                    $categoria_nueva,
                    $evento_id,
                    $distancia_nueva,
                    $sexo_corredor,
                    $edad
                )
            ) {

                $error =
                    "La categoría seleccionada no corresponde a la distancia, sexo o edad del corredor.";
            }
        }


        /* ---------------------------------------------
           PRECIO
        --------------------------------------------- */

        $precio_base = 0;

        if ($error === "") {

            $stmt = $pdo->prepare("
                SELECT *
                FROM precios_inscripcion
                WHERE periodo_id = ?
                  AND distancia_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                (int)$inscripcion["periodo_id"],
                $distancia_nueva
            ]);

            $precio =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$precio) {

                $error =
                    "No hay un precio configurado para la nueva distancia en el período original de inscripción.";

            } else {

                $precio_base =
                    $remera_nueva === 1
                        ? (float)$precio["precio_con_remera"]
                        : (float)$precio["precio_sin_remera"];

                if ($descuento_nuevo > $precio_base) {
                    $descuento_nuevo = $precio_base;
                }
            }
        }


        /* ---------------------------------------------
           DORSAL
        --------------------------------------------- */

        $dorsal_nuevo =
            $dorsal_solicitado > 0
                ? $dorsal_solicitado
                : (int)$inscripcion["dorsal"];


        if ($error === "") {

            $desde =
                (int)$distancia_nueva_datos["dorsal_desde"];

            $hasta =
                (int)$distancia_nueva_datos["dorsal_hasta"];


            /*
             * Si el dorsal actual no sirve,
             * buscar uno automáticamente.
             */

            if (
                $dorsal_nuevo < $desde ||
                $dorsal_nuevo > $hasta
            ) {

                $dorsal_nuevo = 0;

                for (
                    $n = $desde;
                    $n <= $hasta;
                    $n++
                ) {

                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM inscripciones
                        WHERE evento_id = ?
                          AND dorsal = ?
                          AND id <> ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        $evento_id,
                        $n,
                        $inscripcion_id
                    ]);

                    if (!$stmt->fetch()) {

                        $dorsal_nuevo = $n;
                        break;
                    }
                }

                if ($dorsal_nuevo === 0) {

                    $error =
                        "No hay dorsales libres para la nueva distancia.";
                }
            }
        }


        /* ---------------------------------------------
           VERIFICAR DORSAL
        --------------------------------------------- */

        if ($error === "") {

            $stmt = $pdo->prepare("
                SELECT id
                FROM inscripciones
                WHERE evento_id = ?
                  AND dorsal = ?
                  AND id <> ?
                LIMIT 1
            ");

            $stmt->execute([
                $evento_id,
                $dorsal_nuevo,
                $inscripcion_id
            ]);

            if ($stmt->fetch()) {

                $error =
                    "El dorsal $dorsal_nuevo ya está asignado a otro corredor.";
            }
        }


        /* ---------------------------------------------
           TOTAL
        --------------------------------------------- */

        $total_nuevo = 0;

        if ($error === "") {

            $total_nuevo =
                max(
                    0,
                    $precio_base - $descuento_nuevo
                );

            $pagado_actual =
                obtenerPagado(
                    $pdo,
                    $inscripcion_id
                );

            if ($total_nuevo < $pagado_actual) {

                $error =
                    "El nuevo total ($" .
                    number_format(
                        $total_nuevo,
                        2,
                        ',',
                        '.'
                    ) .
                    ") no puede ser menor que lo ya pagado ($" .
                    number_format(
                        $pagado_actual,
                        2,
                        ',',
                        '.'
                    ) .
                    ").";
            }
        }


        /* ---------------------------------------------
           GUARDAR
        --------------------------------------------- */

        if ($error === "") {

            try {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE inscripciones
                    SET
                        distancia_id = ?,
                        categoria_id = ?,
                        dorsal = ?,
                        remera = ?,
                        talle_remera = ?,
                        precio_base = ?,
                        descuento_monto = ?,
                        descuento_motivo = ?,
                        total = ?,
                        estado = 'acreditado'
                    WHERE id = ?
                      AND evento_id = ?
                ");

                $stmt->execute([
                    $distancia_nueva,
                    $categoria_nueva,
                    $dorsal_nuevo,
                    $remera_nueva,
                    $talle_nuevo,
                    $precio_base,
                    $descuento_nuevo,
                    $motivo_nuevo !== ""
                        ? $motivo_nuevo
                        : null,
                    $total_nuevo,
                    $inscripcion_id,
                    $evento_id
                ]);

                $pdo->commit();

                $mensaje =
                    "Inscripción actualizada y acreditación registrada correctamente.";

            } catch (Throwable $ex) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error =
                    "No se pudieron guardar los cambios: " .
                    $ex->getMessage();
            }
        }
    }


    /* =====================================================
       REGISTRAR PAGO
       ===================================================== */

    if ($error === "" && $accion === "pago") {

        $inscripcion_id =
            (int)$inscripcion["id"];

        $monto =
            (float)($_POST["monto"] ?? 0);

        $medio_pago =
            trim($_POST["medio_pago"] ?? "");

        $comprobante =
            trim($_POST["comprobante"] ?? "");

        $observacion =
            trim($_POST["observacion"] ?? "");


        if ($monto <= 0) {

            $error =
                "El monto del pago debe ser mayor a cero.";

        } else {

            $pagado_actual =
                obtenerPagado(
                    $pdo,
                    $inscripcion_id
                );

            $saldo_actual =
                max(
                    0,
                    (float)$inscripcion["total"]
                    - $pagado_actual
                );

            if (
                $monto > $saldo_actual &&
                $saldo_actual > 0
            ) {

                $error =
                    "El pago supera el saldo pendiente ($" .
                    number_format(
                        $saldo_actual,
                        2,
                        ',',
                        '.'
                    ) .
                    ").";
            }
        }


        if ($error === "") {

            $stmt = $pdo->prepare("
                INSERT INTO pagos
                (
                    inscripcion_id,
                    fecha,
                    monto,
                    medio_pago,
                    comprobante,
                    observacion
                )
                VALUES
                (?, NOW(), ?, ?, ?, ?)
            ");

            $stmt->execute([
                $inscripcion_id,
                $monto,
                $medio_pago !== ""
                    ? $medio_pago
                    : null,
                $comprobante !== ""
                    ? $comprobante
                    : null,
                $observacion !== ""
                    ? $observacion
                    : null
            ]);

            $mensaje =
                "Pago registrado correctamente.";
        }
    }
}


/* =========================================================
   RECARGAR INSCRIPCION
   ========================================================= */

if ($dni_busqueda !== "") {

    $inscripcion =
        cargarInscripcion(
            $pdo,
            $evento_id,
            $dni_busqueda
        );
}


/* =========================================================
   CARGAR DATOS
   ========================================================= */

if ($inscripcion) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM corredores
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        (int)$inscripcion["corredor_id"]
    ]);

    $corredor =
        $stmt->fetch(PDO::FETCH_ASSOC);


    $edad_evento =
        calcularEdad(
            $corredor["fecha_nacimiento"] ?? null,
            $fecha_evento
        );


    $pagado =
        obtenerPagado(
            $pdo,
            (int)$inscripcion["id"]
        );

    $saldo =
        max(
            0,
            (float)$inscripcion["total"] -
            $pagado
        );


    /* ---------------------------------------------
       DISTANCIAS
    --------------------------------------------- */

    $stmt = $pdo->query("
        SELECT *
        FROM distancias
        ORDER BY distancia_km
    ");

    $distancias =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* ---------------------------------------------
       CATEGORIAS
       Se cargan con las distancias asociadas.
    --------------------------------------------- */

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            cat.id,
            cat.nombre,
            cat.sexo,
            cat.edad_min,
            cat.edad_max,
            cd.distancia_id
        FROM categorias cat

        INNER JOIN categoria_distancia cd
            ON cd.categoria_id = cat.id

        WHERE cat.evento_id = ?

        ORDER BY
            cat.sexo,
            cat.edad_min,
            cat.nombre
    ");

    $stmt->execute([$evento_id]);

    $categorias =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* ---------------------------------------------
       PRECIOS
    --------------------------------------------- */

    $stmt = $pdo->prepare("
        SELECT
            distancia_id,
            precio_con_remera,
            precio_sin_remera
        FROM precios_inscripcion
        WHERE periodo_id = ?
    ");

    $stmt->execute([
        (int)$inscripcion["periodo_id"]
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $fila
    ) {

        $precios[(int)$fila["distancia_id"]] = [
            "con_remera" =>
                (float)$fila["precio_con_remera"],

            "sin_remera" =>
                (float)$fila["precio_sin_remera"]
        ];
    }


    /* ---------------------------------------------
       PAGOS
    --------------------------------------------- */

    $stmt = $pdo->prepare("
        SELECT *
        FROM pagos
        WHERE inscripcion_id = ?
        ORDER BY fecha ASC, id ASC
    ");

    $stmt->execute([
        (int)$inscripcion["id"]
    ]);

    $pagos =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title>Acreditación</title>

<link
    rel="stylesheet"
    href="../css/estilos.css"
>

<style>

body{
    font-family:Arial,sans-serif;
    background:#f2f2f2;
    margin:0;
    padding:30px;
}

.contenedor{
    max-width:1000px;
    margin:auto;
    background:#fff;
    padding:30px;
    border-radius:10px;
}

h1{
    margin-top:0;
}

h2{
    margin-top:30px;
    border-bottom:1px solid #ddd;
    padding-bottom:8px;
}

.campo{
    margin-bottom:16px;
}

label{
    display:block;
    font-weight:bold;
    margin-bottom:6px;
}

input,
select,
textarea{
    width:100%;
    box-sizing:border-box;
    padding:10px;
    font-size:16px;
}

textarea{
    min-height:70px;
}

.btn{
    padding:12px 20px;
    border:0;
    border-radius:6px;
    cursor:pointer;
    font-size:16px;
    background:#222;
    color:#fff;
}

.mensaje,
.error,
.estado{
    padding:12px;
    margin-bottom:20px;
    border-radius:6px;
}

.mensaje{
    background:#d4edda;
    color:#155724;
}

.error{
    background:#f8d7da;
    color:#721c24;
}

.estado{
    background:#e8f4ea;
}

.datos-corredor{
    background:#f5f5f5;
    padding:15px;
    border-radius:8px;
    margin-bottom:20px;
}

.grid{
    display:grid;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
    gap:15px;
}

.resumen{
    display:grid;
    grid-template-columns:
        repeat(5,minmax(0,1fr));
    gap:12px;
    margin:20px 0;
}

.tarjeta{
    background:#f5f5f5;
    padding:15px;
    border-radius:8px;
}

.tarjeta strong{
    display:block;
    font-size:20px;
    margin-top:5px;
}

.saldo{
    background:#fff3cd;
}

.pagos{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}

.pagos th,
.pagos td{
    border:1px solid #ddd;
    padding:8px;
    text-align:left;
}

.pagos th{
    background:#f5f5f5;
}

.rojo{
    color:#a00;
}

.verde{
    color:#176b2c;
}

.amarillo{
    background:#fff3cd;
    padding:12px;
    border-radius:6px;
}

.edad{
    background:#e8f0ff;
    padding:12px;
    border-radius:6px;
    margin-top:10px;
}

.acciones-secundarias{
    margin-top:25px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.btn-secundario{
    display:inline-block;
    padding:10px 15px;
    background:#555;
    color:#fff;
    text-decoration:none;
    border-radius:6px;
}

.btn-peligro{
    display:inline-block;
    padding:10px 15px;
    background:#a00;
    color:#fff;
    text-decoration:none;
    border-radius:6px;
}

@media(max-width:700px){

    .grid,
    .resumen{
        grid-template-columns:1fr;
    }

}

</style>

</head>

<body>

<?php require_once "../includes/menu.php"; ?>

<div class="contenedor">

<h1>Acreditación</h1>

<?php if ($mensaje !== ""): ?>

<div class="mensaje">
    <?= e($mensaje) ?>
</div>

<?php endif; ?>


<?php if ($error !== ""): ?>

<div class="error">
    <?= e($error) ?>
</div>

<?php endif; ?>


<!-- =====================================================
     BUSQUEDA
===================================================== -->

<form method="GET">

<div class="campo">

<label>DNI</label>

<input
    type="text"
    name="dni"
    value="<?= e($dni_busqueda) ?>"
    autofocus
    required
>

</div>

<button
    class="btn"
    type="submit"
>
    Buscar inscripción
</button>

</form>


<?php if (
    $dni_busqueda !== "" &&
    !$inscripcion &&
    $error === ""
): ?>

<div class="error">
    No existe una inscripción para este DNI.
</div>

<?php endif; ?>


<?php if ($inscripcion): ?>


<!-- =====================================================
     DATOS DEL CORREDOR
===================================================== -->

<hr>

<div class="datos-corredor">

<strong>
    <?= e($inscripcion["apellido"]) ?>,
    <?= e($inscripcion["nombre"]) ?>
</strong>

<br>

DNI:
<?= e($inscripcion["dni"]) ?>

<?php if (!empty($inscripcion["sexo"])): ?>

<br>
Sexo:
<?= e($inscripcion["sexo"]) ?>

<?php endif; ?>

<?php if (!empty($inscripcion["fecha_nacimiento"])): ?>

<br>
Fecha de nacimiento:
<?= e($inscripcion["fecha_nacimiento"]) ?>

<?php endif; ?>

<?php if ($edad_evento !== null): ?>

<div class="edad">

<strong>
Edad al día de la carrera:
<?= e($edad_evento) ?> años
</strong>

<br>

Fecha del evento:
<?= e($fecha_evento) ?>

</div>

<?php endif; ?>

<?php if (!empty($inscripcion["email"])): ?>

<br>
Email:
<?= e($inscripcion["email"]) ?>

<?php endif; ?>

</div>


<div class="estado">

Estado de inscripción:
<strong>
    <?= e($inscripcion["estado"]) ?>
</strong>

</div>


<!-- =====================================================
     RESUMEN
===================================================== -->

<div class="resumen">

<div class="tarjeta">
    Dorsal
    <strong>
        <?= e($inscripcion["dorsal"]) ?>
    </strong>
</div>

<div class="tarjeta">
    Distancia
    <strong>
        <?= e($inscripcion["distancia_km"]) ?> km
    </strong>
</div>

<div class="tarjeta">
    Categoría
    <strong>
        <?= e($inscripcion["categoria_nombre"] ?? "Sin categoría") ?>
    </strong>
</div>

<div class="tarjeta">
    Total
    <strong>
        $<?= number_format(
            (float)$inscripcion["total"],
            2,
            ',',
            '.'
        ) ?>
    </strong>
</div>

<div class="tarjeta saldo">
    Saldo
    <strong
        class="<?= $saldo > 0
            ? 'rojo'
            : 'verde' ?>"
    >
        $<?= number_format(
            $saldo,
            2,
            ',',
            '.'
        ) ?>
    </strong>
</div>

</div>


<!-- =====================================================
     MODIFICAR / ACREDITAR
===================================================== -->

<h2>
    Revisar / modificar inscripción
</h2>

<form method="POST">

<input
    type="hidden"
    name="accion"
    value="guardar"
>

<input
    type="hidden"
    name="dni"
    value="<?= e($dni_busqueda) ?>"
>


<div class="grid">


<div class="campo">

<label>Distancia</label>

<select
    name="distancia_id"
    id="distancia_id"
    required
>

<?php foreach ($distancias as $d): ?>

<option
    value="<?= e($d["id"]) ?>"
    <?= (int)$d["id"] ===
        (int)$inscripcion["distancia_id"]
        ? 'selected'
        : '' ?>
>

<?= e($d["nombre"]) ?>
-
<?= e($d["distancia_km"]) ?> km

</option>

<?php endforeach; ?>

</select>

<small id="rango"></small>

</div>


<div class="campo">

<label>
    Categoría
</label>

<select
    name="categoria_id"
    id="categoria_id"
>

<option value="">
    Sin categoría
</option>

<?php foreach ($categorias as $c): ?>

<option
    value="<?= e($c["id"]) ?>"
    data-distancia="<?= e($c["distancia_id"]) ?>"
    data-sexo="<?= e($c["sexo"]) ?>"
    data-edad-min="<?= e($c["edad_min"]) ?>"
    data-edad-max="<?= e($c["edad_max"]) ?>"
    <?= (int)$c["id"] ===
        (int)$inscripcion["categoria_id"]
        ? 'selected'
        : '' ?>
>

<?= e($c["nombre"]) ?>

</option>

<?php endforeach; ?>

</select>

<small>
    Se muestran las categorías válidas para la distancia y sexo.
</small>

</div>


<div class="campo">

<label>Remera</label>

<select
    name="remera"
    id="remera"
>

<option
    value="1"
    <?= (int)$inscripcion["remera"] === 1
        ? 'selected'
        : '' ?>
>
    Con remera
</option>

<option
    value="0"
    <?= (int)$inscripcion["remera"] === 0
        ? 'selected'
        : '' ?>
>
    Sin remera
</option>

</select>

</div>


<div class="campo">

<label>Talle</label>

<select
    name="talle_remera"
    id="talle"
>

<option value="">
    Sin talle
</option>

<?php foreach (
    ['XS','S','M','L','XL','XXL']
    as $t
): ?>

<option
    value="<?= $t ?>"
    <?= $inscripcion["talle_remera"] === $t
        ? 'selected'
        : '' ?>
>

<?= $t ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="campo">

<label>Dorsal</label>

<input
    type="number"
    name="dorsal"
    id="dorsal"
    value="<?= e($inscripcion["dorsal"]) ?>"
>

<small>
    Si queda fuera del rango,
    se asignará automáticamente uno libre.
</small>

</div>


<div class="campo">

<label>Descuento</label>

<input
    type="number"
    name="descuento_monto"
    id="descuento"
    value="<?= e($inscripcion["descuento_monto"]) ?>"
    min="0"
    step="0.01"
>

</div>

</div>


<div class="campo">

<label>
    Motivo del descuento
</label>

<input
    type="text"
    name="descuento_motivo"
    value="<?= e(
        $inscripcion["descuento_motivo"] ?? ''
    ) ?>"
>

</div>


<div class="campo">

<label>
    Nuevo total
</label>

<strong id="nuevo_total">

$<?= number_format(
    (float)$inscripcion["total"],
    2,
    ',',
    '.'
) ?>

</strong>

</div>


<button
    class="btn"
    type="submit"
>

Guardar cambios y acreditar

</button>

</form>


<!-- =====================================================
     PAGO
===================================================== -->

<h2>
    Registrar pago
</h2>

<?php if ($saldo > 0): ?>

<form method="POST">

<input
    type="hidden"
    name="accion"
    value="pago"
>

<input
    type="hidden"
    name="dni"
    value="<?= e($dni_busqueda) ?>"
>


<div class="grid">


<div class="campo">

<label>Monto</label>

<input
    type="number"
    name="monto"
    min="0.01"
    max="<?= e($saldo) ?>"
    step="0.01"
    value="<?= e($saldo) ?>"
    required
>

</div>


<div class="campo">

<label>
    Medio de pago
</label>

<input
    type="text"
    name="medio_pago"
    placeholder="Efectivo, transferencia, etc."
>

</div>


<div class="campo">

<label>
    Comprobante
</label>

<input
    type="text"
    name="comprobante"
>

</div>


<div class="campo">

<label>
    Observación
</label>

<input
    type="text"
    name="observacion"
>

</div>

</div>


<button
    class="btn"
    type="submit"
>

Registrar pago

</button>

</form>

<?php else: ?>

<div class="estado">
    Pago completo.
</div>

<?php endif; ?>


<!-- =====================================================
     HISTORIAL PAGOS
===================================================== -->

<h2>
    Historial de pagos
</h2>

<?php if (!$pagos): ?>

<p>
    No hay pagos registrados.
</p>

<?php else: ?>

<table class="pagos">

<thead>

<tr>
    <th>Fecha</th>
    <th>Monto</th>
    <th>Medio</th>
    <th>Comprobante</th>
    <th>Observación</th>
</tr>

</thead>

<tbody>

<?php foreach ($pagos as $p): ?>

<tr>

<td>
    <?= e($p["fecha"]) ?>
</td>

<td>
    $<?= number_format(
        (float)$p["monto"],
        2,
        ',',
        '.'
    ) ?>
</td>

<td>
    <?= e($p["medio_pago"]) ?>
</td>

<td>
    <?= e($p["comprobante"]) ?>
</td>

<td>
    <?= e($p["observacion"]) ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endif; ?>


<!-- =====================================================
     ACCIONES
===================================================== -->

<div class="acciones-secundarias">

<a
    class="btn-secundario"
    href="inscripcion_modificar.php?id=<?= (int)$inscripcion["id"] ?>"
>
    Modificar inscripción
</a>

<a
    class="btn-peligro"
    href="inscripcion_eliminar.php?id=<?= (int)$inscripcion["id"] ?>"
>
    Eliminar inscripción
</a>

</div>


<?php endif; ?>

</div>


<script>

/* =========================================================
   DATOS PHP
========================================================= */

const precios =
    <?= json_encode($precios) ?>;

const distancias =
    <?= json_encode($distancias) ?>;

const categorias =
    <?= json_encode($categorias) ?>;

const sexoCorredor =
    <?= json_encode(
        strtoupper(trim($inscripcion["sexo"] ?? ""))
    ) ?>;

const edadCorredor =
    <?= $edad_evento !== null
        ? (int)$edad_evento
        : 'null' ?>;

const categoriaActual =
    <?= $inscripcion
        ? (int)$inscripcion["categoria_id"]
        : 0 ?>;


/* =========================================================
   MONEDA
========================================================= */

function moneda(v){

    return new Intl.NumberFormat(
        'es-AR',
        {
            style:'currency',
            currency:'ARS'
        }
    ).format(v);
}


/* =========================================================
   ACTUALIZAR CATEGORIAS
========================================================= */

function actualizarCategorias(){

    const distancia =
        document.getElementById(
            'distancia_id'
        );

    const categoria =
        document.getElementById(
            'categoria_id'
        );

    if (!distancia || !categoria) {
        return;
    }

    const distanciaId =
        parseInt(distancia.value);


    const valorAnterior =
        parseInt(categoria.value || 0);


    categoria.innerHTML =
        '<option value="">Sin categoría</option>';


    let encontrada = false;


    categorias.forEach(function(c){

        const distanciaCorrecta =
            parseInt(c.distancia_id) ===
            distanciaId;


        const sexoCorrecto =
            !c.sexo ||
            c.sexo === '' ||
            c.sexo === sexoCorredor;


        let edadCorrecta = true;


        if (edadCorredor !== null) {

            if (
                c.edad_min !== null &&
                c.edad_min !== '' &&
                edadCorredor <
                parseInt(c.edad_min)
            ) {

                edadCorrecta = false;
            }


            if (
                c.edad_max !== null &&
                c.edad_max !== '' &&
                edadCorredor >
                parseInt(c.edad_max)
            ) {

                edadCorrecta = false;
            }
        }


        if (
            distanciaCorrecta &&
            sexoCorrecto &&
            edadCorrecta
        ){

            const option =
                document.createElement('option');

            option.value = c.id;

            option.textContent =
                c.nombre;


            /*
             * Si estamos cargando la inscripción
             * actual, conservar su categoría.
             */

            if (
                parseInt(c.id) ===
                categoriaActual
            ){

                option.selected = true;

                encontrada = true;
            }


            categoria.appendChild(option);
        }

    });


    /*
     * Si la categoría anterior ya no es válida,
     * buscar automáticamente la categoría por edad.
     */

    if (!encontrada && edadCorredor !== null){

        const opciones =
            Array.from(
                categoria.options
            );

        for (
            let i = 1;
            i < opciones.length;
            i++
        ){

            const option =
                opciones[i];

            const c =
                categorias.find(
                    x =>
                        parseInt(x.id) ===
                        parseInt(option.value)
                );

            if (!c) continue;


            const min =
                c.edad_min === null ||
                c.edad_min === ''
                    ? -Infinity
                    : parseInt(c.edad_min);


            const max =
                c.edad_max === null ||
                c.edad_max === ''
                    ? Infinity
                    : parseInt(c.edad_max);


            if (
                edadCorredor >= min &&
                edadCorredor <= max
            ){

                option.selected = true;

                break;
            }
        }
    }
}


/* =========================================================
   ACTUALIZAR DISTANCIA / PRECIO
========================================================= */

function actualizar(){

    const id =
        document.getElementById(
            'distancia_id'
        );

    if (!id) {
        return;
    }


    const d =
        distancias.find(
            x =>
                parseInt(x.id) ===
                parseInt(id.value)
        );


    document.getElementById(
        'rango'
    ).textContent =
        d
            ? 'Dorsales: ' +
              d.dorsal_desde +
              ' a ' +
              d.dorsal_hasta
            : '';


    const rem =
        document.getElementById(
            'remera'
        ).value;


    document.getElementById(
        'talle'
    ).disabled =
        rem === '0';


    if (!precios[id.value]) {
        return;
    }


    let base =
        rem === '1'
            ? parseFloat(
                precios[id.value].con_remera
            )
            : parseFloat(
                precios[id.value].sin_remera
            );


    let descuento =
        parseFloat(
            document.getElementById(
                'descuento'
            ).value || 0
        );


    document.getElementById(
        'nuevo_total'
    ).textContent =
        moneda(
            Math.max(
                0,
                base - descuento
            )
        );
}


/* =========================================================
   EVENTOS
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function(){

        const distancia =
            document.getElementById(
                'distancia_id'
            );

        const remera =
            document.getElementById(
                'remera'
            );

        const descuento =
            document.getElementById(
                'descuento'
            );


        if (distancia){

            distancia.addEventListener(
                'change',
                function(){

                    actualizarCategorias();
                    actualizar();

                }
            );
        }


        if (remera){

            remera.addEventListener(
                'change',
                actualizar
            );
        }


        if (descuento){

            descuento.addEventListener(
                'input',
                actualizar
            );
        }


        actualizarCategorias();
        actualizar();

    }
);

</script>

</body>
</html>