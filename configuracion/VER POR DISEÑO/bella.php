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

    die(
        '<div style="
            background:#f8d7da;
            color:#721c24;
            padding:20px;
            margin:20px;
            font-family:Arial;
            border-radius:8px;
        ">
        <h2>Error de conexión</h2>
        <p>El archivo database.php no creó la variable <strong>$pdo</strong>.</p>
        </div>'
    );
}


// =========================================================
// VARIABLES
// =========================================================

$evento_id = 1;

$categorias = [];

$mensaje = "";
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
// MENSAJES
// =========================================================

if (isset($_GET["ok"])) {

    switch ($_GET["ok"]) {

        case "crear":

            $mensaje =
                "La categoría fue creada correctamente.";

            break;

        case "modificar":

            $mensaje =
                "La categoría fue modificada correctamente.";

            break;

        case "eliminar":

            $mensaje =
                "La categoría fue eliminada correctamente.";

            break;
    }
}


// =========================================================
// CARGAR CATEGORÍAS
// =========================================================

try {

    $stmt = $pdo->prepare(
        "SELECT
            id,
            evento_id,
            nombre,
            sexo,
            edad_min,
            edad_max
         FROM categorias
         WHERE evento_id = ?
         ORDER BY
            CASE
                WHEN sexo = 'M' THEN 1
                WHEN sexo = 'F' THEN 2
                ELSE 3
            END,
            edad_min,
            nombre"
    );

    $stmt->execute([
        $evento_id
    ]);

    $categorias =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Throwable $ex) {

    $error =
        "ERROR CARGANDO CATEGORÍAS: "
        . $ex->getMessage();
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<title>Categorías</title>

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
    max-width: 1000px;
    margin: auto;
    background: #fff;
    padding: 30px;
    border-radius: 10px;
}

h1 {
    margin-top: 0;
}

.cabecera {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.btn {
    padding: 11px 18px;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
    text-decoration: none;
    display: inline-block;
}

.btn-principal {
    background: #222;
    color: #fff;
}

.btn-editar {
    background: #ffc107;
    color: #212529;
}

.btn-eliminar {
    background: #dc3545;
    color: #fff;
}

.btn-volver {
    background: #6c757d;
    color: #fff;
}

.tabla {
    width: 100%;
    border-collapse: collapse;
}

.tabla th,
.tabla td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

.tabla th {
    background: #f5f5f5;
}

.tabla tr:hover {
    background: #fafafa;
}

.acciones {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.mensaje,
.error,
.info {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
}

.mensaje {
    background: #d4edda;
    color: #155724;
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

.vacio {
    padding: 30px;
    text-align: center;
    color: #666;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 5px;
    font-size: 13px;
    font-weight: bold;
}

.badge-m {
    background: #d9edf7;
    color: #31708f;
}

.badge-f {
    background: #f5e1f0;
    color: #8a3d6b;
}

.badge-a {
    background: #e2e3e5;
    color: #383d41;
}

@media (max-width: 700px) {

    body {
        padding: 10px;
    }

    .contenedor {
        padding: 15px;
    }

    .tabla {
        font-size: 14px;
    }

    .tabla th,
    .tabla td {
        padding: 8px;
    }

}

</style>

</head>

<body>

<?php require_once "../includes/menu.php"; ?>


<div class="contenedor">

    <div class="cabecera">

        <div>

            <h1>Categorías</h1>

            <div class="info">

                Categorías configuradas para el evento
                <strong>#<?= e($evento_id) ?></strong>

            </div>

        </div>


        <div>

            <a
                href="categoria_nueva.php"
                class="btn btn-principal"
            >
                + Nueva categoría
            </a>

        </div>

    </div>


    <?php if ($mensaje !== ""): ?>

        <div class="mensaje">

            <?= e($mensaje) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="error">

            <strong>Se produjo un error:</strong><br>

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <?php if (count($categorias) === 0): ?>

        <div class="vacio">

            No hay categorías configuradas para este evento.

            <br><br>

            <a
                href="categoria_nueva.php"
                class="btn btn-principal"
            >
                Crear primera categoría
            </a>

        </div>

    <?php else: ?>

        <div style="overflow-x:auto;">

            <table class="tabla">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Nombre</th>

                        <th>Sexo</th>

                        <th>Edad mínima</th>

                        <th>Edad máxima</th>

                        <th>Acciones</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($categorias as $categoria): ?>

                    <tr>

                        <td>
                            <?= e($categoria["id"]) ?>
                        </td>


                        <td>

                            <strong>
                                <?= e($categoria["nombre"]) ?>
                            </strong>

                        </td>


                        <td>

                            <?php

                            $sexo =
                                $categoria["sexo"];

                            if ($sexo === "M"):

                            ?>

                                <span class="badge badge-m">
                                    Masculino
                                </span>

                            <?php

                            elseif ($sexo === "F"):

                            ?>

                                <span class="badge badge-f">
                                    Femenino
                                </span>

                            <?php else: ?>

                                <span class="badge badge-a">
                                    Ambos
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php

                            echo $categoria["edad_min"] !== null
                                ? e($categoria["edad_min"]) . " años"
                                : "Sin límite";

                            ?>

                        </td>


                        <td>

                            <?php

                            echo $categoria["edad_max"] !== null
                                ? e($categoria["edad_max"]) . " años"
                                : "Sin límite";

                            ?>

                        </td>


                        <td>

                            <div class="acciones">

                                <a
                                    href="categoria_modificar.php?id=<?= e($categoria["id"]) ?>"
                                    class="btn btn-editar"
                                >
                                    Modificar
                                </a>


                                <a
                                    href="categoria_eliminar.php?id=<?= e($categoria["id"]) ?>"
                                    class="btn btn-eliminar"
                                >
                                    Eliminar
                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>


    <br>


    <a
        href="../index.php"
        class="btn btn-volver"
    >
        Volver al inicio
    </a>

</div>

</body>

</html>