
<?php

/*
|--------------------------------------------------------------------------
| MOSTRAR ERRORES DURANTE EL DESARROLLO
|--------------------------------------------------------------------------
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| CONEXIÓN
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| VERIFICAR QUE EXISTA $pdo
|--------------------------------------------------------------------------
*/

if (!isset($pdo)) {

    die("
        <div style='
            font-family: Arial, sans-serif;
            padding: 30px;
            margin: 30px;
            background: #ffe5e5;
            border: 1px solid #cc0000;
            color: #990000;
            border-radius: 8px;
        '>

            <h2>Error de conexión</h2>

            <p>
                No se encontró la variable
                <strong>\$pdo</strong>.
            </p>

            <p>
                El archivo
                <strong>config/database.php</strong>
                debería crear una conexión PDO en la variable
                <strong>\$pdo</strong>.
            </p>

        </div>
    ");

}


/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

$buscar = trim($_GET['buscar'] ?? '');


/*
|--------------------------------------------------------------------------
| CONSULTA SQL
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        i.id,
        i.dorsal,
        i.remera,
        i.talle_remera,
        i.precio_base,
        i.descuento_monto,
        i.descuento_motivo,
        i.total,
        i.estado,
        i.hora_largada,
        i.hora_llegada,

        c.nombre AS corredor_nombre,
        c.apellido AS corredor_apellido,
        c.dni,

        e.nombre AS evento_nombre,
        e.fecha AS evento_fecha,

        d.nombre AS distancia_nombre,
        d.distancia_km,
        d.desnivel_m,

        cat.nombre AS categoria_nombre

    FROM inscripciones i

    INNER JOIN corredores c
        ON i.corredor_id = c.id

    INNER JOIN eventos e
        ON i.evento_id = e.id

    INNER JOIN distancias d
        ON i.distancia_id = d.id

    LEFT JOIN categorias cat
        ON i.categoria_id = cat.id
        AND cat.evento_id = i.evento_id
";


/*
|--------------------------------------------------------------------------
| FILTRO DE BÚSQUEDA
|--------------------------------------------------------------------------
*/

$parametros = [];

if ($buscar !== '') {

    $sql .= "
        WHERE
            c.dni LIKE :dni
            OR c.nombre LIKE :nombre
            OR c.apellido LIKE :apellido
            OR CAST(i.dorsal AS CHAR) LIKE :dorsal
    ";

    $termino = '%' . $buscar . '%';

    $parametros[':dni'] = $termino;
    $parametros[':nombre'] = $termino;
    $parametros[':apellido'] = $termino;
    $parametros[':dorsal'] = $termino;
}


/*
|--------------------------------------------------------------------------
| ORDEN
|--------------------------------------------------------------------------
*/

$sql .= " ORDER BY i.dorsal ASC";


/*
|--------------------------------------------------------------------------
| EJECUTAR CONSULTA
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare($sql);

    $stmt->execute($parametros);

    $inscripciones = $stmt->fetchAll();

} catch (PDOException $e) {

    die("
        <div style='
            font-family: Arial, sans-serif;
            padding: 30px;
            margin: 30px;
            background: #ffe5e5;
            border: 1px solid #cc0000;
            color: #990000;
            border-radius: 8px;
        '>

            <h2>Error en la consulta de inscripciones</h2>

            <p>
                <strong>Mensaje de MySQL/PDO:</strong>
            </p>

            <pre style='
                background: #fff;
                padding: 15px;
                overflow-x: auto;
            '>"
            . htmlspecialchars($e->getMessage())
            .
            "</pre>

            <p>
                <strong>Consulta SQL:</strong>
            </p>

            <pre style='
                background: #fff;
                padding: 15px;
                overflow-x: auto;
            '>"
            . htmlspecialchars($sql)
            .
            "</pre>

        </div>
    ");

}

?>


<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Inscripciones</title>

    <link rel="stylesheet" href="../css/estilos.css">
    <link
        rel="stylesheet"
        href="../css/inscripciones.css"
    >

</head>


<body>

<?php require_once "../includes/menu.php"; ?>

<div class="contenedor">


    <!-- =====================================================
         ENCABEZADO
    ====================================================== -->

    <div class="encabezado">


        <div>

            <h1>
                Inscripciones
            </h1>


            <p>
                Listado y búsqueda de inscriptos
            </p>

        </div>


        <a
            href="acreditacion.php"
            class="btn-nuevo"
        >
            + Nueva inscripción
        </a>


    </div>



    <!-- =====================================================
         BUSCADOR
    ====================================================== -->

    <div class="busqueda">


        <form
            method="GET"
            action="index.php"
        >


            <input
                type="text"
                name="buscar"
                value="<?= htmlspecialchars($buscar) ?>"
                placeholder="Buscar por DNI, nombre, apellido o dorsal..."
                autocomplete="off"
            >


            <button type="submit">
                Buscar
            </button>


            <?php if ($buscar !== ''): ?>

                <a href="index.php">
                    Limpiar
                </a>

            <?php endif; ?>


        </form>


    </div>



    <!-- =====================================================
         INFORMACIÓN
    ====================================================== -->

    <div class="resultado-info">


        <?php if ($buscar !== ''): ?>

            <span>

                Buscando:

                <strong>
                    <?= htmlspecialchars($buscar) ?>
                </strong>

            </span>

        <?php endif; ?>


        <span>

            Inscripciones encontradas:

            <strong>
                <?= count($inscripciones) ?>
            </strong>

        </span>


    </div>



    <!-- =====================================================
         TABLA
    ====================================================== -->

    <div class="tabla-contenedor">


        <table>


            <thead>


                <tr>

                    <th>Dorsal</th>

                    <th>Corredor</th>

                    <th>DNI</th>

                    <th>Evento</th>

                    <th>Distancia</th>

                    <th>Categoría</th>

                    <th>Remera</th>

                    <th>Total</th>

                    <th>Estado</th>

                    <th>Acciones</th>

                </tr>


            </thead>


            <tbody>


            <?php if (count($inscripciones) > 0): ?>


                <?php foreach ($inscripciones as $fila): ?>


                    <tr>


                        <!-- DORSAL -->

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $fila['dorsal']
                                ) ?>

                            </strong>

                        </td>



                        <!-- CORREDOR -->

                        <td>

                            <?= htmlspecialchars(
                                $fila['corredor_apellido']
                            ) ?>

                            ,

                            <?= htmlspecialchars(
                                $fila['corredor_nombre']
                            ) ?>

                        </td>



                        <!-- DNI -->

                        <td>

                            <?= htmlspecialchars(
                                $fila['dni']
                            ) ?>

                        </td>



                        <!-- EVENTO -->

                        <td>

                            <?= htmlspecialchars(
                                $fila['evento_nombre']
                            ) ?>

                            <br>

                            <small>

                                <?= htmlspecialchars(
                                    $fila['evento_fecha']
                                ) ?>

                            </small>

                        </td>



                        <!-- DISTANCIA -->

                        <td>

                            <?= htmlspecialchars(
                                $fila['distancia_nombre']
                            ) ?>

                            <br>

                            <small>

                                <?= htmlspecialchars(
                                    $fila['distancia_km']
                                ) ?>

                                km


                                <?php if (
                                    $fila['desnivel_m'] !== null
                                ): ?>

                                    ·

                                    <?= htmlspecialchars(
                                        $fila['desnivel_m']
                                    ) ?>

                                    m+

                                <?php endif; ?>


                            </small>

                        </td>



                        <!-- CATEGORÍA -->

                        <td>


                            <?php if (
                                !empty(
                                    $fila['categoria_nombre']
                                )
                            ): ?>


                                <?= htmlspecialchars(
                                    $fila['categoria_nombre']
                                ) ?>


                            <?php else: ?>


                                <span>
                                    Sin categoría
                                </span>


                            <?php endif; ?>


                        </td>



                        <!-- REMERA -->

                        <td>


                            <?php if (
                                (int)$fila['remera'] === 1
                            ): ?>


                                Sí


                                <?php if (
                                    !empty(
                                        $fila['talle_remera']
                                    )
                                ): ?>


                                    <br>


                                    <small>

                                        Talle:

                                        <?= htmlspecialchars(
                                            $fila['talle_remera']
                                        ) ?>

                                    </small>


                                <?php endif; ?>


                            <?php else: ?>


                                No


                            <?php endif; ?>


                        </td>



                        <!-- TOTAL -->

                        <td>

                            $

                            <?= number_format(
                                (float)$fila['total'],
                                2,
                                ',',
                                '.'
                            ) ?>

                        </td>



                        <!-- ESTADO -->

                        <td>

                            <?= htmlspecialchars(
                                $fila['estado']
                            ) ?>

                        </td>



                        <!-- ACCIONES -->

                        <td class="acciones">


                            <a
                                href="modificar.php?id=<?= (int)$fila['id'] ?>"
                                class="btn-editar"
                            >
                                Modificar
                            </a>


                            <a
                                href="eliminar.php?id=<?= (int)$fila['id'] ?>"
                                class="btn-eliminar"
                                onclick="
                                    return confirm(
                                        '¿Está seguro de eliminar esta inscripción?'
                                    );
                                "
                            >
                                Eliminar
                            </a>


                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php else: ?>


                <tr>


                    <td
                        colspan="10"
                        style="text-align:center;"
                    >


                        <?php if ($buscar !== ''): ?>


                            No se encontraron inscripciones
                            para:


                            <strong>

                                <?= htmlspecialchars(
                                    $buscar
                                ) ?>

                            </strong>


                        <?php else: ?>


                            No hay inscripciones cargadas.


                        <?php endif; ?>


                    </td>


                </tr>


            <?php endif; ?>


            </tbody>


        </table>


    </div>


</div>


</body>

</html>

