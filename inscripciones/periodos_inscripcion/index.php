<?php

require_once "../../config/database.php";

$sql = "
    SELECT
        p.*,
        e.nombre AS evento_nombre
    FROM periodos_inscripcion p
    INNER JOIN eventos e ON e.id = p.evento_id
    ORDER BY p.fecha_desde DESC
";

$stmt = $pdo->query($sql);

$periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Períodos de inscripción</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            margin: 0;
            padding: 30px;
        }

        .contenedor {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        h1 {
            margin-top: 0;
        }

        .acciones {
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #222;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-right: 5px;
        }

        .btn-editar {
            background: #555;
        }

        .btn-eliminar {
            background: #b00020;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #eee;
        }

        .vigente {
            color: green;
            font-weight: bold;
        }

        .cerrado {
            color: #888;
        }

    </style>

</head>

<body>

<div class="contenedor">

    <h1>Períodos de inscripción</h1>

    <div class="acciones">

        <a href="nuevo.php" class="btn">
            + Nuevo período
        </a>

    </div>


    <table>

        <thead>

            <tr>

                <th>Evento</th>
                <th>Período</th>
                <th>Desde</th>
                <th>Hasta</th>
                <th>Estado</th>
                <th>Acciones</th>

            </tr>

        </thead>

        <tbody>

        <?php foreach ($periodos as $periodo): ?>

            <?php

            $hoy = date("Y-m-d");

            if (
                $hoy >= $periodo["fecha_desde"] &&
                $hoy <= $periodo["fecha_hasta"]
            ) {
                $estado = "Vigente";
                $clase = "vigente";
            } elseif ($hoy < $periodo["fecha_desde"]) {
                $estado = "Próximo";
                $clase = "";
            } else {
                $estado = "Finalizado";
                $clase = "cerrado";
            }

            ?>

            <tr>

                <td>
                    <?= htmlspecialchars($periodo["evento_nombre"]) ?>
                </td>

                <td>
                    <?= htmlspecialchars($periodo["nombre"]) ?>
                </td>

                <td>
                    <?= date("d/m/Y", strtotime($periodo["fecha_desde"])) ?>
                </td>

                <td>
                    <?= date("d/m/Y", strtotime($periodo["fecha_hasta"])) ?>
                </td>

                <td class="<?= $clase ?>">
                    <?= $estado ?>
                </td>

                <td>

                    <a
                        href="modificar.php?id=<?= $periodo["id"] ?>"
                        class="btn btn-editar"
                    >
                        Modificar
                    </a>

                    <a
                        href="eliminar.php?id=<?= $periodo["id"] ?>"
                        class="btn btn-eliminar"
                        onclick="return confirm('¿Seguro que desea eliminar este período?');"
                    >
                        Eliminar
                    </a>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>

</body>

</html>