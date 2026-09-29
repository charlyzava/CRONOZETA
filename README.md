Perfecto. Con estas tablas ya podemos armarlo de forma bastante limpia. Yo haría primero Corredores y después Inscripciones, dejando que la inscripción determine automáticamente la categoría a partir del sexo y la edad del corredor.

Hay un detalle importante: como categorias no tiene distancia_id, actualmente una categoría es independiente de la distancia. Eso está bien si, por ejemplo, las categorías son 18-29, 30-39, etc. y se aplican a todas las distancias.

Te propongo esta estructura:

cronometraje/
│
├── config/
│   └── database.php
│
├── corredores/
│   ├── index.php
│   ├── nuevo.php
│   ├── editar.php
│   └── eliminar.php
│
├── inscripciones/
│   ├── index.php
│   ├── nuevo.php
│   ├── editar.php
│   └── eliminar.php
│
├── css/
│   └── estilos.css
│
└── index.php
1. ABM de corredores

Primero hacemos que funcione completamente esta parte:

CORREDORES

Buscar: [________________] [Buscar]

ID | Apellido | Nombre | DNI | Sexo | Fecha nacimiento | Acciones
---------------------------------------------------------------
1  | Pérez    | Juan   | ... | M    | 1990-05-10       | Editar
2  | González | María  | ... | F    | 1988-02-15       | Editar
corredores/index.php
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
2. Alta de corredor

corredores/nuevo.php

<?php

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $dni = trim($_POST["dni"] ?? "");
    $sexo = $_POST["sexo"] ?? null;
    $fecha_nacimiento = $_POST["fecha_nacimiento"] ?? null;
    $email = trim($_POST["email"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");

    if ($nombre === "" || $apellido === "") {

        die("Nombre y apellido son obligatorios.");

    }

    $sql = "
        INSERT INTO corredores
        (nombre, apellido, dni, sexo, fecha_nacimiento, email, telefono)
        VALUES
        (:nombre, :apellido, :dni, :sexo, :fecha_nacimiento, :email, :telefono)
    ";

    $stmt = $pdo->prepare($sql);

    try {

        $stmt->execute([
            ":nombre" => $nombre,
            ":apellido" => $apellido,
            ":dni" => $dni !== "" ? $dni : null,
            ":sexo" => $sexo !== "" ? $sexo : null,
            ":fecha_nacimiento" => $fecha_nacimiento !== "" ? $fecha_nacimiento : null,
            ":email" => $email !== "" ? $email : null,
            ":telefono" => $telefono !== "" ? $telefono : null
        ]);

        header("Location: index.php");
        exit;

    } catch (PDOException $e) {

        if ($e->getCode() == 23000) {

            die("El DNI ingresado ya existe.");

        }

        die("Error al guardar el corredor: " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Nuevo corredor</title>

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

        <a href="../inscripciones/index.php">
            Inscripciones
        </a>

    </nav>

</header>


<main class="contenedor">

    <h1>Nuevo corredor</h1>

    <form method="POST" class="formulario">

        <label>
            Nombre

            <input
                type="text"
                name="nombre"
                maxlength="50"
                required
            >

        </label>


        <label>
            Apellido

            <input
                type="text"
                name="apellido"
                maxlength="50"
                required
            >

        </label>


        <label>
            DNI

            <input
                type="text"
                name="dni"
                maxlength="20"
            >

        </label>


        <label>
            Sexo

            <select name="sexo">

                <option value="">
                    Seleccionar
                </option>

                <option value="M">
                    Masculino
                </option>

                <option value="F">
                    Femenino
                </option>

            </select>

        </label>


        <label>
            Fecha de nacimiento

            <input
                type="date"
                name="fecha_nacimiento"
            >

        </label>


        <label>
            Email

            <input
                type="email"
                name="email"
                maxlength="150"
            >

        </label>


        <label>
            Teléfono

            <input
                type="text"
                name="telefono"
                maxlength="30"
            >

        </label>


        <div class="acciones-formulario">

            <button type="submit" class="boton">
                Guardar corredor
            </button>

            <a href="index.php">
                Cancelar
            </a>

        </div>

    </form>

</main>

</body>
</html>

Con esto ya podemos probar:

Nuevo corredor → MySQL → vuelve al listado.

3. La inscripción

Acá empieza lo interesante.

Cuando hagamos:

Nueva inscripción

quiero que el formulario sea aproximadamente:

┌─────────────────────────────────────┐
│ NUEVA INSCRIPCIÓN                   │
│                                     │
│ Evento                              │
│ [ Desafío Bella Vista 2026       ▼] │
│                                     │
│ Corredor                            │
│ [ Juan Pérez - DNI 12345678      ▼] │
│                                     │
│ Distancia                           │
│ [ Cóndor - 21 km                  ▼] │
│                                     │
│ Categoría                           │
│ [ Caballeros 30-39               ▼] │
│                                     │
│ Dorsal                              │
│ [ 105                              ]│
│                                     │
│ Estado                              │
│ [ Confirmado                      ▼] │
│                                     │
│        [ GUARDAR INSCRIPCIÓN ]      │
└─────────────────────────────────────┘

Pero hay una decisión que te recomiendo tomar: la categoría no debería ser elegida manualmente si podemos calcularla.

Por ejemplo:

Corredor:
Juan Pérez

Sexo:
M

Fecha nacimiento:
15/06/1990

Evento:
08/11/2026

El sistema calcula:

Edad al momento del evento = 36 años

Y busca:

sexo = 'M'
edad_min <= 36
edad_max >= 36

Entonces obtiene, por ejemplo:

Masculino 30-39

y guarda automáticamente:

categoria_id = 4

Esto evita que alguien inscriba accidentalmente a un corredor de 36 años en una categoría 18–29.

4. También haría automático el dorsal

Podemos hacer que al seleccionar la distancia el sistema sugiera el siguiente dorsal.

Por ejemplo:

3K  → 001-099
9K  → 100-199
13K → 200-299
21K → 300-399

Pero esto depende de cómo quieras numerar los dorsales.

Si todavía no definiste esa regla, podemos simplemente empezar con:

Dorsal: [ 101 ]

y validar en PHP/MySQL que no exista otro:

evento_id + dorsal

Así nunca tendrías:

#101 Juan Pérez
#101 Pedro González

dentro del mismo evento.

Una modificación que te recomiendo en MySQL

En inscripciones yo agregaría una restricción única:

ALTER TABLE inscripciones
ADD UNIQUE KEY uk_evento_dorsal (evento_id, dorsal);

Y también:

ALTER TABLE inscripciones
ADD UNIQUE KEY uk_evento_corredor_distancia
(evento_id, corredor_id, distancia_id);

Esto hace que la propia base de datos impida duplicaciones, incluso si por error el PHP no las detecta.

Y hay otra cosa importante

Tus tablas ya están tomando una forma muy buena para el sistema que querés desarrollar:

CORREDOR
   │
   │
   ▼
INSCRIPCIÓN
   │
   ├── EVENTO
   │
   ├── DISTANCIA
   │
   ├── CATEGORÍA
   │
   └── DORSAL
          │
          ▼
    CRONOMETRAJE
          │
          ▼
      RESULTADO

El corredor es la persona. La inscripción es su participación en una carrera concreta.

Eso permite que Juan Pérez tenga:

Desafío Bella Vista 2026
21K
Dorsal 125
Categoría 30-39

Desafío Bella Vista 2027
13K
Dorsal 48
Categoría 30-39

sin duplicar a Juan Pérez en la tabla corredores.

Si querés, el siguiente paso puede ser que 
armemos todo el ABM de corredores completo (listado + búsqueda + alta + edición + eliminación) y después 
hacemos el de inscripciones con cálculo automático de categoría y validación de dorsal.