<?php

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $dni = trim($_POST["dni"] ?? "");
    $sexo = $_POST["sexo"] ?? null;
    $fecha_nacimiento = $_POST["fecha_nacimiento"] ?? null;
    $email = trim($_POST["email"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");

    if ($nombre === "" || $apellido === "") {

        die("Nombre y apellido son obligatorios.");

    }

    $sql = "
        INSERT INTO corredores
        (nombre, apellido, dni, sexo, fecha_nacimiento, email, telefono)
        VALUES
        (:nombre, :apellido, :dni, :sexo, :fecha_nacimiento, :email, :telefono)
    ";

    $stmt = $pdo->prepare($sql);

    try {

        $stmt->execute([
            ":nombre" => $nombre,
            ":apellido" => $apellido,
            ":dni" => $dni !== "" ? $dni : null,
            ":sexo" => $sexo !== "" ? $sexo : null,
            ":fecha_nacimiento" => $fecha_nacimiento !== "" ? $fecha_nacimiento : null,
            ":email" => $email !== "" ? $email : null,
            ":telefono" => $telefono !== "" ? $telefono : null
        ]);

        header("Location: index.php");
        exit;

    } catch (PDOException $e) {

        if ($e->getCode() == 23000) {

            die("El DNI ingresado ya existe.");

        }

        die("Error al guardar el corredor: " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Nuevo corredor</title>

    <link rel="stylesheet" href="../css/estilos.css">

</head>

<body>

<header class="navbar">

    <div class="logo">
        CronoTrail
    </div>

    <nav>

        <a href="../index.php">Inicio</a>

        <a href="index.php">Corredores</a>

        <a href="../inscripciones/index.php">
            Inscripciones
        </a>

    </nav>

</header>


<main class="contenedor">

    <h1>Nuevo corredor</h1>

    <form method="POST" class="formulario">

        <label>
            Nombre

            <input
                type="text"
                name="nombre"
                maxlength="50"
                required
            >

        </label>


        <label>
            Apellido

            <input
                type="text"
                name="apellido"
                maxlength="50"
                required
            >

        </label>


        <label>
            DNI

            <input
                type="text"
                name="dni"
                maxlength="20"
            >

        </label>


        <label>
            Sexo

            <select name="sexo">

                <option value="">
                    Seleccionar
                </option>

                <option value="M">
                    Masculino
                </option>

                <option value="F">
                    Femenino
                </option>

            </select>

        </label>


        <label>
            Fecha de nacimiento

            <input
                type="date"
                name="fecha_nacimiento"
            >

        </label>


        <label>
            Email

            <input
                type="email"
                name="email"
                maxlength="150"
            >

        </label>


        <label>
            Teléfono

            <input
                type="text"
                name="telefono"
                maxlength="30"
            >

        </label>


        <div class="acciones-formulario">

            <button type="submit" class="boton">
                Guardar corredor
            </button>

            <a href="index.php">
                Cancelar
            </a>

        </div>

    </form>

</main>

</body>
</html>