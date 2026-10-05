
<?php

require_once "../config/database.php";


$busqueda = trim($_GET["buscar"] ?? "");


if ($busqueda !== "") {

    $sql = "
        SELECT *
        FROM corredores
        WHERE
            dni LIKE :buscar
            OR nombre LIKE :buscar
            OR apellido LIKE :buscar
        ORDER BY apellido, nombre
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ":buscar" => "%" . $busqueda . "%"
    ]);

} else {

    $sql = "
        SELECT *
        FROM corredores
        ORDER BY apellido, nombre
    ";

    $stmt = $pdo->query($sql);
}


$corredores = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">


<head>

    <meta charset="UTF-8">

    <title>Corredores - CronoTrail</title>


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


<header class="navbar">


    <div class="logo">
        CronoTrail
    </div>


    <nav>

        <a href="../index.php">
            Inicio
        </a>

        <a
            href="index.php"
            class="activo"
        >
            Corredores
        </a>

        <a href="../inscripciones/index.php">
            Inscripciones
        </a>

    </nav>


</header>



<main class="contenedor">


    <!-- CABECERA -->

    <div class="cabecera-pagina">


        <div>

            <h1>
                Corredores
            </h1>

            <p>
                Padrón de corredores registrados
            </p>

        </div>


        <a
            href="nuevo.php"
            class="boton"
        >
            + Nuevo corredor
        </a>


    </div>



    <!-- BUSCADOR -->

    <section class="panel-busqueda">


        <form method="GET">


            <div class="campo-busqueda">


                <input
                    type="text"
                    name="buscar"
                    value="<?= htmlspecialchars(
                        $busqueda
                    ) ?>"
                    placeholder="Buscar por DNI, nombre o apellido..."
                    autocomplete="off"
                >


                <button
                    type="submit"
                    class="boton"
                >
                    Buscar
                </button>


                <?php if ($busqueda !== ""): ?>

                    <a
                        href="index.php"
                        class="boton-secundario"
                    >
                        Limpiar
                    </a>

                <?php endif; ?>


            </div>


        </form>


    </section>



    <!-- INFORMACION -->

    <div class="info-resultados">


        <span>

            <?php if ($busqueda !== ""): ?>

                Resultados para:
                <strong>
                    <?= htmlspecialchars($busqueda) ?>
                </strong>

            <?php else: ?>

                Todos los corredores

            <?php endif; ?>

        </span>


        <span class="contador">

            <?= count($corredores) ?>

            <?= count($corredores) == 1
                ? "corredor"
                : "corredores" ?>

        </span>


    </div>



    <!-- TABLA -->

    <section class="panel-tabla">


        <?php if (count($corredores) > 0): ?>


            <div class="tabla-contenedor">


                <table class="tabla-corredores">


                    <thead>

                        <tr>

                            <th>
                                Apellido y nombre
                            </th>

                            <th>
                                DNI
                            </th>

                            <th>
                                Sexo
                            </th>

                            <th>
                                Fecha nacimiento
                            </th>

                            <th>
                                Contacto
                            </th>

                            <th>
                                Acciones
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $corredores
                        as $corredor
                    ): ?>


                        <tr>


                            <td>

                                <div class="nombre-corredor">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $corredor["apellido"]
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars(
                                            $corredor["nombre"]
                                        ) ?>
                                    </span>

                                </div>

                            </td>


                            <td>

                                <span class="dni">

                                    <?= htmlspecialchars(
                                        $corredor["dni"] ?? "-"
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?php if (
                                    $corredor["sexo"] === "M"
                                ): ?>

                                    Masculino

                                <?php elseif (
                                    $corredor["sexo"] === "F"
                                ): ?>

                                    Femenino

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= !empty(
                                    $corredor["fecha_nacimiento"]
                                )
                                    ? date(
                                        "d/m/Y",
                                        strtotime(
                                            $corredor[
                                                "fecha_nacimiento"
                                            ]
                                        )
                                    )
                                    : "-" ?>

                            </td>


                            <td>

                                <div class="contacto-corredor">


                                    <?php if (
                                        !empty(
                                            $corredor["email"]
                                        )
                                    ): ?>

                                        <span>
                                            <?= htmlspecialchars(
                                                $corredor["email"]
                                            ) ?>
                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        !empty(
                                            $corredor["telefono"]
                                        )
                                    ): ?>

                                        <span>
                                            <?= htmlspecialchars(
                                                $corredor["telefono"]
                                            ) ?>
                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        empty(
                                            $corredor["email"]
                                        ) &&
                                        empty(
                                            $corredor["telefono"]
                                        )
                                    ): ?>

                                        <span>
                                            -
                                        </span>

                                    <?php endif; ?>


                                </div>

                            </td>


                            <td>


                                <div class="acciones-tabla">


                                    <a
                                        href="editar.php?id=<?= $corredor["id"] ?>"
                                        class="boton-editar"
                                    >
                                        Editar
                                    </a>


                                    <a
                                        href="eliminar.php?id=<?= $corredor["id"] ?>"
                                        class="boton-eliminar"
                                        onclick="return confirm(
                                            '¿Está seguro de eliminar este corredor?'
                                        );"
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


        <?php else: ?>


            <div class="sin-resultados">


                <div class="icono-sin-resultados">
                    🔎
                </div>


                <h2>
                    No se encontraron corredores
                </h2>


                <p>

                    <?php if ($busqueda !== ""): ?>

                        No hay corredores que coincidan
                        con la búsqueda.

                    <?php else: ?>

                        Todavía no hay corredores
                        registrados.

                    <?php endif; ?>

                </p>


                <?php if ($busqueda !== ""): ?>

                    <a
                        href="index.php"
                        class="boton-secundario"
                    >
                        Ver todos los corredores
                    </a>

                <?php else: ?>

                    <a
                        href="nuevo.php"
                        class="boton"
                    >
                        Registrar corredor
                    </a>

                <?php endif; ?>


            </div>


        <?php endif; ?>


    </section>


</main>


</body>

</html>

