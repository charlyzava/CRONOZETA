<?php

require_once "../../config/database.php";

$id = intval($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$error = "";


// ---------------------------------------------------------
// CARGAR EVENTOS
// ---------------------------------------------------------

$stmt = $pdo->query("
    SELECT *
    FROM eventos
    ORDER BY fecha DESC
");

$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ---------------------------------------------------------
// CARGAR DISTANCIAS
// ---------------------------------------------------------

$stmt = $pdo->query("
    SELECT *
    FROM distancias
    ORDER BY distancia_km
");

$distancias = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ---------------------------------------------------------
// CARGAR PERÍODO
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
// CARGAR PRECIOS
// ---------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT *
    FROM precios_inscripcion
    WHERE periodo_id = ?
");

$stmt->execute([$id]);

$precios = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $precio) {

    $precios[$precio["distancia_id"]] = $precio;
}


// ---------------------------------------------------------
// GUARDAR CAMBIOS
// ---------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $evento_id = intval($_POST["evento_id"] ?? 0);
    $nombre = trim($_POST["nombre"] ?? "");
    $fecha_desde = $_POST["fecha_desde"] ?? "";
    $fecha_hasta = $_POST["fecha_hasta"] ?? "";


    // -----------------------------------------------------
    // VALIDACIONES
    // -----------------------------------------------------

    if ($evento_id <= 0) {

        $error = "Debe seleccionar un evento.";

    } elseif ($nombre === "") {

        $error = "Debe ingresar el nombre del período.";

    } elseif ($fecha_desde === "" || $fecha_hasta === "") {

        $error = "Debe ingresar las fechas.";

    } elseif ($fecha_desde > $fecha_hasta) {

        $error = "La fecha desde no puede ser posterior a la fecha hasta.";

    }


    // -----------------------------------------------------
    // VERIFICAR SUPERPOSICIÓN
    // -----------------------------------------------------

    if ($error === "") {

        $stmt = $pdo->prepare("
            SELECT id
            FROM periodos_inscripcion
            WHERE evento_id = ?
              AND id <> ?
              AND fecha_desde <= ?
              AND fecha_hasta >= ?
            LIMIT 1
        ");

        $stmt->execute([
            $evento_id,
            $id,
            $fecha_hasta,
            $fecha_desde
        ]);

        if ($stmt->fetch()) {

            $error = "Las fechas se superponen con otro período de este evento.";

        }
    }


    // -----------------------------------------------------
    // ACTUALIZAR
    // -----------------------------------------------------

    if ($error === "") {

        $stmt = $pdo->prepare("
            UPDATE periodos_inscripcion
            SET
                evento_id = ?,
                nombre = ?,
                fecha_desde = ?,
                fecha_hasta = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $evento_id,
            $nombre,
            $fecha_desde,
            $fecha_hasta,
            $id
        ]);


        // -------------------------------------------------
        // ACTUALIZAR PRECIOS
        // -------------------------------------------------

        foreach ($distancias as $distancia) {

            $distancia_id = $distancia["id"];

            $precio_con = floatval(
                $_POST["precio_con_remera"][$distancia_id] ?? 0
            );

            $precio_sin = floatval(
                $_POST["precio_sin_remera"][$distancia_id] ?? 0
            );


            if (isset($precios[$distancia_id])) {

                $stmt = $pdo->prepare("
                    UPDATE precios_inscripcion
                    SET
                        precio_con_remera = ?,
                        precio_sin_remera = ?
                    WHERE periodo_id = ?
                      AND distancia_id = ?
                ");

                $stmt->execute([
                    $precio_con,
                    $precio_sin,
                    $id,
                    $distancia_id
                ]);

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO precios_inscripcion
                    (
                        periodo_id,
                        distancia_id,
                        precio_con_remera,
                        precio_sin_remera
                    )
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->execute([
                    $id,
                    $distancia_id,
                    $precio_con,
                    $precio_sin
                ]);
            }
        }


        header("Location: index.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Modificar período</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            margin: 0;
            padding: 30px;
        }

        .contenedor {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        h1 {
            margin-top: 0;
        }

        .campo {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }

        input,
        select {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            font-size: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
        }

        th {
            background: #eee;
        }

        table input {
            width: 100%;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .botones {
            margin-top: 25px;
        }

        button,
        .volver {
            padding: 11px 18px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            font-size: 16px;
        }

        button {
            background: #222;
            color: white;
        }

        .volver {
            background: #ddd;
            color: #222;
        }

    </style>

</head>

<body>

<div class="contenedor">

    <h1>Modificar período</h1>


    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="POST">


        <!-- EVENTO -->

        <div class="campo">

            <label>Evento</label>

            <select name="evento_id" required>

                <?php foreach ($eventos as $evento): ?>

                    <option
                        value="<?= $evento["id"] ?>"
                        <?= $periodo["evento_id"] == $evento["id"] ? "selected" : "" ?>
                    >

                        <?= htmlspecialchars($evento["nombre"]) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- NOMBRE -->

        <div class="campo">

            <label>Nombre del período</label>

            <input
                type="text"
                name="nombre"
                value="<?= htmlspecialchars($periodo["nombre"]) ?>"
                required
            >

        </div>


        <!-- FECHAS -->

        <div class="campo">

            <label>Fecha desde</label>

            <input
                type="date"
                name="fecha_desde"
                value="<?= htmlspecialchars($periodo["fecha_desde"]) ?>"
                required
            >

        </div>


        <div class="campo">

            <label>Fecha hasta</label>

            <input
                type="date"
                name="fecha_hasta"
                value="<?= htmlspecialchars($periodo["fecha_hasta"]) ?>"
                required
            >

        </div>


        <!-- PRECIOS -->

        <h2>Precios</h2>

        <table>

            <thead>

                <tr>
                    <th>Distancia</th>
                    <th>Con remera</th>
                    <th>Sin remera</th>
                </tr>

            </thead>

            <tbody>

            <?php foreach ($distancias as $distancia): ?>

                <?php

                $precio_con = $precios[$distancia["id"]]["precio_con_remera"] ?? 0;
                $precio_sin = $precios[$distancia["id"]]["precio_sin_remera"] ?? 0;

                ?>

                <tr>

                    <td>

                        <?= htmlspecialchars($distancia["nombre"]) ?>

                        <br>

                        <small>
                            <?= htmlspecialchars($distancia["distancia_km"]) ?> km
                        </small>

                    </td>

                    <td>

                        <input
                            type="number"
                            name="precio_con_remera[<?= $distancia["id"] ?>]"
                            value="<?= htmlspecialchars($precio_con) ?>"
                            min="0"
                            step="0.01"
                            required
                        >

                    </td>

                    <td>

                        <input
                            type="number"
                            name="precio_sin_remera[<?= $distancia["id"] ?>]"
                            value="<?= htmlspecialchars($precio_sin) ?>"
                            min="0"
                            step="0.01"
                            required
                        >

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>


        <div class="botones">

            <button type="submit">
                Guardar cambios
            </button>

            <a href="index.php" class="volver">
                Cancelar
            </a>

        </div>

    </form>

</div>

</body>

</html>