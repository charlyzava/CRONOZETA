<?php

require_once "../../config/database.php";

$id = intval($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit;
}


// ---------------------------------------------------------
// VERIFICAR QUE EXISTA
// ---------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT *
    FROM periodos_inscripcion
    WHERE id = ?
");

$stmt->execute([$id]);

$periodo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$periodo) {
    header("Location: index.php");
    exit;
}


// ---------------------------------------------------------
// VERIFICAR SI ESTÁ SIENDO UTILIZADO
// ---------------------------------------------------------
//
// Actualmente inscripciones guarda el precio directamente,
// pero todavía no guarda el periodo_id.
// Por lo tanto no podemos relacionarlo directamente.
//
// Por ahora comprobamos si existen precios asociados.
//

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM precios_inscripcion
    WHERE periodo_id = ?
");

$stmt->execute([$id]);

$tiene_precios = intval($stmt->fetchColumn());


if ($tiene_precios > 0) {

    die("
        <h2>No se puede eliminar este período</h2>
        <p>
            El período tiene precios configurados.
        </p>
        <p>
            Para evitar perder información, modifíquelo
            o elimine primero sus precios.
        </p>
        <p>
            <a href='index.php'>Volver</a>
        </p>
    ");
}


// ---------------------------------------------------------
// ELIMINAR
// ---------------------------------------------------------

$stmt = $pdo->prepare("
    DELETE FROM periodos_inscripcion
    WHERE id = ?
");

$stmt->execute([$id]);

header("Location: index.php");
exit;