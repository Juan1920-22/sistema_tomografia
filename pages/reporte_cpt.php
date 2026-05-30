<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost","root","","sistema_tomografia");
if($conn->connect_error) die("Conexión fallida: ".$conn->connect_error);

// Crear tabla cpt_codigos si no existe
$conn->query("
CREATE TABLE IF NOT EXISTS cpt_codigos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_examen INT NOT NULL UNIQUE,
    codigo_cpt VARCHAR(20) NOT NULL,
    co_codups VARCHAR(20) DEFAULT '00003414',
    servicio_especialidad VARCHAR(20) DEFAULT '080900',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ── Mes y año ────────────────────────────────────────────────
$mes_sel  = isset($_GET['mes'])  ? max(1,min(12,intval($_GET['mes'])))       : (int)date('m');
$anio_sel = isset($_GET['anio']) ? max(2020,min(2099,intval($_GET['anio']))) : (int)date('Y');
$dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes_sel, $anio_sel);

$nombres_meses = ['','ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO',
                  'JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
$nombre_mes = $nombres_meses[$mes_sel];

// ── Consultar reporte ────────────────────────────────────────
$fecha_ini = sprintf('%04d-%02d-01', $anio_sel, $mes_sel);
$fecha_fin = sprintf('%04d-%02d-%02d', $anio_sel, $mes_sel, $dias_en_mes);
$periodo   = sprintf('%04d%02d', $anio_sel, $mes_sel);

// Contar procedimientos por código CPT en el mes
$stmt = $conn->prepare("
    SELECT
        cc.codigo_cpt,
        cc.co_codups,
        cc.servicio_especialidad,
        COUNT(t.id_tomografia) as total
    FROM cpt_codigos cc
    JOIN examenes_solicitados e ON e.id_examen = cc.id_examen
    JOIN tomografias t ON (
        t.examen_solicitado LIKE CONCAT('%', e.nombre_examen, '%')
        OR t.id_examen = cc.id_examen
    )
    WHERE t.fecha BETWEEN ? AND ?
    GROUP BY cc.codigo_cpt, cc.co_codups, cc.servicio_especialidad
    ORDER BY cc.codigo_cpt ASC
");
$stmt->bind_param("ss", $fecha_ini, $fecha_fin);
$stmt->execute();
$res_reporte = $stmt->get_result();
$filas_reporte = [];
$total_general = 0;
while($r = $res_reporte->fetch_assoc()){
    $filas_reporte[] = $r;
    $total_general += $r['total'];
}
$stmt->close();

$anios = range(2020, 2030);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporte CPT-Code</title>
<link rel="stylesheet" href="../css/reporte_cpt.css?v=1">
</head>
<body>

<!-- CONTROLES -->
<div class="controles no-print">
    <a href="reportes.php" class="btn-volver">← Volver a Reportes</a>

    <a href="configurar_cpt.php" class="btn-configurar">⚙ Configurar CPT-Code</a>
</div>

<!-- CABECERA -->
<div class="cabecera-reporte">
    <div class="hospital-nombre">HOSPITAL SAN JOSÉ DE CHINCHA</div>
    <div class="reporte-titulo">REPORTE INFORMES TOMOGRÁFICOS SEGÚN TABLA DE CODIFICACIÓN (CPT-CODE)</div>
    <div class="reporte-periodo">MES DE <?=$nombre_mes?> <?=$anio_sel?></div>
</div>

<!-- FILTROS -->
<div class="filtros-box no-print">
    <form class="form-filtro" method="GET">
        <div class="filtro-grupo">
            <label>Mes</label>
            <select name="mes">
                <?php foreach($nombres_meses as $n=>$nm): if(!$n) continue; ?>
                <option value="<?=$n?>" <?=$n===$mes_sel?'selected':''?>><?=$nm?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-grupo">
            <label>Año</label>
            <select name="anio">
                <?php foreach($anios as $a): ?>
                <option value="<?=$a?>" <?=$a===$anio_sel?'selected':''?>><?=$a?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit"  class="btn-generar">Generar reporte</button>
        <button type="button"  class="btn-imprimir" onclick="window.print()">Imprimir</button>
    </form>
</div>

<!-- TABLA REPORTE -->
<div class="tabla-wrapper">
    <table class="tabla-cpt">
        <thead>
            <tr>
                <th>PERIODO<br>DE REPORTE</th>
                <th>CÓDIGO DE LA<br>IPRESS</th>
                <th>CO-CODUPS</th>
                <th>CÓDIGO DEL<br>PROCEDIMIENTO</th>
                <th>TOTAL<br>PROCEDIMIENTOS</th>
                <th>SERVICIO /<br>ESPECIALIDAD</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($filas_reporte)): ?>
            <tr><td colspan="6" class="sin-datos">No hay procedimientos registrados para este período</td></tr>
        <?php else: ?>
            <?php foreach($filas_reporte as $f): ?>
            <tr>
                <td><?=$periodo?></td>
                <td>00003414</td>
                <td><?=htmlspecialchars($f['co_codups'])?></td>
                <td><?=htmlspecialchars($f['codigo_cpt'])?></td>
                <td class="td-num"><?=$f['total']?></td>
                <td><?=htmlspecialchars($f['servicio_especialidad'])?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="tr-total">
                <td colspan="4" class="td-total-label">TOTAL GENERAL</td>
                <td class="td-num"><?=$total_general?></td>
                <td></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- TABLA REFERENCIA CPT -->
<?php
$ref = $conn->query("
    SELECT cc.codigo_cpt, e.nombre_examen
    FROM cpt_codigos cc
    JOIN examenes_solicitados e ON e.id_examen = cc.id_examen
    ORDER BY cc.codigo_cpt ASC
");
$ref_rows = [];
while($r = $ref->fetch_assoc()) $ref_rows[] = $r;
?>
<?php if(!empty($ref_rows)): ?>
<div class="ref-wrapper">
    <?php
    $mitad = ceil(count($ref_rows)/2);
    $col1  = array_slice($ref_rows, 0, $mitad);
    $col2  = array_slice($ref_rows, $mitad);
    ?>
    <div class="ref-col">
        <?php foreach($col1 as $r): ?>
        <div class="ref-item">
            <span class="ref-cod"><?=htmlspecialchars($r['codigo_cpt'])?></span>
            <span class="ref-nom"><?=htmlspecialchars($r['nombre_examen'])?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="ref-col">
        <?php foreach($col2 as $r): ?>
        <div class="ref-item">
            <span class="ref-cod"><?=htmlspecialchars($r['codigo_cpt'])?></span>
            <span class="ref-nom"><?=htmlspecialchars($r['nombre_examen'])?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</body>
</html>