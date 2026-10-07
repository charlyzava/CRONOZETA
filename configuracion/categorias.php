<?php

require_once "../config/database.php";

$evento_id = 1;


// ---------------------------------------------------------
// CARGAR CATEGORÍAS
// ---------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.nombre,
        c.sexo,
        c.edad_min,
        c.edad_max,
        GROUP_CONCAT(
            CONCAT(
                d.nombre,
                ' (',
                d.distancia_km,
                ' km)'
            )
            ORDER BY d.distancia_km
            SEPARATOR ', '
        ) AS distancias
    FROM categorias c

    LEFT JOIN categoria_distancia cd
        ON cd.categoria_id = c.id

    LEFT JOIN distancias d
        ON d.id = cd.distancia_id

    WHERE c.evento_id = ?

    GROUP BY
        c.id,
        c.nombre,
        c.sexo,
        c.edad_min,
        c.edad_max

    ORDER BY
        c.sexo,
        c.edad_min,
        c.edad_max
");

$stmt->execute([$evento_id]);

$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include "../includes/menu.php"; ?>


<style>

.contenedor-categorias {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}


/* ---------------------------------------------------------
   ENCABEZADO
--------------------------------------------------------- */

.encabezado-categorias {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 20px;
}

.encabezado-categorias h1 {
    margin: 0 0 5px 0;
}

.encabezado-categorias p {
    margin: 0;
    color: #777;
}


/* ---------------------------------------------------------
   BOTONES
--------------------------------------------------------- */

.boton {
    display: inline-block;
    padding: 10px 18px;
    background: #f28c28;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.boton:hover {
    background: #d97616;
}

.boton-secundario {
    display: inline-block;
    padding: 8px 13px;
    background: #eeeeee;
    color: #333;
    text-decoration: none;
    border-radius: 5px;
    font-size: 13px;
}

.boton-secundario:hover {
    background: #dddddd;
}

.boton-peligro {
    display: inline-block;
    padding: 8px 13px;
    background: #dc3545;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-size: 13px;
}

.boton-peligro:hover {
    background: #b52a37;
}


/* ---------------------------------------------------------
   TABLA
--------------------------------------------------------- */

.tabla-categorias {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.tabla-categorias th {
    background: #333;
    color: white;
    padding: 13px 12px;
    text-align: left;
    font-size: 13px;
}

.tabla-categorias td {
    padding: 13px 12px;
    border-bottom: 1px solid #eeeeee;
    font-size: 14px;
    vertical-align: middle;
}

.tabla-categorias tbody tr:hover {
    background: #fafafa;
}

.tabla-categorias tbody tr:last-child td {
    border-bottom: none;
}


/* ---------------------------------------------------------
   DISTANCIAS
--------------------------------------------------------- */

.distancias-lista {
    line-height: 1.7;
}

.distancia-tag {
    display: inline-block;
    background: #f4f4f4;
    border: 1px solid #ddd;
    padding: 3px 7px;
    border-radius: 4px;
    margin: 2px 3px 2px 0;
    font-size: 12px;
}


/* ---------------------------------------------------------
   SEXO
--------------------------------------------------------- */

.sexo-m {
    color: #1769aa;
    font-weight: 600;
}

.sexo-f {
    color: #b02a75;
    font-weight: 600;
}

.sexo-ambos {
    color: #555;
    font-weight: 600;
}


/* ---------------------------------------------------------
   EDAD
--------------------------------------------------------- */

.edad {
    white-space: nowrap;
}


/* ---------------------------------------------------------
   ACCIONES
--------------------------------------------------------- */

.acciones-tabla {
    white-space: nowrap;
}

.acciones-tabla a {
    margin-right: 5px;
}


/* ---------------------------------------------------------
   SIN DATOS
--------------------------------------------------------- */

.sin-datos {
    background: white;
    border-radius: 8px;
    padding: 35px;
    text-align: center;
    color: #777;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}


/* ---------------------------------------------------------
   RESPONSIVE
--------------------------------------------------------- */

@media (max-width: 800px) {

    .encabezado-categorias {
        flex-direction: column;
        align-items: flex-start;
    }

    .tabla-categorias {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }

}

</style>


<div class="contenedor-categorias">


    <div class="encabezado-categorias">

        <div>

            <h1>Categorías</h1>

            <p>
                Categorías disponibles para el evento
            </p>

        </div>


        <a
            href="categoria_nueva.php"
            class="boton"
        >
            + Nueva categoría
        </a>

    </div>


    <?php if (count($categorias) === 0): ?>

        <div class="sin-datos">

            <h3>No hay categorías configuradas</h3>

            <p>
                Puede comenzar creando la primera categoría.
            </p>

            <br>

            <a
                href="categoria_nueva.php"
                class="boton"
            >
                + Nueva categoría
            </a>

        </div>


    <?php else: ?>


        <table class="tabla-categorias">

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Categoría</th>
                    <th>Sexo</th>
                    <th>Edad</th>
                    <th>Distancias</th>
                    <th>Acciones</th>

                </tr>

            </thead>


            <tbody>


                <?php foreach ($categorias as $categoria): ?>

                    <tr>


                        <td>
                            <?= htmlspecialchars($categoria['id']) ?>
                        </td>


                        <td>

                            <strong>
                                <?= htmlspecialchars($categoria['nombre']) ?>
                            </strong>

                        </td>


                        <td>

                            <?php if ($categoria['sexo'] === 'M'): ?>

                                <span class="sexo-m">
                                    Masculino
                                </span>

                            <?php elseif ($categoria['sexo'] === 'F'): ?>

                                <span class="sexo-f">
                                    Femenino
                                </span>

                            <?php else: ?>

                                <span class="sexo-ambos">
                                    Ambos
                                </span>

                            <?php endif; ?>

                        </td>


                        <td class="edad">

                            <?php

                            if (
                                $categoria['edad_min'] !== null &&
                                $categoria['edad_max'] !== null
                            ) {

                                echo $categoria['edad_min']
                                    . ' - '
                                    . $categoria['edad_max']
                                    . ' años';

                            } elseif ($categoria['edad_min'] !== null) {

                                echo 'Desde '
                                    . $categoria['edad_min']
                                    . ' años';

                            } elseif ($categoria['edad_max'] !== null) {

                                echo 'Hasta '
                                    . $categoria['edad_max']
                                    . ' años';

                            } else {

                                echo 'Todas las edades';

                            }

                            ?>

                        </td>


                        <td>

                            <?php if ($categoria['distancias']): ?>

                                <div class="distancias-lista">

                                    <?php

                                    $lista =
                                        explode(
                                            ', ',
                                            $categoria['distancias']
                                        );

                                    foreach ($lista as $distancia):

                                    ?>

                                        <span class="distancia-tag">

                                            <?= htmlspecialchars($distancia) ?>

                                        </span>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <span style="color:#999;">
                                    Sin asignar
                                </span>

                            <?php endif; ?>

                        </td>


                        <td class="acciones-tabla">

                            <a
                                href="categoria_modificar.php?id=<?= $categoria['id'] ?>"
                                class="boton-secundario"
                            >
                                Modificar
                            </a>


                            <a
                                href="categoria_eliminar.php?id=<?= $categoria['id'] ?>"
                                class="boton-peligro"
                                onclick="return confirm('¿Está seguro de eliminar esta categoría?');"
                            >
                                Eliminar
                            </a>

                        </td>


                    </tr>

                <?php endforeach; ?>


            </tbody>

        </table>


    <?php endif; ?>


</div>