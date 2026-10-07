<?php

require_once "../config/database.php";

$evento_id = 1;

$id = isset($_GET['id'])
    ? intval($_GET['id'])
    : 0;


// ---------------------------------------------------------
// BUSCAR CATEGORÍA
// ---------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT *
    FROM categorias
    WHERE id = ?
    AND evento_id = ?
");

$stmt->execute([
    $id,
    $evento_id
]);

$categoria = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$categoria) {

    header("Location: categorias.php");
    exit;

}


// ---------------------------------------------------------
// VERIFICAR INSCRIPCIONES
// ---------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM inscripciones
    WHERE categoria_id = ?
");

$stmt->execute([$id]);

$cantidad_inscripciones =
    $stmt->fetchColumn();


if ($cantidad_inscripciones > 0) {

    ?>

    <?php include "../includes/menu.php"; ?>


    <style>

    .mensaje-categoria {
        max-width: 650px;
        margin: 50px auto;
        padding: 0 20px;
    }

    .card-error {
        background: white;
        border-radius: 9px;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
        border-left: 5px solid #dc3545;
    }

    .card-error h2 {
        margin-top: 0;
        color: #a71d2a;
    }

    .card-error p {
        color: #555;
        line-height: 1.6;
    }

    .categoria-nombre {
        font-weight: bold;
        color: #333;
    }

    .acciones-error {
        margin-top: 25px;
    }

    .boton-secundario {
        display: inline-block;
        padding: 11px 20px;
        background: #eeeeee;
        color: #333;
        text-decoration: none;
        border-radius: 6px;
    }

    .boton-secundario:hover {
        background: #dddddd;
    }

    </style>


    <div class="mensaje-categoria">

        <div class="card-error">

            <h2>
                No se puede eliminar la categoría
            </h2>


            <p>

                La categoría

                <span class="categoria-nombre">
                    <?= htmlspecialchars(
                        $categoria['nombre']
                    ) ?>
                </span>

                tiene

                <strong>
                    <?= intval(
                        $cantidad_inscripciones
                    ) ?>
                </strong>

                inscripción(es) asociada(s).

            </p>


            <p>

                Para conservar la integridad de las
                clasificaciones, esta categoría no puede
                eliminarse mientras tenga inscripciones.

                Puede modificar sus datos o sus distancias.

            </p>


            <div class="acciones-error">

                <a
                    href="categorias.php"
                    class="boton-secundario"
                >
                    ← Volver a categorías
                </a>

            </div>

        </div>

    </div>


    <?php

    exit;

}


// ---------------------------------------------------------
// ELIMINAR
// ---------------------------------------------------------

try {

    $pdo->beginTransaction();


    // Eliminar relaciones

    $stmt = $pdo->prepare("
        DELETE FROM categoria_distancia
        WHERE categoria_id = ?
    ");

    $stmt->execute([$id]);


    // Eliminar categoría

    $stmt = $pdo->prepare("
        DELETE FROM categorias
        WHERE id = ?
        AND evento_id = ?
    ");

    $stmt->execute([
        $id,
        $evento_id
    ]);


    $pdo->commit();


} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die("No se pudo eliminar la categoría.");

}


header("Location: categorias.php");
exit;