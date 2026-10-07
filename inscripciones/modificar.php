<?php
require_once "../config/database.php";

$evento_id = 1;

$id = (int)($_GET["id"] ?? $_POST["id"] ?? 0);

if ($id <= 0) {
    die("Inscripción no válida.");
}

$mensaje = "";
$error = "";

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function calcularEdad($fecha_nacimiento, $fecha_evento) {

    if (!$fecha_nacimiento || !$fecha_evento) {
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


/* =========================================================
   EVENTO
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
    die("No existe el evento.");
}


/* =========================================================
   INSCRIPCION
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        i.*,

        c.nombre,
        c.apellido,
        c.dni,
        c.sexo,
        c.fecha_nacimiento,
        c.email,
        c.telefono

    FROM inscripciones i

    INNER JOIN corredores c
        ON c.id = i.corredor_id

    WHERE i.id = ?
      AND i.evento_id = ?

    LIMIT 1
");

$stmt->execute([
    $id,
    $evento_id
]);

$inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inscripcion) {
    die("No existe la inscripción.");
}


$edad = calcularEdad(
    $inscripcion["fecha_nacimiento"],
    $evento["fecha"]
);


/* =========================================================
   GUARDAR
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $distancia_id =
        (int)($_POST["distancia_id"] ?? 0);

    $categoria_id =
        !empty($_POST["categoria_id"])
            ? (int)$_POST["categoria_id"]
            : null;

    $remera =
        (int)($_POST["remera"] ?? 1);

    $talle =
        trim($_POST["talle_remera"] ?? "");

    $dorsal =
        (int)($_POST["dorsal"] ?? 0);

    $descuento =
        max(
            0,
            (float)($_POST["descuento_monto"] ?? 0)
        );

    $motivo =
        trim($_POST["descuento_motivo"] ?? "");


    if ($distancia_id <= 0) {

        $error =
            "Debe seleccionar una distancia.";

    } elseif (
        $remera === 1 &&
        $talle === ""
    ) {

        $error =
            "Debe seleccionar el talle de remera.";

    } elseif ($remera === 0) {

        $talle = null;
    }


    /* ---------------------------------------------
       DISTANCIA
    --------------------------------------------- */

    $distancia = null;

    if ($error === "") {

        $stmt = $pdo->prepare("
            SELECT *
            FROM distancias
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$distancia_id]);

        $distancia =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$distancia) {

            $error =
                "La distancia no existe.";
        }
    }


    /* ---------------------------------------------
       CATEGORIA
    --------------------------------------------- */

    if (
        $error === "" &&
        $categoria_id !== null
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

        $categoria =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$categoria) {

            $error =
                "La categoría no corresponde a la distancia seleccionada.";

        } else {

            if (
                !empty($categoria["sexo"]) &&
                $categoria["sexo"] !==
                $inscripcion["sexo"]
            ) {

                $error =
                    "La categoría no corresponde al sexo del corredor.";
            }


            if (
                $error === "" &&
                $edad !== null
            ) {

                if (
                    $categoria["edad_min"] !== null &&
                    $edad <
                    (int)$categoria["edad_min"]
                ) {

                    $error =
                        "La categoría no corresponde a la edad del corredor.";
                }


                if (
                    $categoria["edad_max"] !== null &&
                    $edad >
                    (int)$categoria["edad_max"]
                ) {

                    $error =
                        "La categoría no corresponde a la edad del corredor.";
                }
            }
        }
    }


    /* ---------------------------------------------
       DORSAL
    --------------------------------------------- */

    if ($error === "") {

        $desde =
            (int)$distancia["dorsal_desde"];

        $hasta =
            (int)$distancia["dorsal_hasta"];


        if (
            $dorsal < $desde ||
            $dorsal > $hasta
        ) {

            $error =
                "El dorsal debe estar entre $desde y $hasta.";
        }
    }


    /* ---------------------------------------------
       DORSAL REPETIDO
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
            $dorsal,
            $id
        ]);

        if ($stmt->fetch()) {

            $error =
                "El dorsal ya está asignado a otro corredor.";
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
            $distancia_id
        ]);

        $precio =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$precio) {

            $error =
                "No existe precio para esa distancia en el período original.";

        } else {

            $precio_base =
                $remera === 1
                    ? (float)$precio["precio_con_remera"]
                    : (float)$precio["precio_sin_remera"];

            if ($descuento > $precio_base) {
                $descuento = $precio_base;
            }
        }
    }


    $total =
        max(
            0,
            $precio_base - $descuento
        );


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
                    total = ?
                WHERE id = ?
                  AND evento_id = ?
            ");

            $stmt->execute([
                $distancia_id,
                $categoria_id,
                $dorsal,
                $remera,
                $talle,
                $precio_base,
                $descuento,
                $motivo !== "" ? $motivo : null,
                $total,
                $id,
                $evento_id
            ]);

            $pdo->commit();

            header(
                "Location: index.php?mensaje=" .
                urlencode("Inscripción modificada correctamente.")
            );

            exit;

        } catch (Throwable $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error =
                "No se pudo modificar: " .
                $ex->getMessage();
        }
    }
}


/* =========================================================
   DATOS PARA FORMULARIO
========================================================= */

$stmt = $pdo->query("
    SELECT *
    FROM distancias
    ORDER BY distancia_km
");

$distancias =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("
    SELECT DISTINCT
        cat.*,
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


/* Precios */

$precios = [];

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

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title>Modificar inscripción</title>

<link
    rel="stylesheet"
    href="../css/estilos.css"
>

<style>

body{
    font-family:Arial;
    background:#f2f2f2;
    margin:0;
    padding:30px;
}

.contenedor{
    max-width:900px;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:10px;
}

.grid{
    display:grid;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
    gap:15px;
}

.campo{
    margin-bottom:15px;
}

label{
    display:block;
    font-weight:bold;
    margin-bottom:6px;
}

input,
select{
    width:100%;
    box-sizing:border-box;
    padding:10px;
    font-size:16px;
}

.btn{
    padding:12px 20px;
    border:0;
    border-radius:6px;
    background:#222;
    color:#fff;
    cursor:pointer;
}

.error{
    background:#f8d7da;
    color:#721c24;
    padding:12px;
    border-radius:6px;
    margin-bottom:20px;
}

.datos{
    background:#f5f5f5;
    padding:15px;
    border-radius:8px;
    margin-bottom:20px;
}

@media(max-width:700px){
    .grid{
        grid-template-columns:1fr;
    }
}

</style>

</head>

<body>

<?php require_once "../includes/menu.php"; ?>

<div class="contenedor">

<h1>Modificar inscripción</h1>

<?php if ($error): ?>

<div class="error">
    <?= e($error) ?>
</div>

<?php endif; ?>


<div class="datos">

<strong>
    <?= e($inscripcion["apellido"]) ?>,
    <?= e($inscripcion["nombre"]) ?>
</strong>

<br>

DNI:
<?= e($inscripcion["dni"]) ?>

<br>

Edad al día del evento:
<strong>
    <?= $edad !== null
        ? e($edad) . " años"
        : "No disponible" ?>
</strong>

<br>

Evento:
<?= e($evento["nombre"]) ?>

<br>

Fecha:
<?= e($evento["fecha"]) ?>

</div>


<form method="POST">

<input
    type="hidden"
    name="id"
    value="<?= $id ?>"
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
        ? "selected"
        : "" ?>
>

<?= e($d["nombre"]) ?>
-
<?= e($d["distancia_km"]) ?> km

</option>

<?php endforeach; ?>

</select>

</div>


<div class="campo">

<label>Categoría</label>

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
        ? "selected"
        : "" ?>
>

<?= e($c["nombre"]) ?>

</option>

<?php endforeach; ?>

</select>

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
        ? "selected"
        : "" ?>
>
Con remera
</option>

<option
    value="0"
    <?= (int)$inscripcion["remera"] === 0
        ? "selected"
        : "" ?>
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
    ["XS","S","M","L","XL","XXL"]
    as $t
): ?>

<option
    value="<?= $t ?>"
    <?= $inscripcion["talle_remera"] === $t
        ? "selected"
        : "" ?>
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
    value="<?= e($inscripcion["dorsal"]) ?>"
    required
>

</div>


<div class="campo">

<label>Descuento</label>

<input
    type="number"
    name="descuento_monto"
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
        $inscripcion["descuento_motivo"] ?? ""
    ) ?>"
>

</div>


<button
    type="submit"
    class="btn"
>
    Guardar cambios
</button>

</form>

</div>

<script>

const categorias =
    <?= json_encode($categorias) ?>;

const sexo =
    <?= json_encode(
        strtoupper($inscripcion["sexo"])
    ) ?>;

const edad =
    <?= $edad !== null
        ? (int)$edad
        : "null" ?>;

const categoriaActual =
    <?= (int)$inscripcion["categoria_id"] ?>;


function filtrarCategorias(){

    const distancia =
        parseInt(
            document.getElementById(
                "distancia_id"
            ).value
        );

    const select =
        document.getElementById(
            "categoria_id"
        );

    select.innerHTML =
        '<option value="">Sin categoría</option>';


    let categoriaActualEncontrada =
        false;


    categorias.forEach(function(c){

        if (
            parseInt(c.distancia_id) !==
            distancia
        ) {
            return;
        }


        if (
            c.sexo &&
            c.sexo !== sexo
        ) {
            return;
        }


        if (edad !== null) {

            if (
                c.edad_min !== null &&
                edad < parseInt(c.edad_min)
            ) {
                return;
            }

            if (
                c.edad_max !== null &&
                edad > parseInt(c.edad_max)
            ) {
                return;
            }
        }


        const option =
            document.createElement(
                "option"
            );

        option.value = c.id;
        option.textContent = c.nombre;


        if (
            parseInt(c.id) ===
            categoriaActual
        ) {

            option.selected = true;
            categoriaActualEncontrada = true;
        }


        select.appendChild(option);
    });
}


document.addEventListener(
    "DOMContentLoaded",
    function(){

        document
            .getElementById("distancia_id")
            .addEventListener(
                "change",
                filtrarCategorias
            );

        filtrarCategorias();

    }
);

</script>

</body>
</html>