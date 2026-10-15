<?php

// =========================================================
// MODO DIAGNÓSTICO
// =========================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');


// =========================================================
// CONEXIÓN
// =========================================================

try {

    require_once "../config/database.php";

} catch (Throwable $ex) {

    die(
        '<div style="
            background:#f8d7da;
            color:#721c24;
            padding:20px;
            margin:20px;
            font-family:Arial;
            border-radius:8px;
        ">
        <h2>Error cargando database.php</h2>
        <strong>' .
        htmlspecialchars(
            $ex->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
        . '</strong>
        </div>'
    );
}


if (!isset($pdo)) {

    die("Error: no existe \$pdo.");

}


// =========================================================
// VARIABLES
// =========================================================

$evento_id = 1;

$id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : (int)($_POST["id"] ?? 0);

$nombre = "";
$sexo = "";
$edad_min = "";
$edad_max = "";

$error = "";


// =========================================================
// FUNCIÓN ESCAPE
// =========================================================

function e($valor)
{
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


// =========================================================
// VALIDAR ID
// =========================================================

if ($id <= 0) {

    die(
        '<div style="
            padding:30px;
            font-family:Arial;
        ">
        <h2>ID de categoría inválido.</h2>
        <a href="categorias.php">
            Volver a categorías
        </a>
        </div>'
    );
}


// =========================================================
// CARGAR CATEGORÍA
// =========================================================

try {

    $stmt = $pdo->prepare(
        "SELECT *
         FROM categorias
         WHERE id = ?
         AND evento_id = ?
         LIMIT 1"
    );

    $stmt->execute([
        $id,
        $evento_id
    ]);

    $categoria =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$categoria) {

        die(
            '<div style="
                padding:30px;
                font-family:Arial;
            ">
            <h2>La categoría no existe.</h2>
            <a href="categorias.php">
                Volver a categorías
            </a>
            </div>'
        );
    }


    $nombre =
        $categoria["nombre"];

    $sexo =
        $categoria["sexo"] ?? "";

    $edad_min =
        $categoria["edad_min"] !== null
        ? $categoria["edad_min"]
        : "";

    $edad_max =
        $categoria["edad_max"] !== null
        ? $categoria["edad_max"]
        : "";


} catch (Throwable $ex) {

    die(
        '<div style="
            padding:30px;
            font-family:Arial;
        ">
        <h2>Error cargando categoría</h2>
        ' .
        e($ex->getMessage())
        .
        '</div>'
    );
}


// =========================================================
// GUARDAR CAMBIOS
// =========================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre =
        trim($_POST["nombre"] ?? "");

    $sexo =
        trim($_POST["sexo"] ?? "");

    $edad_min =
        trim($_POST["edad_min"] ?? "");

    $edad_max =
        trim($_POST["edad_max"] ?? "");


    // -----------------------------------------------------
    // VALIDACIONES
    // -----------------------------------------------------

    if ($nombre === "") {

        $error =
            "Debe ingresar el nombre de la categoría.";

    } elseif (strlen($nombre) > 50) {

        $error =
            "El nombre no puede superar los 50 caracteres.";

    } elseif (
        $sexo !== "" &&
        $sexo !== "M" &&
        $sexo !== "F"
    ) {

        $error =
            "El sexo seleccionado no es válido.";

    }


    if ($error === "" && $edad_min !== "") {

        if (
            !ctype_digit($edad_min) ||
            (int)$edad_min < 0 ||
            (int)$edad_min > 120
        ) {

            $error =
                "La edad mínima no es válida.";
        }
    }


    if ($error === "" && $edad_max !== "") {

        if (
            !ctype_digit($edad_max) ||
            (int)$edad_max < 0 ||
            (int)$edad_max > 120
        ) {

            $error =
                "La edad máxima no es válida.";
        }
    }


    if (
        $error === "" &&
        $edad_min !== "" &&
        $edad_max !== ""
    ) {

        if ((int)$edad_min > (int)$edad_max) {

            $error =
                "La edad mínima no puede ser mayor que la edad máxima.";
        }
    }


    // -----------------------------------------------------
    // VERIFICAR DUPLICADO
    // -----------------------------------------------------

    if ($error === "") {

        try {

            if ($sexo === "") {

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM categorias
                     WHERE evento_id = ?
                     AND nombre = ?
                     AND sexo IS NULL
                     AND id <> ?
                     LIMIT 1"
                );

                $stmt->execute([
                    $evento_id,
                    $nombre,
                    $id
                ]);

            } else {

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM categorias
                     WHERE evento_id = ?
                     AND nombre = ?
                     AND sexo = ?
                     AND id <> ?
                     LIMIT 1"
                );

                $stmt->execute([
                    $evento_id,
                    $nombre,
                    $sexo,
                    $id
                ]);
            }


            if ($stmt->fetch()) {

                $error =
                    "Ya existe otra categoría con ese nombre y sexo.";
            }


        } catch (Throwable $ex) {

            $error =
                "ERROR VERIFICANDO CATEGORÍA: "
                . $ex->getMessage();
        }
    }


    // -----------------------------------------------------
    // ACTUALIZAR
    // -----------------------------------------------------

    if ($error === "") {

        try {

            $stmt = $pdo->prepare(
                "UPDATE categorias
                 SET
                    nombre = ?,
                    sexo = ?,
                    edad_min = ?,
                    edad_max = ?
                 WHERE id = ?
                 AND evento_id = ?"
            );


            $stmt->execute([

                $nombre,

                $sexo !== ""
                    ? $sexo
                    : null,

                $edad_min !== ""
                    ? (int)$edad_min
                    : null,

                $edad_max !== ""
                    ? (int)$edad_max
                    : null,

                $id,

                $evento_id

            ]);


            header(
                "Location: categorias.php?ok=modificar"
            );

            exit;


        } catch (Throwable $ex) {

            $error =
                "ERROR MODIFICANDO CATEGORÍA: "
                . $ex->getMessage();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<title>Modificar categoría</title>

<link
    rel="stylesheet"
    href="../css/estilos.css"
>

<style>

body {
    font-family: Arial, sans-serif;
    background: #f2f2f2;
    margin: 0;
    padding: 30px;
}

.contenedor {
    max-width: 700px;
    margin: auto;
    background: #fff;
    padding: 30px;
    border-radius: 10px;
}

h1 {
    margin-top: 0;
}

.campo {
    margin-bottom: 18px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 6px;
}

input,
select {
    width: 100%;
    box-sizing: border-box;
    padding: 10px;
    font-size: 16px;
}

button,
.btn {
    padding: 12px 20px;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
}

.btn {
    text-decoration: none;
    display: inline-block;
}

.btn-principal {
    background: #222;
    color: #fff;
}

.btn-volver {
    background: #6c757d;
    color: #fff;
}

.mensaje,
.error,
.info {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.info {
    background: #d9edf7;
    color: #31708f;
}

.ayuda {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}

.acciones {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

</style>

</head>

<body>

<?php require_once "../includes/menu.php"; ?>


<div class="contenedor">

<h1>Modificar categoría</h1>


<div class="info">

Categoría ID:
<strong><?= e($id) ?></strong>

<br>

Evento:
<strong>#<?= e($evento_id) ?></strong>

</div>


<?php if ($error !== ""): ?>

<div class="error">

<strong>Se produjo un error:</strong><br>

<?= e($error) ?>

</div>

<?php endif; ?>


<form method="POST">

<input
    type="hidden"
    name="id"
    value="<?= e($id) ?>"
>


<div class="campo">

<label>
Nombre
</label>

<input
    type="text"
    name="nombre"
    value="<?= e($nombre) ?>"
    maxlength="50"
    required
    autofocus
>

</div>


<div class="campo">

<label>
Sexo
</label>

<select name="sexo">

<option
    value=""
    <?= $sexo === "" ? "selected" : "" ?>
>
Ambos sexos
</option>

<option
    value="M"
    <?= $sexo === "M" ? "selected" : "" ?>
>
Masculino
</option>

<option
    value="F"
    <?= $sexo === "F" ? "selected" : "" ?>
>
Femenino
</option>

</select>

</div>


<div
    style="
        display:grid;
        grid-template-columns:
        repeat(2,minmax(0,1fr));
        gap:15px;
    "
>


<div class="campo">

<label>
Edad mínima
</label>

<input
    type="number"
    name="edad_min"
    value="<?= e($edad_min) ?>"
    min="0"
    max="120"
>

</div>


<div class="campo">

<label>
Edad máxima
</label>

<input
    type="number"
    name="edad_max"
    value="<?= e($edad_max) ?>"
    min="0"
    max="120"
>

</div>


</div>


<div class="acciones">

<button
    type="submit"
    class="btn btn-principal"
>
Guardar cambios
</button>


<a
    href="categorias.php"
    class="btn btn-volver"
>
Cancelar
</a>

</div>


</form>

</div>

</body>

</html>