<?php

/*

 * CronoTrail - Importación de inscriptos desde Google Forms / Excel

 * Compatible con PHP 7.2+. Admite .xlsx (requiere ZipArchive) y .csv.

 * No crea pagos. Evento Desafío Bella Vista: ID 1.

 */

ini_set('display_errors', 1);

error_reporting(E_ALL);

require_once "../config/database.php";

if (!isset($pdo)) { die('No se encontró la conexión PDO en ../config/database.php'); }



$evento_id = 1;

$errores = [];

$avisos = [];

$filas = [];

$resultado = null;



function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function normalizar($v) {

    $v = trim((string)$v);

    $v = str_replace("\xC2\xA0", ' ', $v);

    return preg_replace('/\s+/', ' ', $v);

}

function normalizarTelefono($valor) {
    $valor = trim((string)$valor);

    // Convierte teléfonos que Excel entrega en notación científica.
    if (preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)[eE][+-]?\d+$/', $valor)) {
        return sprintf('%.0f', (float)$valor);
    }

    return $valor;
}

function fechaIso($valor) {

    $valor = trim((string)$valor);

    if ($valor === '') return null;

    $formatos = ['d/m/Y','j/n/Y','Y-m-d','d-m-Y','j-n-Y'];

    foreach ($formatos as $f) {

        $dt = DateTime::createFromFormat('!'.$f, $valor);

        if ($dt && $dt->format($f) === $valor) return $dt->format('Y-m-d');

    }

    // Fechas Excel almacenadas como número serial.

    if (is_numeric($valor) && (float)$valor > 20000 && (float)$valor < 80000) {

        return date('Y-m-d', strtotime('1899-12-30 +' . (int)$valor . ' days'));

    }

    return null;

}

function fechaHoraIso($valor) {

    $valor = trim((string)$valor);

    if ($valor === '') return null;

    // Serial de fecha/hora de Excel (días desde 1899-12-30).

    if (is_numeric($valor) && (float)$valor > 20000 && (float)$valor < 80000) {

        $segundos = (int)round(((float)$valor) * 86400);

        return gmdate('Y-m-d H:i:s', strtotime('1899-12-30 00:00:00 UTC') + $segundos);

    }

    foreach (['d/m/Y H:i:s','j/n/Y H:i:s','d/m/Y G:i:s','j/n/Y G:i:s','Y-m-d H:i:s','d/m/Y H:i','j/n/Y H:i'] as $f) {

        $dt = DateTime::createFromFormat('!'.$f, $valor);

        if ($dt) return $dt->format('Y-m-d H:i:s');

    }

    return null;

}

function sexoCodigo($v) {

    $v = mb_strtolower(trim((string)$v), 'UTF-8');

    if ($v === 'f' || strpos($v, 'femen') === 0) return 'F';

    if ($v === 'm' || strpos($v, 'masc') === 0) return 'M';

    return null;

}

function separarNombre($valor) {

    $valor = normalizar($valor);

    if (strpos($valor, ',') !== false) {

        $p = array_map('trim', explode(',', $valor, 2));

        return [$p[1] ?? '', $p[0] ?? '']; // Google Forms: Apellido, Nombre

    }

    $p = preg_split('/\s+/', $valor);

    if (count($p) < 2) return [$valor, ''];

    // En registros sin coma, se asume primer término = apellido; revisar en la vista previa.

    $apellido = array_shift($p);

    return [implode(' ', $p), $apellido];

}

function parseCsv($tmp) {

    $fh = fopen($tmp, 'r');

    if (!$fh) throw new Exception('No se pudo abrir el CSV.');

    $first = fgets($fh); rewind($fh);

    $delims = ["\t", ';', ',']; $delimiter = ','; $max = -1;

    foreach ($delims as $d) { $n = substr_count((string)$first, $d); if ($n > $max) {$max=$n; $delimiter=$d;} }

    $data = [];

    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {

        if (isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);

        $data[] = $row;

    }

    fclose($fh);

    return $data;

}

function parseXlsx($tmp) {

    if (!class_exists('ZipArchive')) throw new Exception('Este servidor no tiene ZipArchive habilitado. Exportá el archivo de Google Sheets como CSV UTF-8 e importá ese archivo.');

    $zip = new ZipArchive();

    if ($zip->open($tmp) !== true) throw new Exception('No se pudo abrir el archivo XLSX.');

    $shared = [];

    $ss = $zip->getFromName('xl/sharedStrings.xml');

    if ($ss !== false) {

        $xml = simplexml_load_string($ss);

        if ($xml) foreach ($xml->si as $si) {

            $s = ''; if (isset($si->t)) $s = (string)$si->t;

            else foreach ($si->r as $run) $s .= (string)$run->t;

            $shared[] = $s;

        }

    }

    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

    if ($sheet === false) { $zip->close(); throw new Exception('No se encontró la primera hoja del XLSX.'); }

    $xml = simplexml_load_string($sheet);

    if (!$xml) { $zip->close(); throw new Exception('No se pudo leer la hoja del XLSX.'); }

    $data = [];

    foreach ($xml->sheetData->row as $row) {

        $line = [];

        foreach ($row->c as $cell) {

            $ref = (string)$cell['r']; preg_match('/([A-Z]+)/', $ref, $m); $letters = $m[1] ?? 'A';

            $col = 0; for ($i=0; $i<strlen($letters); $i++) $col = $col*26 + ord($letters[$i])-64; $col--;

            $type = (string)$cell['t']; $val = isset($cell->v) ? (string)$cell->v : '';

            if ($type === 's') $val = $shared[(int)$val] ?? '';

            elseif ($type === 'inlineStr') $val = (string)$cell->is->t;

            $line[$col] = $val;

        }

        if ($line) { $max = max(array_keys($line)); $ordered = array_fill(0, $max+1, ''); foreach ($line as $k=>$v) $ordered[$k]=$v; $data[]=$ordered; }

    }

    $zip->close(); return $data;

}

function colIndex($headers, $patterns) {

    foreach ($headers as $i=>$header) {

        $t = mb_strtolower(normalizar($header), 'UTF-8');

        foreach ($patterns as $p) if (preg_match($p, $t)) return $i;

    }

    return null;

}

function valorCol($row, $index) { return $index === null ? '' : normalizar($row[$index] ?? ''); }



// Importación definitiva: los datos se vuelven a validar en el servidor.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_importacion'])) {

    $payload = json_decode($_POST['filas_json'] ?? '', true);

    if (!is_array($payload) || !$payload) $errores[] = 'No hay filas válidas para importar. Volvé a cargar el archivo.';

    else {

        $importados = 0; $omitidos = 0; $fallidos = 0; $detalle = [];

        // La base del dump usa MyISAM; no depende de transacciones para garantizar rollback.

        try {

            $qEvento = $pdo->prepare("SELECT id FROM eventos WHERE id = ? LIMIT 1"); $qEvento->execute([$evento_id]);

            if (!$qEvento->fetchColumn()) throw new Exception('No existe el evento ID 1.');

            $qCorredor = $pdo->prepare("SELECT id FROM corredores WHERE dni = ? LIMIT 1");

            $qInscripto = $pdo->prepare("SELECT id FROM inscripciones WHERE evento_id = ? AND corredor_id = ? LIMIT 1");

            $qCat = $pdo->prepare("SELECT id FROM categorias WHERE evento_id = ? AND sexo <=> ? AND edad_min = ? AND edad_max = ? LIMIT 1");

            $qCatMay = $pdo->prepare("SELECT id FROM categorias WHERE evento_id = ? AND edad_min = ? AND edad_max = ? AND nombre LIKE 'MAYUATOS%' LIMIT 1");

            $qPeriodo = $pdo->prepare("SELECT id FROM periodos_inscripcion WHERE evento_id = ? AND ? BETWEEN fecha_desde AND fecha_hasta ORDER BY fecha_desde DESC LIMIT 1");

            $qPrecio = $pdo->prepare("SELECT precio_con_remera, precio_sin_remera FROM precios_inscripcion WHERE periodo_id = ? AND distancia_id = ? LIMIT 1");

            $qDorsal = $pdo->prepare("SELECT dorsal_desde, dorsal_hasta FROM distancias WHERE id = ? LIMIT 1");

            $qUsado = $pdo->prepare("SELECT dorsal FROM inscripciones WHERE evento_id = ? AND distancia_id = ? AND dorsal BETWEEN ? AND ? ORDER BY dorsal");

            $insCorredor = $pdo->prepare("INSERT INTO corredores (nombre, apellido, dni, sexo, fecha_nacimiento, email, telefono) VALUES (?, ?, ?, ?, ?, ?, ?)");

            $updCorredor = $pdo->prepare("UPDATE corredores SET nombre = IF(nombre = '' OR nombre IS NULL, ?, nombre), apellido = IF(apellido = '' OR apellido IS NULL, ?, apellido), sexo = IF(sexo IS NULL OR sexo = '', ?, sexo), fecha_nacimiento = IF(fecha_nacimiento IS NULL, ?, fecha_nacimiento), email = IF(email IS NULL OR email = '', ?, email), telefono = IF(telefono IS NULL OR telefono = '', ?, telefono) WHERE id = ?");

            $insInscripcion = $pdo->prepare("INSERT INTO inscripciones (evento_id, corredor_id, distancia_id, periodo_id, categoria_id, dorsal, remera, talle_remera, precio_base, descuento_monto, descuento_motivo, total, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, ?, 'confirmado')");

            foreach ($payload as $n=>$r) {

                $dni = preg_replace('/\D+/', '', (string)($r['dni'] ?? ''));

                if ($dni === '' || empty($r['nombre']) || empty($r['apellido']) || empty($r['distancia_id']) || empty($r['fecha_nacimiento'])) { $fallidos++; $detalle[] = 'Fila '.($n+1).': faltan DNI, nombre/apellido, fecha de nacimiento o distancia.'; continue; }

                $distancia_id = (int)$r['distancia_id'];

                if (!in_array($distancia_id, [1,2,3,4], true)) { $fallidos++; $detalle[]='Fila '.($n+1).': distancia no reconocida.'; continue; }

                $qCorredor->execute([$dni]); $corredor_id = $qCorredor->fetchColumn();

                if ($corredor_id) {

                    $qInscripto->execute([$evento_id, $corredor_id]);

                    if ($qInscripto->fetchColumn()) { $omitidos++; $detalle[]='DNI '.$dni.': ya estaba inscripto en este evento; se omitió.'; continue; }

                    $updCorredor->execute([$r['nombre'], $r['apellido'], $r['sexo'] ?: null, $r['fecha_nacimiento'], $r['email'] ?: null, $r['telefono'] ?: null, $corredor_id]);

                } else {

                    $insCorredor->execute([$r['nombre'], $r['apellido'], $dni, $r['sexo'] ?: null, $r['fecha_nacimiento'], $r['email'] ?: null, $r['telefono'] ?: null]);

                    $corredor_id = $pdo->lastInsertId();

                }

                $fechaRef = substr((string)($r['marca_temporal'] ?? ''), 0, 10);

                $fechaPeriodo = $r['fecha_periodo'] ?? date('Y-m-d');

                $qPeriodo->execute([$evento_id, $fechaPeriodo]); $periodo_id = $qPeriodo->fetchColumn();

                if (!$periodo_id) { $qPeriodo->execute([$evento_id, date('Y-m-d')]); $periodo_id = $qPeriodo->fetchColumn(); }

                if (!$periodo_id) { $fallidos++; $detalle[]='DNI '.$dni.': no se encontró período de inscripción para calcular el precio.'; continue; }

                $qPrecio->execute([$periodo_id, $distancia_id]); $precio = $qPrecio->fetch(PDO::FETCH_ASSOC);

                if (!$precio) { $fallidos++; $detalle[]='DNI '.$dni.': no hay precio configurado para el período y distancia.'; continue; }

                $remera = !empty($r['talle_remera']) ? 1 : 0;

                $talle = $remera ? strtoupper(substr($r['talle_remera'], 0, 10)) : null;

                $total = (float)($remera ? $precio['precio_con_remera'] : $precio['precio_sin_remera']);

                $qDorsal->execute([$distancia_id]); $rango = $qDorsal->fetch(PDO::FETCH_ASSOC);

                if (!$rango || $rango['dorsal_desde'] === null || $rango['dorsal_hasta'] === null) { $fallidos++; $detalle[]='DNI '.$dni.': la distancia no tiene rango de dorsales configurado.'; continue; }

                $desde=(int)$rango['dorsal_desde']; $hasta=(int)$rango['dorsal_hasta'];

                $qUsado->execute([$evento_id,$distancia_id,$desde,$hasta]); $usados=array_map('intval',$qUsado->fetchAll(PDO::FETCH_COLUMN));

                $dorsal=null; for ($d=$desde;$d<=$hasta;$d++) if (!in_array($d,$usados,true)) {$dorsal=$d;break;}

                if ($dorsal === null) { $fallidos++; $detalle[]='DNI '.$dni.': no quedan dorsales disponibles para esa distancia.'; continue; }

                $catId = null;

                if (isset($r['edad_min'], $r['edad_max']) && $r['edad_min'] !== null && $r['edad_max'] !== null) {

                    if ($distancia_id === 1) { $qCatMay->execute([$evento_id,(int)$r['edad_min'],(int)$r['edad_max']]); $catId=$qCatMay->fetchColumn(); }

                    else { $qCat->execute([$evento_id, $r['sexo'] ?: null, (int)$r['edad_min'], (int)$r['edad_max']]); $catId=$qCat->fetchColumn(); }

                }

                $insInscripcion->execute([$evento_id,$corredor_id,$distancia_id,$periodo_id,$catId ?: null,$dorsal,$remera,$talle,$total,$total]);

                $importados++; $detalle[]='DNI '.$dni.': importado con dorsal '.$dorsal.'.';

            }

            $resultado = compact('importados','omitidos','fallidos','detalle');

        } catch (Exception $e) { $errores[] = 'Error durante la importación: '.$e->getMessage(); }

    }

}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_excel']) && !isset($_POST['confirmar_importacion'])) {

    if ($_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) $errores[] = 'No se pudo subir el archivo. Revisá el tamaño y volvé a intentarlo.';

    else {

        $ext = strtolower(pathinfo($_FILES['archivo_excel']['name'], PATHINFO_EXTENSION));

        try {

            if ($ext === 'csv') $raw = parseCsv($_FILES['archivo_excel']['tmp_name']);

            elseif ($ext === 'xlsx') $raw = parseXlsx($_FILES['archivo_excel']['tmp_name']);

            else throw new Exception('Formato no admitido. Subí un archivo .xlsx o .csv (CSV UTF-8).');

            if (count($raw) < 2) throw new Exception('El archivo no contiene filas de datos.');

            $headers = array_map('normalizar', array_shift($raw));

            $idx = [

                'marca'=>colIndex($headers,['/marca temporal/','/timestamp/']),

                'dni'=>colIndex($headers,['/numero de dni/','/número de dni/','/^dni$/']),

                'persona'=>colIndex($headers,['/apellido y nombre/','/nombre completo/']),

                'nacimiento'=>colIndex($headers,['/fecha de nacimiento/']),

                'sexo'=>colIndex($headers,['/^sexo$/']),

                'email'=>colIndex($headers,['/correo electronico/','/correo electrónico/','/e-mail/','/^email$/']),

                'telefono'=>colIndex($headers,['/nro de telefono/','/nro de teléfono/','/telefono/','/teléfono/']),

                'd9'=>colIndex($headers,['/distancias competitivas.*9\s*km/']),

                'd13'=>colIndex($headers,['/distancias competitivas.*13\s*km/']),

                'd21'=>colIndex($headers,['/distancias competitivas.*21\s*km/']),

                'd3'=>colIndex($headers,['/3\s*km participativo/']),

                'talle'=>colIndex($headers,['/talle de remera/'])

            ];

            foreach (['dni','persona','nacimiento','sexo','d9','d13','d21','d3','talle'] as $ob) if ($idx[$ob] === null) throw new Exception('Falta una columna requerida: '.$ob.'. Revisá que los encabezados coincidan con los de Google Forms.');

            $distCols = ['d3'=>1,'d9'=>2,'d13'=>3,'d21'=>4];

            foreach ($raw as $num=>$row) {

                if (count(array_filter($row, function($v){return trim((string)$v)!=='';})) === 0) continue;

                $dni = preg_replace('/\D+/', '', valorCol($row,$idx['dni']));

                list($nombre,$apellido)=separarNombre(valorCol($row,$idx['persona']));

                $nac=fechaIso(valorCol($row,$idx['nacimiento']));

                $sx=sexoCodigo(valorCol($row,$idx['sexo']));

                $distSel=[]; $catTxt='';

                foreach ($distCols as $key=>$did) { $v=valorCol($row,$idx[$key]); if ($v!=='') {$distSel[]=$did; if ($catTxt==='') $catTxt=$v;} }

                $marca=valorCol($row,$idx['marca']); $marcaIso=fechaHoraIso($marca); $fechaPeriodo=$marcaIso ? substr($marcaIso,0,10) : ($nac ?: date('Y-m-d'));

                $talle=valorCol($row,$idx['talle']);

                $edadMin=$edadMax=null;

                if (preg_match('/(\d+)\s*[-–a]\s*(\d+)\s*años?/iu',$catTxt,$mm)) {$edadMin=(int)$mm[1];$edadMax=(int)$mm[2];}

                $erroresFila=[];

                if (!$dni) $erroresFila[]='sin DNI';

                if (!$nombre || !$apellido) $erroresFila[]='nombre/apellido dudoso';

                if (!$nac) $erroresFila[]='fecha de nacimiento no reconocida';

                if (count($distSel)!==1) $erroresFila[]=(count($distSel)===0?'sin distancia seleccionada':'más de una distancia seleccionada');

                $filas[] = [

                    'fila'=>$num+2,'dni'=>$dni,'nombre'=>$nombre,'apellido'=>$apellido,'fecha_nacimiento'=>$nac,

                    'sexo'=>$sx,'email'=>valorCol($row,$idx['email']),'telefono'=>normalizarTelefono(valorCol($row,$idx['telefono'])),

                    'distancia_id'=>count($distSel)===1?$distSel[0]:null,'distancia'=>count($distSel)===1?[1=>'3K - Mayuato',2=>'9K - Liebre',3=>'13K - Zorro',4=>'21K - Cóndor'][$distSel[0]]:'REVISAR',

                    'categoria_origen'=>$catTxt,'edad_min'=>$edadMin,'edad_max'=>$edadMax,'talle_remera'=>$talle,

                    'marca_temporal'=>$marcaIso,'fecha_periodo'=>$fechaPeriodo,'errores'=>$erroresFila

                ];

            }

            if (!$filas) throw new Exception('No se encontraron filas de datos para importar.');

        } catch (Exception $e) { $errores[]=$e->getMessage(); }

    }

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Importar Excel - CronoTrail</title>

<link rel="stylesheet" href="../css/estilos.css"><link rel="stylesheet" href="../css/inscripciones.css">

<style>

.importar-card{background:#fff;border-radius:12px;padding:22px;margin:18px 0;box-shadow:0 2px 10px #00000010}.importar-card p{line-height:1.5}.importar-form{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.importar-form input[type=file]{max-width:100%;padding:10px}.btn-importar{background:#e87524;color:#fff;border:0;border-radius:7px;padding:11px 18px;font-weight:700;cursor:pointer}.tabla-scroll{overflow:auto;max-width:100%}.tabla-scroll table{min-width:1050px;width:100%;border-collapse:collapse}.tabla-scroll th,.tabla-scroll td{padding:8px 10px;border-bottom:1px solid #ddd;text-align:left;font-size:13px}.estado-error{color:#a12622;font-weight:700}.estado-ok{color:#197244;font-weight:700}.alerta{padding:12px 15px;border-radius:8px;margin:12px 0;background:#fff1d9;color:#704700}.errores{padding:12px 15px;border-radius:8px;background:#ffe7e7;color:#8b1c1c;margin:12px 0}.resultado{background:#edf8ef;padding:16px;border-radius:8px;margin:16px 0}.nota{font-size:14px;color:#555}.acciones-importar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:16px}

</style></head><body>

<?php require_once "../includes/menu.php"; ?>

<div class="contenedor">

 <div class="encabezado"><div><h1>Importar inscriptos</h1><p>Importación desde Google Forms / Excel</p></div><a href="index.php" class="btn-nuevo">Volver a inscripciones</a></div>

 <?php foreach ($errores as $e): ?><div class="errores"><?=h($e)?></div><?php endforeach; ?>

 <?php if ($resultado): ?><div class="resultado"><h2>Importación finalizada</h2><p><strong>Importados:</strong> <?= (int)$resultado['importados'] ?> · <strong>Omitidos:</strong> <?= (int)$resultado['omitidos'] ?> · <strong>Con error:</strong> <?= (int)$resultado['fallidos'] ?></p><ul><?php foreach ($resultado['detalle'] as $d): ?><li><?=h($d)?></li><?php endforeach; ?></ul><p>Por seguridad, no se creó ningún registro en la tabla de pagos.</p></div><?php endif; ?>

 <div class="importar-card"><h2>1. Seleccionar archivo</h2><p>Admite Excel moderno <strong>.xlsx</strong> o <strong>.csv UTF-8</strong>. Si descargás las respuestas desde Google Sheets, podés usar Archivo → Descargar → Microsoft Excel (.xlsx) o Valores separados por comas (.csv).</p><form class="importar-form" method="post" enctype="multipart/form-data"><input type="file" name="archivo_excel" accept=".xlsx,.csv" required><button class="btn-importar" type="submit">Leer y previsualizar</button></form><p class="nota">Primero se muestra una vista previa. Los registros no se guardan hasta que confirmes la importación.</p></div>

 <?php if ($filas): $validas=0; foreach($filas as $f) if(!$f['errores'])$validas++; ?>

 <div class="importar-card"><h2>2. Revisar datos</h2><p>Filas leídas: <strong><?=count($filas)?></strong>. Sin alertas: <strong><?=$validas?></strong>. Revisá especialmente la separación de apellido y nombre en filas que no tienen coma.</p><div class="alerta">Se omitirán automáticamente las personas que ya tengan una inscripción en el evento. Los corredores existentes se reutilizan por DNI. Se asignará el primer dorsal libre del rango de cada distancia. El precio se toma de la tabla de precios según la fecha de la marca temporal (o el período vigente si no se reconoce la fecha). No se crean pagos.</div>

 <div class="tabla-scroll"><table><thead><tr><th>Fila</th><th>DNI</th><th>Apellido</th><th>Nombre</th><th>F. nacimiento</th><th>Sexo</th><th>Distancia</th><th>Categoría en Excel</th><th>Remera</th><th>Validación</th></tr></thead><tbody><?php foreach($filas as $f): ?><tr><td><?= (int)$f['fila'] ?></td><td><?=h($f['dni'])?></td><td><?=h($f['apellido'])?></td><td><?=h($f['nombre'])?></td><td><?=h($f['fecha_nacimiento'] ?: '—')?></td><td><?=h($f['sexo'] ?: '—')?></td><td><?=h($f['distancia'])?></td><td><?=h($f['categoria_origen'] ?: '—')?></td><td><?=h($f['talle_remera'] ?: 'Sin talle')?></td><td class="<?= $f['errores']?'estado-error':'estado-ok' ?>"><?=h($f['errores']?implode(', ',$f['errores']):'Lista')?></td></tr><?php endforeach; ?></tbody></table></div>

 <form method="post" class="acciones-importar"><input type="hidden" name="confirmar_importacion" value="1"><input type="hidden" name="filas_json" value="<?=h(json_encode($filas, JSON_UNESCAPED_UNICODE))?>"><button class="btn-importar" type="submit" onclick="return confirm('¿Confirmás la importación? Se crearán las inscripciones válidas y no se crearán pagos.');">Confirmar importación</button><a href="importar_excel.php">Cancelar y cargar otro archivo</a></form></div><?php endif; ?>

</div></body></html>
