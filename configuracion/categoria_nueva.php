<?php

require_once "../config/database.php";

$evento_id = 1;

$errores = [];

$nombre = "";
$sexo = "";
$edad_min = "";
$edad_max = "";

$distancias_seleccionadas = [];


// =========================================================
// CARGAR DISTANCIAS
// =========================================================

$stmt = $pdo->query("
    SELECT *
    FROM distancias
    ORDER BY distancia_km
");

$distancias = $stmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// PROCESAR FORMULARIO
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre'] ?? '');

    $sexo = $_POST['sexo'] ?? '';

    $edad_min = trim($_POST['edad_min'] ?? '');

    $edad_max = trim($_POST['edad_max'] ?? '');

    $distancias_seleccionadas =
        isset($_POST['distancias'])
            ? array_map('intval', $_POST['distancias'])
            : [];


    // =====================================================
    // VALIDACIONES
    // =====================================================

    if ($nombre === '') {

        $errores[] =
            "Debe ingresar el nombre de la categoría.";

    }


    if (
        $sexo !== '' &&
        $sexo !== 'M' &&
        $sexo !== 'F'
    ) {

        $errores[] =
            "El sexo seleccionado no es válido.";

    }


    if (
        $edad_min !== '' &&
        !is_numeric($edad_min)
    ) {

        $errores[] =
            "La edad mínima no es válida.";

    }


    if (
        $edad_max !== '' &&
        !is_numeric($edad_max)
    ) {

        $errores[] =
            "La edad máxima no es válida.";

    }


    if (
        $edad_min !== '' &&
        $edad_max !== '' &&
        intval($edad_min) > intval($edad_max)
    ) {

        $errores[] =
            "La edad mínima no puede ser mayor que la edad máxima.";

    }


    if (count($distancias_seleccionadas) === 0) {

        $errores[] =
            "Debe seleccionar al menos una distancia.";

    }


    // =====================================================
    // VALIDAR QUE LAS DISTANCIAS EXISTAN
    // =====================================================

    if (count($distancias_seleccionadas) > 0) {

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($distancias_seleccionadas),
                '?'
            )
        );


        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM distancias
            WHERE id IN ($placeholders)
        ");


        $stmt->execute(
            $distancias_seleccionadas
        );


        $cantidad_validas =
            intval($stmt->fetchColumn());


        if (
            $cantidad_validas
            != count($distancias_seleccionadas)
        ) {

            $errores[] =
                "Una o más distancias seleccionadas no existen.";

        }

    }


    // =====================================================
    // VERIFICAR CATEGORÍA DUPLICADA
    // =====================================================

    if (count($errores) === 0) {

        if ($sexo === '') {

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM categorias
                WHERE evento_id = ?
                AND nombre = ?
                AND sexo IS NULL
            ");

            $stmt->execute([
                $evento_id,
                $nombre
            ]);

        } else {

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM categorias
                WHERE evento_id = ?
                AND nombre = ?
                AND sexo = ?
            ");

            $stmt->execute([
                $evento_id,
                $nombre,
                $sexo
            ]);

        }


        if (intval($stmt->fetchColumn()) > 0) {

            $errores[] =
                "Ya existe una categoría con ese nombre y sexo.";

        }

    }


    // =====================================================
    // GUARDAR
    // =====================================================

    if (count($errores) === 0) {

        try {

            $pdo->beginTransaction();


            // ---------------------------------------------
            // CATEGORÍA
            // ---------------------------------------------

            $stmt = $pdo->prepare("
                INSERT INTO categorias
                (
                    evento_id,
                    nombre,
                    sexo,
                    edad_min,
                    edad_max
                )
                VALUES (?, ?, ?, ?, ?)
            ");


            $stmt->execute([

                $evento_id,

                $nombre,

                $sexo !== ''
                    ? $sexo
                    : null,

                $edad_min !== ''
                    ? intval($edad_min)
                    : null,

                $edad_max !== ''
                    ? intval($edad_max)
                    : null

            ]);


            $categoria_id =
                $pdo->lastInsertId();


            // ---------------------------------------------
            // RELACIÓN CON DISTANCIAS
            // ---------------------------------------------

            $stmtDistancia = $pdo->prepare("
                INSERT INTO categoria_distancia
                (
                    categoria_id,
                    distancia_id
                )
                VALUES (?, ?)
            ");


            foreach (
                $distancias_seleccionadas
                as $distancia_id
            ) {

                $stmtDistancia->execute([

                    $categoria_id,

                    $distancia_id

                ]);

            }


            $pdo->commit();


            header(
                "Location: categorias.php"
            );

            exit;


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errores[] =
                "Error de base de datos: "
                . $e->getMessage();

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errores[] =
                "Error: "
                . $e->getMessage();

        }

    }

}

?>

<?php include "../includes/menu.php"; ?>


<style>

/* =========================================================
   CONTENEDOR
========================================================= */

.formulario-categoria {

    max-width: 850px;

    margin: 30px auto;

    padding: 0 20px;

}


.formulario-categoria h1 {

    margin-bottom: 5px;

}


.formulario-subtitulo {

    color: #777;

    margin-bottom: 30px;

}


/* =========================================================
   TARJETA
========================================================= */

.form-card {

    background: white;

    border-radius: 9px;

    padding: 28px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.08);

}


/* =========================================================
   CAMPOS
========================================================= */

.campo {

    margin-bottom: 22px;

}


.campo > label {

    display: block;

    font-weight: 600;

    margin-bottom: 7px;

}


.campo input,
.campo select {

    width: 100%;

    box-sizing: border-box;

    padding: 11px 12px;

    border: 1px solid #ccc;

    border-radius: 5px;

    font-size: 14px;

}


.campo input:focus,
.campo select:focus {

    outline: none;

    border-color: #f28c28;

    box-shadow:
        0 0 0 2px
        rgba(242,140,40,.12);

}


/* =========================================================
   EDADES
========================================================= */

.fila-edades {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;

}


/* =========================================================
   DISTANCIAS
========================================================= */

.titulo-distancias {

    margin-bottom: 5px;

}


.descripcion-distancias {

    color: #777;

    font-size: 13px;

    margin-bottom: 15px;

}


.distancias-checkboxes {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;

}


.distancia-checkbox {

    position: relative;

}


.distancia-checkbox input {

    position: absolute;

    opacity: 0;

}


.distancia-checkbox label {

    display: block;

    padding: 15px;

    border: 2px solid #ddd;

    border-radius: 7px;

    cursor: pointer;

    transition: .15s;

    background: #fafafa;

}


.distancia-checkbox label:hover {

    border-color: #f28c28;

}


.distancia-checkbox input:checked + label {

    border-color: #f28c28;

    background: #fff5ea;

}


.distancia-nombre {

    font-weight: 700;

    display: block;

    margin-bottom: 3px;

}


.distancia-km {

    color: #777;

    font-size: 13px;

}


/* =========================================================
   ERRORES
========================================================= */

.errores {

    background: #fff0f0;

    border: 1px solid #e0a0a0;

    color: #9b2226;

    padding: 15px 18px;

    border-radius: 6px;

    margin-bottom: 20px;

}


.errores div {

    margin-bottom: 5px;

}


/* =========================================================
   BOTONES
========================================================= */

.acciones-formulario {

    display: flex;

    gap: 10px;

    margin-top: 28px;

}


.boton {

    display: inline-block;

    padding: 11px 20px;

    background: #f28c28;

    color: white;

    text-decoration: none;

    border: none;

    border-radius: 6px;

    cursor: pointer;

    font-weight: 600;

}


.boton:hover {

    background: #d97616;

}


.boton-secundario {

    display: inline-block;

    padding: 11px 20px;

    background: #eeeeee;

    color: #333;

    text-decoration: none;

    border-radius: 6px;

}


.boton-secundario:hover {

    background: #dddddd;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 650px) {

    .fila-edades {

        grid-template-columns: 1fr;

        gap: 0;

    }


    .distancias-checkboxes {

        grid-template-columns: 1fr;

    }


    .form-card {

        padding: 20px;

    }


    .acciones-formulario {

        flex-direction: column;

    }

}

</style>


<div class="formulario-categoria">

    <h1>Nueva categoría</h1>

    <div class="formulario-subtitulo">

        Configure la categoría y las distancias
        donde estará disponible.

    </div>


    <?php if (count($errores) > 0): ?>

        <div class="errores">

            <?php foreach ($errores as $error): ?>

                <div>
                    • <?= htmlspecialchars($error) ?>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <div class="form-card">

        <form method="POST">


            <!-- =========================================
                 NOMBRE
            ========================================== -->

            <div class="campo">

                <label>
                    Nombre de la categoría
                </label>

                <input
                    type="text"
                    name="nombre"
                    maxlength="50"
                    value="<?= htmlspecialchars($nombre) ?>"
                    placeholder="Ej.: 30 a 39 años"
                    required
                >

            </div>


            <!-- =========================================
                 SEXO
            ========================================== -->

            <div class="campo">

                <label>
                    Sexo
                </label>

                <select name="sexo">

                    <option
                        value=""
                        <?= $sexo === ''
                            ? 'selected'
                            : '' ?>
                    >
                        Ambos
                    </option>

                    <option
                        value="M"
                        <?= $sexo === 'M'
                            ? 'selected'
                            : '' ?>
                    >
                        Masculino
                    </option>

                    <option
                        value="F"
                        <?= $sexo === 'F'
                            ? 'selected'
                            : '' ?>
                    >
                        Femenino
                    </option>

                </select>

            </div>


            <!-- =========================================
                 EDAD
            ========================================== -->

            <div class="campo">

                <label>
                    Rango de edad
                </label>


                <div class="fila-edades">

                    <input
                        type="number"
                        name="edad_min"
                        min="0"
                        max="120"
                        value="<?= htmlspecialchars($edad_min) ?>"
                        placeholder="Edad mínima"
                    >


                    <input
                        type="number"
                        name="edad_max"
                        min="0"
                        max="120"
                        value="<?= htmlspecialchars($edad_max) ?>"
                        placeholder="Edad máxima"
                    >

                </div>

            </div>


            <!-- =========================================
                 DISTANCIAS
            ========================================== -->

            <div class="campo">

                <label class="titulo-distancias">
                    Distancias
                </label>

                <div class="descripcion-distancias">

                    Seleccione una o más distancias
                    en las que estará disponible esta categoría.

                </div>


                <div class="distancias-checkboxes">


                    <?php foreach ($distancias as $distancia): ?>

                        <div class="distancia-checkbox">

                            <input
                                type="checkbox"
                                id="distancia_<?= $distancia['id'] ?>"
                                name="distancias[]"
                                value="<?= $distancia['id'] ?>"
                                <?= in_array(
                                    $distancia['id'],
                                    $distancias_seleccionadas
                                )
                                    ? 'checked'
                                    : '' ?>
                            >


                            <label
                                for="distancia_<?= $distancia['id'] ?>"
                            >

                                <span class="distancia-nombre">

                                    <?= htmlspecialchars(
                                        $distancia['nombre']
                                    ) ?>

                                </span>


                                <span class="distancia-km">

                                    <?= htmlspecialchars(
                                        $distancia['distancia_km']
                                    ) ?>

                                    km

                                </span>

                            </label>

                        </div>

                    <?php endforeach; ?>


                </div>

            </div>


            <!-- =========================================
                 BOTONES
            ========================================== -->

            <div class="acciones-formulario">

                <button
                    type="submit"
                    class="boton"
                >
                    Guardar categoría
                </button>


                <a
                    href="categorias.php"
                    class="boton-secundario"
                >
                    Cancelar
                </a>

            </div>


        </form>

    </div>

</div>