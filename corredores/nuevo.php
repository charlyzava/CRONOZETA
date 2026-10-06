
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
        (
            nombre,
            apellido,
            dni,
            sexo,
            fecha_nacimiento,
            email,
            telefono
        )
        VALUES
        (
            :nombre,
            :apellido,
            :dni,
            :sexo,
            :fecha_nacimiento,
            :email,
            :telefono
        )
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
                : null
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

    <title>Nuevo corredor - CronoTrail</title>

    <link rel="stylesheet" href="../css/estilos.css">

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
                Nuevo corredor
            </h1>

            <p>
                Ingrese los datos del corredor.
            </p>

        </div>

    </div>


    <section class="formulario-panel">


        <!-- DNI -->

        <div class="campo-formulario campo-dni">

            <label for="dni">
                DNI
            </label>

            <div class="dni-contenedor">

                <input
                    type="text"
                    id="dni"
                    name="dni"
                    form="form-corredor"
                    maxlength="20"
                    autocomplete="off"
                    placeholder="Ingrese el DNI"
                    autofocus
                >

                <span
                    id="dni-cargando"
                    class="dni-cargando"
                >
                    Buscando...
                </span>

            </div>


            <div
                id="mensaje-dni"
                class="mensaje-dni"
            ></div>

        </div>


        <form
            method="POST"
            id="form-corredor"
        >


            <div class="grid-formulario">


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

                        <option value="M">
                            Masculino
                        </option>

                        <option value="F">
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
                    id="boton-guardar"
                >
                    Guardar corredor
                </button>

            </div>

        </form>

    </section>

</main>


<script>

const dni = document.getElementById("dni");

const nombre = document.getElementById("nombre");
const apellido = document.getElementById("apellido");
const sexo = document.getElementById("sexo");
const fechaNacimiento =
    document.getElementById("fecha_nacimiento");
const email = document.getElementById("email");
const telefono = document.getElementById("telefono");

const mensajeDni =
    document.getElementById("mensaje-dni");

const dniCargando =
    document.getElementById("dni-cargando");

const botonGuardar =
    document.getElementById("boton-guardar");


let corredorEncontrado = false;


// ------------------------------------
// BUSCAR DNI
// ------------------------------------

dni.addEventListener("blur", buscarDni);


async function buscarDni() {

    const valor = dni.value.trim();

    mensajeDni.innerHTML = "";

    mensajeDni.className = "mensaje-dni";

    corredorEncontrado = false;

    if (valor === "") {
        return;
    }


    dniCargando.style.display = "inline";


    try {

        const respuesta = await fetch(
            "buscar_dni.php?dni=" +
            encodeURIComponent(valor)
        );


        const datos = await respuesta.json();


        dniCargando.style.display = "none";


        if (datos.existe) {

            corredorEncontrado = true;

            const corredor = datos.corredor;


            mensajeDni.className =
                "mensaje-dni mensaje-existe";


            mensajeDni.innerHTML = `
                <strong>⚠ Corredor ya registrado</strong>
                <br>
                ${escapeHtml(corredor.apellido)},
                ${escapeHtml(corredor.nombre)}
                <br>
                <span>
                    Este corredor ya existe en el sistema.
                </span>
            `;


            // Completar datos

            nombre.value =
                corredor.nombre ?? "";

            apellido.value =
                corredor.apellido ?? "";

            sexo.value =
                corredor.sexo ?? "";

            fechaNacimiento.value =
                corredor.fecha_nacimiento ?? "";

            email.value =
                corredor.email ?? "";

            telefono.value =
                corredor.telefono ?? "";


            // Bloqueamos el alta para evitar
            // duplicar el corredor.

            botonGuardar.disabled = true;


        } else {

            mensajeDni.className =
                "mensaje-dni mensaje-nuevo";


            mensajeDni.innerHTML = `
                ✓ DNI disponible.
                Puede continuar con la carga.
            `;


            botonGuardar.disabled = false;
        }


    } catch (error) {

        dniCargando.style.display = "none";

        mensajeDni.className =
            "mensaje-dni mensaje-error";


        mensajeDni.innerHTML =
            "No se pudo consultar el DNI.";

    }

}


// ------------------------------------
// EVITAR ENVIAR SI YA EXISTE
// ------------------------------------

document
    .getElementById("form-corredor")
    .addEventListener("submit", function(event) {

        if (corredorEncontrado) {

            event.preventDefault();

            alert(
                "Este DNI ya corresponde a un corredor registrado."
            );

        }

    });


// ------------------------------------
// SEGURIDAD PARA MOSTRAR TEXTO
// ------------------------------------

function escapeHtml(text) {

    const div =
        document.createElement("div");

    div.textContent = text ?? "";

    return div.innerHTML;
}

</script>


</body>

</html>

