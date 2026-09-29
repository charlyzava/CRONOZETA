<?php
 require_once "../config/database.php";

$sql = "SELECT * FROM corredores ORDER BY apellido, nombre";
$stmt = $pdo->query($sql);
$corredores = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Corredores</title>

    <link rel="stylesheet" href="../css/estilos.css">
</head>

<body>

<header class="navbar">

    <div class="logo">
        CronoTrail
    </div>

    <nav>
        <a href="../index.php">Inicio</a>
        <a href="index.php">Corredores</a>
        <a href="../inscripciones/index.php">Inscripciones</a>
        <a href="#">Cronometraje</a>
        <a href="#">Clasificaciones</a>
    </nav>

</header>


<main class="contenedor">

    <div class="cabecera-pagina">

        <div>
            <h1>Corredores</h1>
            <p>Administración de corredores</p>
        </div>

        <a href="nuevo.php" class="boton">
            + Nuevo corredor
        </a>

    </div>


    <div class="tabla-contenedor">

        <table>

            <thead>

                <tr>
                    <th>ID</th>
                    <th>Apellido</th>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Sexo</th>
                    <th>Fecha nacimiento</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Acciones</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($corredores as $corredor): ?>

                    <tr>

                        <td>
                            <?= $corredor['id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($corredor['apellido']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($corredor['nombre']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($corredor['dni'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($corredor['sexo'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($corredor['fecha_nacimiento'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($corredor['email'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($corredor['telefono'] ?? '') ?>
                        </td>

                        <td>

                            <a href="editar.php?id=<?= $corredor['id'] ?>">
                                Editar
                            </a>

                            |

                            <a
                                href="eliminar.php?id=<?= $corredor['id'] ?>"
                                onclick="return confirm('¿Eliminar este corredor?')"
                            >
                                Eliminar
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>