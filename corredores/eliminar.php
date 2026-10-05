
<?php

require_once "../config/database.php";

// Verificar que recibimos un ID
$id = $_GET["id"] ?? null;

if (!$id || !is_numeric($id)) {

    die("Corredor no válido.");

}

$id = (int) $id;


// Buscar el corredor
$sql = "
    SELECT id, nombre, apellido, dni
    FROM corredores
    WHERE id = :id
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ":id" => $id
]);

$corredor = $stmt->fetch(PDO::FETCH_ASSOC);


// Si no existe
if (!$corredor) {

    die("El corredor no existe.");

}


// Verificar si tiene inscripciones
$sql = "
    SELECT COUNT(*)
    FROM inscripciones
    WHERE corredor_id = :corredor_id
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ":corredor_id" => $id
]);

$cantidadInscripciones = (int) $stmt->fetchColumn();


if ($cantidadInscripciones > 0) {

    die(
        "No se puede eliminar el corredor porque "
        . "tiene inscripciones registradas."
    );

}


// Eliminar corredor
$sql = "
    DELETE FROM corredores
    WHERE id = :id
";

$stmt = $pdo->prepare($sql);

try {

    $stmt->execute([
        ":id" => $id
    ]);

    header("Location: index.php");
    exit;

} catch (PDOException $e) {

    die(
        "Error al eliminar el corredor: "
        . $e->getMessage()
    );

}

