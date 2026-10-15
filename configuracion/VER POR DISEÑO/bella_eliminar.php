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

$id =
    isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

$error = "";

$categoria = null;

$inscripciones = 0;


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
// VERIFICAR USO EN INSCRIPCIONES
// =========================================================

try {

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM inscripciones
         WHERE categoria_id = ?
         AND evento_id = ?"
    );

    $stmt->execute([
        $id,
        $evento_id
    ]);

    $inscripciones =
        (int)$stmt->fetchColumn();


} catch (Throwable $ex) {

    $error =
        "ERROR VERIFICANDO INSCRIPCIONES: "
        . $ex->getMessage();
}


// =========================================================
// ELIMINAR
// =========================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $error === ""
) {

    // -----------------------------------------------------
    // SEGUNDA VERIFICACIÓN
    // -----------------------------------------------------

    try {

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM inscripciones
             WHERE categoria_id = ?
             AND evento_id = ?"
        );

        $stmt->execute([
            $id,
            $evento_id
        ]);

        $inscripciones =
            (int)$stmt->fetchColumn();


        if ($inscripciones > 0) {

            $error =
                "No se puede eliminar esta categoría porque tiene "
                . $inscripciones
                . " inscripción(es) asociada(s).";

        } else {

            // -------------------------------------------------
            // ELIMINAR
            // -------------------------------------------------

            $stmt = $pdo->prepare(
                "DELETE FROM categorias
                 WHERE id = ?
                 AND evento_id = ?"
            );

            $stmt->execute([
                $id,
                $evento_id
            ]);


            header(
                "Location: categorias.php?ok=eliminar"
            );

            exit;
        }


    } catch (Throwable $ex) {

        $error =
            "ERROR ELIMINANDO CATEGORÍA: "
            . $ex->getMessage();
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<title>Eliminar categoría</title>

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

.alerta {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
    padding: 18px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
}

.datos {
    background: #f5f5f5;
    padding: 18px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.acciones {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 20px;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    text-decoration: none;
    display: inline-block;
}

.btn-eliminar {
    background: #dc3545;
    color: #fff;
}

.btn-volver {
    background: #6c757d;
    color: #fff;
}

.campo {
    margin-bottom: 12px;
}

.etiqueta {
    font-weight: bold;
}

</style>

</head>

<body>

<?php require_once "../includes/menu.php"; ?>


<div class="contenedor">

<h1>Eliminar categoría</h1>


<?php if ($error !== ""): ?>

<div class="error">

<strong>No se puede eliminar:</strong><br>

<?= e($error) ?>

</div>

<?php endif; ?>


<div class="datos">

<div class="campo">

<span class="etiqueta">
Nombre:
</span>

<?= e($categoria["nombre"]) ?>

</div>


<div class="campo">

<span class="etiqueta">
Sexo:
</span>

<?php

if ($categoria["sexo"] === "M") {

    echo "Masculino";

} elseif ($categoria["sexo"] === "F") {

    echo "Femenino";

} else {

    echo "Ambos sexos";

}

?>

</div>


<div class="campo">

<span class="etiqueta">
Edad:
</span>

<?php

$min =
    $categoria["edad_min"];

$max =
    $categoria["edad_max"];


if ($min !== null && $max !== null) {

    echo e($min)
        . " a "
        . e($max)
        . " años";

} elseif ($min !== null) {

    echo "Desde "
        . e($min)
        . " años";

} elseif ($max !== null) {

    echo "Hasta "
        . e($max)
        . " años";

} else {

    echo "Sin límite de edad";

}

?>

</div>

</div>


<?php if ($inscripciones > 0): ?>

<div class="alerta">

<strong>
Esta categoría no puede eliminarse.
</strong>

<br><br>

Actualmente tiene:

<strong>
<?= e($inscripciones) ?>
</strong>

inscripción(es) asociada(s).

<br><br>

Esto evita perder información de las inscripciones existentes.

</div>


<div class="acciones">

<a
    href="categorias.php"
    class="btn btn-volver"
>
Volver a categorías
</a>

</div>


<?php else: ?>


<div class="alerta">

<strong>
¿Está seguro de eliminar esta categoría?
</strong>

<br><br>

Esta acción no se puede deshacer.

</div>


<form method="POST">

<div class="acciones">

<button
    type="submit"
    class="btn btn-eliminar"
>
Sí, eliminar categoría
</button>


<a
    href="categorias.php"
    class="btn btn-volver"
>
Cancelar
</a>

</div>

</form>


<?php endif; ?>


</div>

</body>

</html>