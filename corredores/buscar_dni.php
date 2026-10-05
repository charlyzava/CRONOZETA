
<?php

require_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

$dni = trim($_GET["dni"] ?? "");

if ($dni === "") {

    echo json_encode([
        "existe" => false
    ]);

    exit;
}


$sql = "
    SELECT
        id,
        nombre,
        apellido,
        dni,
        sexo,
        fecha_nacimiento,
        email,
        telefono
    FROM corredores
    WHERE dni = :dni
    LIMIT 1
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ":dni" => $dni
]);

$corredor = $stmt->fetch(PDO::FETCH_ASSOC);


if ($corredor) {

    echo json_encode([
        "existe" => true,
        "corredor" => $corredor
    ]);

} else {

    echo json_encode([
        "existe" => false
    ]);
}

