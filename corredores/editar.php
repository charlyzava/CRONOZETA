
<?php

require_once "../config/database.php";


// ------------------------------------
// OBTENER ID
// ------------------------------------

$id = $_GET["id"] ?? null;

if (!$id || !is_numeric($id)) {

    die("Corredor no válido.");

}

$id = (int) $id;


// ------------------------------------
// GUARDAR CAMBIOS
// ------------------------------------

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
        UPDATE corredores
        SET
            nombre = :nombre,
            apellido = :apellido,
            dni = :dni,
            sexo = :sexo,
            fecha_nacimiento = :fecha_nacimiento,
            email = :email,
            telefono = :telefono
        WHERE id = :id
    ";


    $stmt = $pdo->prepare($sql);


    try {

        $stmt->execute([

            ":nombre" => $nombre,

            ":apellido" => $apellido,

            ":dni" => $dni !== ""
                ? $dni
                : null,

            ":sexo" => $sexo !== ""
                ? $sexo
                : null,

            ":fecha_nacimiento" => $fecha_nacimiento !== ""
                ? $fecha_nacimiento
                : null,

            ":email" => $email !== ""
                ? $email
                : null,

            ":telefono" => $telefono !== ""
                ? $telefono
                : null,

            ":id" => $id
        ]);


        header("Location: index.php");

        exit;


    } catch (PDOException $e) {

        if ($e->getCode() == 23000) {

            die("El DNI ingresado ya pertenece a otro corredor.");

        }


        die(
            "Error al modificar el corredor: "
            . $e->getMessage()
        );
    }
}


// ------------------------------------
// OBTENER CORREDOR
// ------------------------------------

$sql = "
    SELECT *
    FROM corredores
    WHERE id = :id
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ":id" => $id
]);

$corredor = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$corredor) {

    die("El corredor no existe.");

}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Modificar corredor - CronoTrail</title>

    <link
        rel="stylesheet"
        href="../css/estilos.css"
    >

    <link
        rel="stylesheet"
        href="../css/corredores.css"
    >

</head>


<body>


<?php require_once "../includes/menu.php"; ?>



<main class="contenedor">


    <div class="cabecera-pagina">

        <div>

            <h1>
                Modificar corredor
            </h1>

            <p>
                Actualice los datos del corredor.
            </p>

        </div>

    </div>



    <section class="formulario-panel">


        <form method="POST">


            <div class="grid-formulario">


                <!-- DNI -->

                <div class="campo-formulario">

                    <label for="dni">
                        DNI
                    </label>

                    <input
                        type="text"
                        id="dni"
                        name="dni"
                        maxlength="20"
                        value="<?= htmlspecialchars(
                            $corredor["dni"] ?? ""
                        ) ?>"
                    >

                </div>


                <!-- NOMBRE -->

                <div class="campo-formulario">

                    <label for="nombre">
                        Nombre
                    </label>

                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        maxlength="50"
                        required
                        value="<?= htmlspecialchars(
                            $corredor["nombre"]
                        ) ?>"
                    >

                </div>


                <!-- APELLIDO -->

                <div class="campo-formulario">

                    <label for="apellido">
                        Apellido
                    </label>

                    <input
                        type="text"
                        id="apellido"
                        name="apellido"
                        maxlength="50"
                        required
                        value="<?= htmlspecialchars(
                            $corredor["apellido"]
                        ) ?>"
                    >

                </div>


                <!-- SEXO -->

                <div class="campo-formulario">

                    <label for="sexo">
                        Sexo
                    </label>

                    <select
                        id="sexo"
                        name="sexo"
                    >

                        <option value="">
                            Seleccionar
                        </option>

                        <option
                            value="M"
                            <?= $corredor["sexo"] === "M"
                                ? "selected"
                                : "" ?>
                        >
                            Masculino
                        </option>

                        <option
                            value="F"
                            <?= $corredor["sexo"] === "F"
                                ? "selected"
                                : "" ?>
                        >
                            Femenino
                        </option>

                    </select>

                </div>


                <!-- FECHA -->

                <div class="campo-formulario">

                    <label for="fecha_nacimiento">
                        Fecha de nacimiento
                    </label>

                    <input
                        type="date"
                        id="fecha_nacimiento"
                        name="fecha_nacimiento"
                        value="<?= htmlspecialchars(
                            $corredor["fecha_nacimiento"] ?? ""
                        ) ?>"
                    >

                </div>


                <!-- EMAIL -->

                <div class="campo-formulario">

                    <label for="email">
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $corredor["email"] ?? ""
                        ) ?>"
                    >

                </div>


                <!-- TELEFONO -->

                <div class="campo-formulario">

                    <label for="telefono">
                        Teléfono
                    </label>

                    <input
                        type="text"
                        id="telefono"
                        name="telefono"
                        maxlength="30"
                        value="<?= htmlspecialchars(
                            $corredor["telefono"] ?? ""
                        ) ?>"
                    >

                </div>

            </div>


            <div class="acciones-formulario">


                <a
                    href="index.php"
                    class="boton-secundario"
                >
                    Cancelar
                </a>


                <button
                    type="submit"
                    class="boton"
                >
                    Guardar cambios
                </button>


            </div>


        </form>

    </section>

</main>


</body>

</html>

