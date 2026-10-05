<?php

require_once "config/database.php";

$sql = "SELECT * FROM distancias ORDER BY distancia_km ASC";
$stmt = $pdo->query($sql);
$distancias = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sistema de Cronometraje</title>

    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>

    <?php require_once "includes/menu.php"; ?>


    <main>

        <section class="hero">

            <div class="hero-contenido">

                <p class="hero-superior">
                    SISTEMA DE CRONOMETRAJE
                </p>

                <h1>
                    Desafío Bella Vista
                </h1>

                <p>
                    Gestión de corredores, tiempos y clasificaciones
                    para carreras de montaña.
                </p>

                <a href="#distancias" class="boton">
                    Ver distancias
                </a>

            </div>

        </section>


        <section id="distancias" class="distancias">

            <h2>Distancias</h2>

            <p class="subtitulo">
                Circuitos disponibles en el evento
            </p>


            <div class="grid-distancias">

                <?php foreach ($distancias as $distancia): ?>

                    <article class="card-distancia">

                        <h3>
                            <?= htmlspecialchars($distancia['nombre']) ?>
                        </h3>

                        <div class="kilometros">
                            <?= htmlspecialchars($distancia['distancia_km']) ?>
                            <span>KM</span>
                        </div>

                        <p>
                            Desnivel:
                            <?= htmlspecialchars($distancia['desnivel_m']) ?> m+
                        </p>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

    </main>


    <footer>

        <p>
            Sistema de Cronometraje © <?= date("Y") ?>
        </p>

    </footer>

</body>

</html>