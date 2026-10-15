<?php
require_once "../config/database.php";

$evento_id = 1;
$id = (int)($_GET["id"] ?? $_POST["id"] ?? 0);

if ($id <= 0) {
    die("Inscripción no válida.");
}

$error = "";

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

/* Buscar inscripción */
$stmt = $pdo->prepare("
    SELECT
        i.*,
        c.nombre,
        c.apellido,
        c.dni,
        d.nombre AS distancia_nombre,
        d.distancia_km,
        cat.nombre AS categoria_nombre
    FROM inscripciones i
    INNER JOIN corredores c ON c.id = i.corredor_id
    INNER JOIN distancias d ON d.id = i.distancia_id
    LEFT JOIN categorias cat ON cat.id = i.categoria_id
    WHERE i.id = ?
      AND i.evento_id = ?
    LIMIT 1
");
$stmt->execute([$id, $evento_id]);
$inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inscripcion) {
    die("No existe la inscripción.");
}

/* Consultar pagos vinculados */
$stmt = $pdo->prepare("
    SELECT COUNT(*), COALESCE(SUM(monto), 0)
    FROM pagos
    WHERE inscripcion_id = ?
");
$stmt->execute([$id]);
$datosPagos = $stmt->fetch(PDO::FETCH_NUM);
$cantidadPagos = (int)$datosPagos[0];
$totalPagado = (float)$datosPagos[1];

/* Eliminar primero los pagos y luego la inscripción */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["confirmar"] ?? "") === "SI"
) {
    try {
        $pdo->beginTransaction();

        $stmtPagos = $pdo->prepare("
            DELETE FROM pagos
            WHERE inscripcion_id = ?
        ");
        $stmtPagos->execute([$id]);

        $stmtInscripcion = $pdo->prepare("
            DELETE FROM inscripciones
            WHERE id = ?
              AND evento_id = ?
        ");
        $stmtInscripcion->execute([$id, $evento_id]);

        if ($stmtInscripcion->rowCount() !== 1) {
            throw new Exception("No se pudo eliminar la inscripción.");
        }

        $pdo->commit();

        $mensaje = "Inscripción eliminada correctamente.";
        if ($cantidadPagos > 0) {
            $mensaje .= " También se eliminaron " . $cantidadPagos .
                " pago(s) asociado(s), por un total de $" .
                number_format($totalPagado, 2, ",", ".");
        }

        header("Location: index.php?mensaje=" . urlencode($mensaje));
        exit;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "No se pudo completar la eliminación: " . $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eliminar inscripción</title>
<link rel="stylesheet" href="../css/estilos.css">
<style>
body {
    font-family: Arial, sans-serif;
    background: #f2f2f2;
    margin: 0;
    padding: 30px;
}
.contenedor {
    max-width: 700px;
    margin: auto;
    background: #fff;
    padding: 30px;
    border-radius: 10px;
}
.datos {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    line-height: 1.7;
}
.advertencia {
    background: #fff3cd;
    color: #856404;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    line-height: 1.5;
}
.error {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.btn {
    padding: 12px 20px;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
}
.btn-peligro {
    background: #a00;
    color: #fff;
}
.btn-cancelar {
    background: #555;
    color: #fff;
    text-decoration: none;
    display: inline-block;
}
.acciones {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
</style>
</head>
<body>
<?php require_once "../includes/menu.php"; ?>

<div class="contenedor">
    <h1>Eliminar inscripción</h1>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="datos">
        <strong><?= e($inscripcion["apellido"]) ?>, <?= e($inscripcion["nombre"]) ?></strong>
        <br>
        DNI: <?= e($inscripcion["dni"]) ?>
        <br>
        Distancia: <?= e($inscripcion["distancia_nombre"]) ?> - <?= e($inscripcion["distancia_km"]) ?> km
        <br>
        Dorsal: <strong><?= e($inscripcion["dorsal"]) ?></strong>
        <br>
        Categoría: <?= e($inscripcion["categoria_nombre"] ?? "Sin categoría") ?>
        <br>
        Total de inscripción:
        <strong>$<?= number_format((float)$inscripcion["total"], 2, ",", ".") ?></strong>
    </div>

    <?php if ($cantidadPagos > 0): ?>
        <div class="advertencia">
            <strong>Atención: esta inscripción tiene pagos registrados.</strong>
            <br><br>
            Se eliminarán <strong><?= $cantidadPagos ?></strong> pago(s) asociado(s),
            por un total de
            <strong>$<?= number_format($totalPagado, 2, ",", ".") ?></strong>.
            <br><br>
            Esta eliminación es definitiva: esos pagos dejarán de figurar en el historial.
            El corredor no será eliminado.
        </div>
    <?php else: ?>
        <div class="advertencia">
            <strong>Atención:</strong> esta acción eliminará definitivamente la inscripción.
            No hay pagos asociados.
            <br><br>
            El corredor permanecerá en la tabla <strong>corredores</strong> y podrá eliminarse por separado.
        </div>
    <?php endif; ?>

    <form method="POST" onsubmit="return confirmarEliminacion();">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="confirmar" value="SI">
        <div class="acciones">
            <button type="submit" class="btn btn-peligro">
                Sí, eliminar inscripción<?= $cantidadPagos > 0 ? " y sus pagos" : "" ?>
            </button>
            <a href="index.php" class="btn btn-cancelar">Cancelar</a>
        </div>
    </form>
</div>

<script>
function confirmarEliminacion() {
    return confirm(
        "¿Está seguro de eliminar esta inscripción?\n\n" +
        "También se eliminarán todos los pagos asociados, si existen.\n" +
        "El corredor no será eliminado.\n\n" +
        "Esta acción no se puede deshacer."
    );
}
</script>
</body>
</html>
