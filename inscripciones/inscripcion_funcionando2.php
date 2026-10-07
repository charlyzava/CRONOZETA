
<?php

// =========================================================
// MODO DIAGNÓSTICO
// =========================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Mostrar errores fatales que ocurran antes de que termine el script
register_shutdown_function(function () {

    $error = error_get_last();

    if ($error !== null) {

        $tipos = array(
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR
        );

        if (in_array($error['type'], $tipos)) {

            echo '<div style="
                background:#f8d7da;
                color:#721c24;
                border:2px solid #f5c6cb;
                padding:20px;
                margin:20px;
                font-family:Arial;
                border-radius:8px;
            ">';

            echo '<h2>ERROR FATAL DE PHP</h2>';

            echo '<strong>Mensaje:</strong><br>';
            echo htmlspecialchars($error['message'], ENT_QUOTES, 'UTF-8');

            echo '<br><br><strong>Archivo:</strong><br>';
            echo htmlspecialchars($error['file'], ENT_QUOTES, 'UTF-8');

            echo '<br><br><strong>Línea:</strong> ';
            echo (int)$error['line'];

            echo '</div>';
        }
    }
});


// =========================================================
// CONEXIÓN
// =========================================================

try {

    require_once "../config/database.php";

} catch (Throwable $ex) {

    die('
        <div style="
            background:#f8d7da;
            color:#721c24;
            padding:20px;
            margin:20px;
            font-family:Arial;
            border-radius:8px;
        ">
        <h2>Error cargando database.php</h2>
        <strong>' .
        htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8')
        . '</strong>
        </div>
    ');
}


// Verificar PDO

if (!isset($pdo)) {

    die('
        <div style="
            background:#f8d7da;
            color:#721c24;
            padding:20px;
            margin:20px;
            font-family:Arial;
            border-radius:8px;
        ">
        <h2>Error de conexión</h2>
        <p>El archivo database.php no creó la variable <strong>$pdo</strong>.</p>
        </div>
    ');
}


// =========================================================
// VARIABLES
// =========================================================

$evento_id = 1;

$mensaje = "";
$error = "";

$corredor = null;
$ya_inscripto = null;

$distancias = [];
$categorias = [];
$precios_js = [];

$corredor_id = 0;


// =========================================================
// FUNCIONES
// =========================================================

function e($valor)
{
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


// =========================================================
// BUSCAR CORREDOR POR DNI
// =========================================================

if (isset($_GET["dni"])) {

    $dni = trim($_GET["dni"]);

    if ($dni !== "") {

        try {

            $stmt = $pdo->prepare(
                "SELECT *
                 FROM corredores
                 WHERE dni = ?
                 LIMIT 1"
            );

            $stmt->execute([$dni]);

            $corredor = $stmt->fetch(PDO::FETCH_ASSOC);


            if ($corredor) {

                $corredor_id = (int)$corredor["id"];


                $stmt = $pdo->prepare(
                    "SELECT
                        i.id,
                        i.dorsal,
                        i.distancia_id,
                        d.nombre AS distancia_nombre,
                        d.distancia_km,
                        i.categoria_id,
                        c.nombre AS categoria_nombre,
                        i.estado
                    FROM inscripciones i
                    INNER JOIN distancias d
                        ON d.id = i.distancia_id
                    LEFT JOIN categorias c
                        ON c.id = i.categoria_id
                    WHERE i.evento_id = ?
                    AND i.corredor_id = ?
                    LIMIT 1"
                );

                $stmt->execute([
                    $evento_id,
                    $corredor_id
                ]);

                $ya_inscripto =
                    $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
            }

        } catch (Throwable $ex) {

            $error =
                "ERROR buscando corredor: "
                . $ex->getMessage();
        }
    }
}


// =========================================================
// GUARDAR INSCRIPCIÓN
// =========================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        $corredor_id =
            (int)($_POST["corredor_id"] ?? 0);

        $distancia_id =
            (int)($_POST["distancia_id"] ?? 0);

        $categoria_id =
            !empty($_POST["categoria_id"])
            ? (int)$_POST["categoria_id"]
            : null;

        $remera =
            isset($_POST["remera"])
            ? (int)$_POST["remera"]
            : 1;

        $talle_remera =
            trim($_POST["talle_remera"] ?? "");

        $dorsal_ingresado =
            !empty($_POST["dorsal"])
            ? (int)$_POST["dorsal"]
            : 0;

        $descuento_monto =
            max(
                0,
                (float)($_POST["descuento_monto"] ?? 0)
            );

        $descuento_motivo =
            trim($_POST["descuento_motivo"] ?? "");

        $pago_inicial =
            max(
                0,
                (float)($_POST["pago_inicial"] ?? 0)
            );

        $medio_pago =
            trim($_POST["medio_pago"] ?? "");

        $comprobante =
            trim($_POST["comprobante"] ?? "");

        $observacion_pago =
            trim($_POST["observacion_pago"] ?? "");


        // -----------------------------------------------------
        // VALIDACIONES
        // -----------------------------------------------------

        if ($corredor_id <= 0) {

            $error = "Corredor inválido.";

        } elseif ($distancia_id <= 0) {

            $error = "Debe seleccionar una distancia.";

        } elseif (
            $remera === 1 &&
            $talle_remera === ""
        ) {

            $error = "Debe seleccionar el talle de remera.";

        } elseif ($remera === 0) {

            $talle_remera = null;
        }


        // -----------------------------------------------------
        // VERIFICAR CORREDOR
        // -----------------------------------------------------

        if ($error === "") {

            $stmt = $pdo->prepare(
                "SELECT *
                 FROM corredores
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$corredor_id]);

            $corredor =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$corredor) {

                $error =
                    "El corredor no existe.";
            }
        }


        // -----------------------------------------------------
        // VERIFICAR INSCRIPCIÓN EXISTENTE
        // -----------------------------------------------------

        if ($error === "") {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM inscripciones
                 WHERE evento_id = ?
                 AND corredor_id = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $evento_id,
                $corredor_id
            ]);

            if ($stmt->fetch()) {

                $error =
                    "El corredor ya está inscripto en este evento.";
            }
        }


        // -----------------------------------------------------
        // PERÍODO VIGENTE
        // -----------------------------------------------------

        $periodo = null;

        if ($error === "") {

            $fecha_hoy = date("Y-m-d");

            $stmt = $pdo->prepare(
                "SELECT *
                 FROM periodos_inscripcion
                 WHERE evento_id = ?
                 AND fecha_desde <= ?
                 AND fecha_hasta >= ?
                 ORDER BY fecha_desde DESC
                 LIMIT 1"
            );

            $stmt->execute([
                $evento_id,
                $fecha_hoy,
                $fecha_hoy
            ]);

            $periodo =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$periodo) {

                $error =
                    "No existe un período de inscripción vigente para la fecha de hoy.";
            }
        }


        // -----------------------------------------------------
        // DISTANCIA
        // -----------------------------------------------------

        $distancia = null;

        if ($error === "") {

            $stmt = $pdo->prepare(
                "SELECT *
                 FROM distancias
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$distancia_id]);

            $distancia =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$distancia) {

                $error =
                    "La distancia seleccionada no existe.";

            } elseif (
                $distancia["dorsal_desde"] === null ||
                $distancia["dorsal_hasta"] === null
            ) {

                $error =
                    "La distancia no tiene configurado el rango de dorsales.";
            }
        }


        // -----------------------------------------------------
        // PRECIO
        // -----------------------------------------------------

        $precio_base = 0;
        $total = 0;

        if ($error === "") {

            $stmt = $pdo->prepare(
                "SELECT *
                 FROM precios_inscripcion
                 WHERE periodo_id = ?
                 AND distancia_id = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $periodo["id"],
                $distancia_id
            ]);

            $precio =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$precio) {

                $error =
                    "No hay un precio configurado para esta distancia en el período actual.";

            } else {

                $precio_base =
                    $remera === 1
                    ? (float)$precio["precio_con_remera"]
                    : (float)$precio["precio_sin_remera"];


                if ($descuento_monto > $precio_base) {

                    $descuento_monto =
                        $precio_base;
                }


                $total =
                    $precio_base -
                    $descuento_monto;
            }
        }


        // -----------------------------------------------------
        // DORSAL AUTOMÁTICO
        // -----------------------------------------------------

        $dorsal =
            $dorsal_ingresado;


        if ($error === "") {

            if ($dorsal <= 0) {

                $desde =
                    (int)$distancia["dorsal_desde"];

                $hasta =
                    (int)$distancia["dorsal_hasta"];

                $dorsal_encontrado = null;


                for (
                    $numero = $desde;
                    $numero <= $hasta;
                    $numero++
                ) {

                    $stmt = $pdo->prepare(
                        "SELECT id
                         FROM inscripciones
                         WHERE evento_id = ?
                         AND dorsal = ?
                         LIMIT 1"
                    );

                    $stmt->execute([
                        $evento_id,
                        $numero
                    ]);


                    if (!$stmt->fetch()) {

                        $dorsal_encontrado =
                            $numero;

                        break;
                    }
                }


                if ($dorsal_encontrado === null) {

                    $error =
                        "No quedan dorsales disponibles para la distancia seleccionada.";

                } else {

                    $dorsal =
                        $dorsal_encontrado;
                }
            }
        }


        // -----------------------------------------------------
        // VALIDAR DORSAL MANUAL
        // -----------------------------------------------------

        if (
            $error === "" &&
            $dorsal_ingresado > 0
        ) {

            $desde =
                (int)$distancia["dorsal_desde"];

            $hasta =
                (int)$distancia["dorsal_hasta"];


            if (
                $dorsal_ingresado < $desde ||
                $dorsal_ingresado > $hasta
            ) {

                $error =
                    "El dorsal $dorsal_ingresado está fuera del rango de la distancia. " .
                    "El rango permitido es $desde a $hasta.";
            }
        }


        // -----------------------------------------------------
        // DORSAL OCUPADO
        // -----------------------------------------------------

        if ($error === "") {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM inscripciones
                 WHERE evento_id = ?
                 AND dorsal = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $evento_id,
                $dorsal
            ]);


            if ($stmt->fetch()) {

                $error =
                    "El dorsal $dorsal ya está asignado.";
            }
        }


        // -----------------------------------------------------
        // PAGO
        // -----------------------------------------------------

        if (
            $error === "" &&
            $pago_inicial > $total
        ) {

            $error =
                "El pago inicial no puede superar el total de la inscripción.";
        }


        // -----------------------------------------------------
        // INSERTAR INSCRIPCIÓN
        // -----------------------------------------------------

        if ($error === "") {

            $stmt = $pdo->prepare(
                "INSERT INTO inscripciones
                (
                    evento_id,
                    corredor_id,
                    distancia_id,
                    periodo_id,
                    categoria_id,
                    dorsal,
                    remera,
                    talle_remera,
                    precio_base,
                    descuento_monto,
                    descuento_motivo,
                    total,
                    estado
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, 'confirmado'
                )"
            );


            $pdo->beginTransaction();


            $stmt->execute([

                $evento_id,
                $corredor_id,
                $distancia_id,
                $periodo["id"],
                $categoria_id,
                $dorsal,
                $remera,
                $talle_remera,
                $precio_base,
                $descuento_monto,
                $descuento_motivo !== ""
                    ? $descuento_motivo
                    : null,
                $total
            ]);


            $inscripcion_id =
                (int)$pdo->lastInsertId();


            // -------------------------------------------------
            // PAGO
            // -------------------------------------------------

            if ($pago_inicial > 0) {

                $stmtPago = $pdo->prepare(
                    "INSERT INTO pagos
                    (
                        inscripcion_id,
                        fecha,
                        monto,
                        medio_pago,
                        comprobante,
                        observacion
                    )
                    VALUES
                    (?, NOW(), ?, ?, ?, ?)"
                );


                $stmtPago->execute([

                    $inscripcion_id,
                    $pago_inicial,

                    $medio_pago !== ""
                        ? $medio_pago
                        : null,

                    $comprobante !== ""
                        ? $comprobante
                        : null,

                    $observacion_pago !== ""
                        ? $observacion_pago
                        : null
                ]);
            }


            $pdo->commit();


            header(
                "Location: inscripcion.php?ok=1&dorsal="
                . urlencode($dorsal)
            );

            exit;
        }


    } catch (Throwable $ex) {

        if (
            isset($pdo) &&
            $pdo instanceof PDO &&
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();
        }


        $error =
            "ERROR PHP/SQL: " .
            $ex->getMessage();
    }
}


// =========================================================
// DATOS PARA EL FORMULARIO
// =========================================================

try {

    // DISTANCIAS

    $stmt = $pdo->query(
        "SELECT *
         FROM distancias
         ORDER BY distancia_km"
    );

    $distancias =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    // CATEGORÍAS

    $stmt = $pdo->prepare(
        "SELECT *
         FROM categorias
         WHERE evento_id = ?
         ORDER BY sexo, edad_min"
    );

    $stmt->execute([$evento_id]);

    $categorias =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    // PRECIOS

    $fecha_hoy =
        date("Y-m-d");


    $stmt = $pdo->prepare(
        "SELECT
            pi.distancia_id,
            pi.precio_con_remera,
            pi.precio_sin_remera
         FROM precios_inscripcion pi
         INNER JOIN periodos_inscripcion p
             ON p.id = pi.periodo_id
         WHERE p.evento_id = ?
         AND p.fecha_desde <= ?
         AND p.fecha_hasta >= ?"
    );


    $stmt->execute([
        $evento_id,
        $fecha_hoy,
        $fecha_hoy
    ]);


    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $fila
    ) {

        $precios_js[
            $fila["distancia_id"]
        ] = [

            "con_remera" =>
                (float)$fila["precio_con_remera"],

            "sin_remera" =>
                (float)$fila["precio_sin_remera"]
        ];
    }


} catch (Throwable $ex) {

    $error =
        "ERROR CARGANDO DATOS DEL FORMULARIO: "
        . $ex->getMessage();
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<title>Inscripción</title>

<link
    rel="stylesheet"
    href="../css/estilos.css"
>

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
    background: #fff;
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

button,
.btn {
    padding: 12px 20px;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
}

.btn {
    background: #222;
    color: #fff;
    text-decoration: none;
    display: inline-block;
}

.mensaje,
.error,
.info {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
}

.mensaje {
    background: #d4edda;
    color: #155724;
}

.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.info {
    background: #d9edf7;
    color: #31708f;
}

.datos-corredor {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 25px;
}

.precio {
    font-size: 28px;
    font-weight: bold;
    margin-top: 10px;
}

.radio {
    display: flex;
    gap: 20px;
}

.radio label {
    font-weight: normal;
    display: flex;
    align-items: center;
    gap: 5px;
}

.radio input {
    width: auto;
}

.rango {
    margin-top: 5px;
    color: #666;
    font-size: 14px;
}

.acciones {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.debug {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
    font-family: monospace;
}

</style>

</head>

<body>

<?php require_once "../includes/menu.php"; ?>

<div class="contenedor">

<h1>Inscripción</h1>


<?php if ($error !== ""): ?>

<div class="error">

<strong>Se produjo un error:</strong><br>

<?= e($error) ?>

</div>

<?php endif; ?>


<?php if (isset($_GET["ok"])): ?>

<div class="mensaje">

Inscripción realizada correctamente.

<br>

Dorsal asignado:

<strong>
<?= e($_GET["dorsal"] ?? "") ?>
</strong>

</div>

<?php endif; ?>


<form method="GET">

<div class="campo">

<label>DNI</label>

<input
    type="text"
    name="dni"
    value="<?= e(
        $_GET["dni"] ??
        ($corredor["dni"] ?? "")
    ) ?>"
    autofocus
    required
>

</div>


<button
    type="submit"
    class="btn"
>
Buscar corredor
</button>

</form>


<?php if ($corredor): ?>

<hr>


<div class="datos-corredor">

<strong>

<?= e($corredor["apellido"]) ?>,
<?= e($corredor["nombre"]) ?>

</strong>

<br>

DNI:
<?= e($corredor["dni"]) ?>


<?php if (!empty($corredor["email"])): ?>

<br>

Email:
<?= e($corredor["email"]) ?>

<?php endif; ?>

</div>


<?php if ($ya_inscripto): ?>

<div class="info">

<strong>
Este corredor ya está inscripto.
</strong>

<br>

Dorsal:
<?= e($ya_inscripto["dorsal"]) ?>

·

<?= e($ya_inscripto["distancia_nombre"]) ?>

(<?= e($ya_inscripto["distancia_km"]) ?> km)

<br>

Categoría:
<?= e(
    $ya_inscripto["categoria_nombre"]
    ?? "Sin asignar"
) ?>

</div>


<div class="acciones">

<a
    class="btn"
    href="acreditacion.php?dni=<?= urlencode($corredor["dni"]) ?>"
>
Ir a acreditación
</a>

</div>


<?php else: ?>


<form method="POST">

<input
    type="hidden"
    name="corredor_id"
    value="<?= e($corredor["id"]) ?>"
>


<div class="campo">

<label>Distancia</label>

<select
    name="distancia_id"
    id="distancia_id"
    required
>

<option value="">
Seleccionar distancia
</option>


<?php foreach ($distancias as $distancia): ?>

<option value="<?= e($distancia["id"]) ?>">

<?= e($distancia["nombre"]) ?>

-

<?= e($distancia["distancia_km"]) ?>

km

</option>

<?php endforeach; ?>

</select>


<div
    id="rango_dorsal"
    class="rango"
></div>

</div>


<div class="campo">

<label>Remera</label>

<div class="radio">

<label>

<input
    type="radio"
    name="remera"
    value="1"
    checked
>

Con remera

</label>


<label>

<input
    type="radio"
    name="remera"
    value="0"
>

Sin remera

</label>

</div>

</div>


<div
    class="campo"
    id="campo_talle"
>

<label>Talle de remera</label>

<select
    name="talle_remera"
    id="talle_remera"
>

<option value="">
Seleccionar talle
</option>

<option>XS</option>
<option>S</option>
<option>M</option>
<option>L</option>
<option>XL</option>
<option>XXL</option>

</select>

</div>


<div class="campo">

<label>Categoría</label>

<select name="categoria_id">

<option value="">
Seleccionar categoría
</option>


<?php foreach ($categorias as $categoria): ?>

<option value="<?= e($categoria["id"]) ?>">

<?= e($categoria["nombre"]) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="campo">

<label>Dorsal</label>

<input
    type="number"
    name="dorsal"
    id="dorsal"
    placeholder="Vacío = asignación automática"
>

<div class="rango">

Si se deja vacío,
se asignará el primer dorsal libre del rango.

</div>

</div>


<div class="campo">

<label>Descuento</label>

<input
    type="number"
    name="descuento_monto"
    id="descuento_monto"
    value="0"
    min="0"
    step="0.01"
>

</div>


<div class="campo">

<label>Motivo del descuento</label>

<input
    type="text"
    name="descuento_motivo"
    placeholder="Ej.: TEAM, sponsor, cortesía..."
>

</div>


<h3>Pago inicial (opcional)</h3>


<div
    class="grid"
    style="
        display:grid;
        grid-template-columns:
        repeat(2,minmax(0,1fr));
        gap:15px
    "
>

<div class="campo">

<label>Monto pagado</label>

<input
    type="number"
    name="pago_inicial"
    value="0"
    min="0"
    step="0.01"
>

</div>


<div class="campo">

<label>Medio de pago</label>

<input
    type="text"
    name="medio_pago"
    placeholder="Efectivo, transferencia, etc."
>

</div>


<div class="campo">

<label>Comprobante</label>

<input
    type="text"
    name="comprobante"
>

</div>


<div class="campo">

<label>Observación</label>

<input
    type="text"
    name="observacion_pago"
>

</div>

</div>


<div class="campo">

<label>Total</label>

<div
    class="precio"
    id="precio"
>
$0,00
</div>

</div>


<button
    type="submit"
    class="btn"
>
Confirmar inscripción
</button>

</form>


<?php endif; ?>

<?php endif; ?>

</div>


<script>

const precios =
<?= json_encode($precios_js) ?>;

const distancias =
<?= json_encode($distancias) ?>;


function formatoMoneda(valor) {

    return new Intl.NumberFormat(
        'es-AR',
        {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2
        }
    ).format(valor);

}


function actualizar() {

    const select =
        document.getElementById(
            'distancia_id'
        );

    const rango =
        document.getElementById(
            'rango_dorsal'
        );

    const precio =
        document.getElementById(
            'precio'
        );

    const desc =
        document.getElementById(
            'descuento_monto'
        );

    const rem =
        document.querySelector(
            'input[name="remera"]:checked'
        );


    if (!select || !precio) {
        return;
    }


    const d =
        distancias.find(
            x =>
                parseInt(x.id) ===
                parseInt(select.value)
        );


    rango.textContent =
        d
        ? 'Dorsales disponibles: ' +
          d.dorsal_desde +
          ' a ' +
          d.dorsal_hasta
        : '';


    const talle =
        document.getElementById(
            'talle_remera'
        );

    const campo =
        document.getElementById(
            'campo_talle'
        );


    if (
        rem &&
        rem.value === '1'
    ) {

        campo.style.display = 'block';

        talle.disabled = false;

    } else {

        campo.style.display = 'none';

        talle.disabled = true;

        talle.value = '';

    }


    if (
        !precios[select.value] ||
        !rem
    ) {

        precio.textContent =
            formatoMoneda(0);

        return;
    }


    let base =
        parseFloat(
            rem.value === '1'
            ? precios[
                select.value
              ].con_remera
            : precios[
                select.value
              ].sin_remera
        );


    let descuento =
        parseFloat(
            desc?.value || 0
        );


    let total =
        Math.max(
            0,
            base - descuento
        );


    precio.textContent =
        formatoMoneda(total);

}


document.addEventListener(
    'DOMContentLoaded',
    () => {

        document
            .getElementById(
                'distancia_id'
            )
            ?.addEventListener(
                'change',
                actualizar
            );


        document
            .querySelectorAll(
                'input[name="remera"]'
            )
            .forEach(
                x =>
                    x.addEventListener(
                        'change',
                        actualizar
                    )
            );


        document
            .getElementById(
                'descuento_monto'
            )
            ?.addEventListener(
                'input',
                actualizar
            );


        actualizar();

    }
);

</script>

</body>

</html>

