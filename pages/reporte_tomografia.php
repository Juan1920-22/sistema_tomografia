<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost","root","","sistema_tomografia");
if($conn->connect_error) die("Conexión fallida: ".$conn->connect_error);

// ── Mes y año ────────────────────────────────────────────────
$mes_sel  = isset($_GET['mes'])  ? max(1,min(12,intval($_GET['mes'])))       : (int)date('m');
$anio_sel = isset($_GET['anio']) ? max(2020,min(2099,intval($_GET['anio']))) : (int)date('Y');
$dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes_sel, $anio_sel);

$nombres_meses = ['','ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO',
                  'JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
$nombre_mes = $nombres_meses[$mes_sel];

// ── Paginación ───────────────────────────────────────────────
$ex_por_pagina = 3;   // 3 exámenes por página en pantalla
$pagina = isset($_GET['pagina']) ? max(1,intval($_GET['pagina'])) : 1;

// ── Exámenes desde BD ────────────────────────────────────────
$res = $conn->query("SELECT nombre_examen FROM examenes_solicitados WHERE estado='Activo' ORDER BY nombre_examen ASC");
$todos_examenes = [];
while($r = $res->fetch_assoc()) $todos_examenes[] = trim($r['nombre_examen']);

$total_examenes = count($todos_examenes);
$total_paginas  = max(1, ceil($total_examenes / $ex_por_pagina));
$pagina         = min($pagina, $total_paginas);
$examenes_pagina = array_slice($todos_examenes, ($pagina-1)*$ex_por_pagina, $ex_por_pagina);

// ── Condiciones desde BD ─────────────────────────────────────
$res2 = $conn->query("SELECT nombre_condicion FROM condiciones_pago WHERE estado='Activo' ORDER BY nombre_condicion ASC");
$todas_condiciones = [];
while($r = $res2->fetch_assoc()) $todas_condiciones[] = trim($r['nombre_condicion']);

// ── Consultar datos del mes ──────────────────────────────────
$fecha_ini = sprintf('%04d-%02d-01', $anio_sel, $mes_sel);
$fecha_fin = sprintf('%04d-%02d-%02d', $anio_sel, $mes_sel, $dias_en_mes);

$stmt = $conn->prepare("
    SELECT DAY(fecha) as dia, examen_solicitado, condicion
    FROM tomografias
    WHERE fecha BETWEEN ? AND ?
");
$stmt->bind_param("ss", $fecha_ini, $fecha_fin);
$stmt->execute();
$res_data = $stmt->get_result();

function norm($s){ return mb_strtolower(trim($s), 'UTF-8'); }

// datos[norm_examen][norm_condicion][dia] = count
// Un registro puede tener múltiples exámenes separados por coma
$datos = [];
while($row = $res_data->fetch_assoc()){
    $examenes_raw = explode(',', $row['examen_solicitado']);
    $ck = norm($row['condicion']);
    $d  = (int)$row['dia'];
    foreach($examenes_raw as $ex){
        $ek = norm($ex);
        $datos[$ek][$ck][$d] = ($datos[$ek][$ck][$d] ?? 0) + 1;
    }
}
$stmt->close();

function getVal($datos,$ex,$cond,$dia){
    return $datos[norm($ex)][norm($cond)][$dia] ?? 0;
}
function getTotalCond($datos,$ex,$cond,$dias){
    $t=0; for($d=1;$d<=$dias;$d++) $t+=getVal($datos,$ex,$cond,$d); return $t;
}
function getTotalExamen($datos,$ex,$condiciones,$dias){
    $t=0; foreach($condiciones as $c) $t+=getTotalCond($datos,$ex,$c,$dias); return $t;
}
function getTotalDia($datos,$examenes,$condiciones,$dia){
    $t=0;
    foreach($examenes as $ex) foreach($condiciones as $c) $t+=getVal($datos,$ex,$c,$dia);
    return $t;
}

$anios = range(2020, 2030);
function buildUrl($p,$mes,$anio){ return "?pagina=$p&mes=$mes&anio=$anio"; }

$total_general = 0;
foreach($todos_examenes as $ex)
    $total_general += getTotalExamen($datos,$ex,$todas_condiciones,$dias_en_mes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporte por Tomografía</title>
<link rel="stylesheet" href="../css/reporte_tomografia.css?v=1">
</head>
<body>

<!-- CONTROLES -->
<div class="controles no-print">
    <a href="reportes.php" class="btn-volver">← Volver a Reportes</a>
    <form class="form-filtro" method="GET">
        <div class="filtro-grupo">
            <label>MES</label>
            <select name="mes">
                <?php foreach($nombres_meses as $n=>$nm): if(!$n) continue; ?>
                <option value="<?=$n?>" <?=$n===$mes_sel?'selected':''?>><?=$nm?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-grupo">
            <label>AÑO</label>
            <select name="anio">
                <?php foreach($anios as $a): ?>
                <option value="<?=$a?>" <?=$a===$anio_sel?'selected':''?>><?=$a?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="pagina" value="1">
        <button type="submit"  class="btn-generar">Generar reporte</button>
        <button type="button"  class="btn-imprimir" onclick="window.print()">Imprimir</button>
        <button type="button"  class="btn-pdf"      onclick="window.print()">Descargar PDF</button>
    </form>
</div>

<!-- CABECERA -->
<div class="cabecera-reporte">
    <div class="hospital-nombre">HOSPITAL SAN JOSÉ DE CHINCHA</div>
    <div class="reporte-titulo">INFORME ESTADÍSTICO POR TOMOGRAFÍA</div>
    <div class="reporte-periodo">MES DE <?=$nombre_mes?> <?=$anio_sel?></div>
</div>

<!-- PAGINACIÓN PANTALLA -->
<?php if($total_paginas > 1): ?>
<div class="paginacion no-print">
    <span class="pag-info">Página <strong><?=$pagina?></strong> de <strong><?=$total_paginas?></strong></span>
    <?php if($pagina>1): ?>
        <a href="<?=buildUrl($pagina-1,$mes_sel,$anio_sel)?>" class="btn-pag">« Anterior</a>
    <?php endif; ?>
    <?php if($pagina<$total_paginas): ?>
        <a href="<?=buildUrl($pagina+1,$mes_sel,$anio_sel)?>" class="btn-pag">Siguiente »</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- TABLA PANTALLA -->
<div class="tabla-wrapper no-print">
    <div class="tabla-scroll">
        <?php renderTabla($examenes_pagina, $todas_condiciones, $datos, $dias_en_mes, $todos_examenes); ?>
    </div>
</div>

<!-- TABLA COMPLETA PARA IMPRIMIR -->
<div class="tabla-wrapper only-print">
    <div class="tabla-scroll">
        <?php renderTabla($todos_examenes, $todas_condiciones, $datos, $dias_en_mes, $todos_examenes); ?>
    </div>
</div>

<?php
function renderTabla($examenes, $condiciones, $datos, $dias, $todos_examenes){
    global $total_general;
    $n_cond = count($condiciones);
    ?>
    <table class="tabla-reporte">
        <thead>
            <tr>
                <th rowspan="2" class="th-exam">TOMOGRAFÍA</th>
                <th rowspan="2" class="th-cond">CONDICIÓN</th>
                <th colspan="<?=$dias?>" class="th-dias-label">DÍAS DEL MES</th>
                <th rowspan="2" class="th-total">TOTAL</th>
                <th rowspan="2" class="th-total-serv">TOTAL<br>ECOG.</th>
            </tr>
            <tr>
                <?php for($d=1;$d<=$dias;$d++): ?>
                <th class="th-dia"><?=$d?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($examenes as $idx => $ex):
            $total_ex = getTotalExamen($datos, $ex, $condiciones, $dias);
            $bg_class = $idx % 2 === 0 ? 'bg-par' : 'bg-impar';
        ?>
            <?php foreach($condiciones as $ci => $cond):
                $total_cond = getTotalCond($datos, $ex, $cond, $dias);
                $es_primera = ($ci === 0);
            ?>
            <tr>
                <?php if($es_primera): ?>
                <td rowspan="<?=$n_cond?>" class="td-examen <?=$bg_class?>"><?=htmlspecialchars(strtoupper($ex))?></td>
                <?php endif; ?>

                <td class="td-cond"><?=htmlspecialchars($cond)?></td>

                <?php for($d=1;$d<=$dias;$d++):
                    $v = getVal($datos,$ex,$cond,$d);
                ?>
                <td class="td-num <?=$v>0?'td-val':''?>"><?=$v>0?$v:''?></td>
                <?php endfor; ?>

                <td class="td-total-cond <?=$total_cond>0?'td-total-am':''?>"><?=$total_cond>0?$total_cond:''?></td>

                <?php if($es_primera): ?>
                <td rowspan="<?=$n_cond?>" class="td-total-ex <?=$total_ex>0?'td-total-vd':''?>"><?=$total_ex>0?$total_ex:''?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <!-- TOTAL DIARIO -->
        <tr class="tr-total-diario">
            <td colspan="2" class="td-label-total">TOTAL DIARIO</td>
            <?php
            $gran_total_dias = 0;
            for($d=1;$d<=$dias;$d++):
                $td = getTotalDia($datos, $todos_examenes, $condiciones, $d);
                $gran_total_dias += $td;
            ?>
            <td class="td-dia-total <?=$td>0?'td-dia-val':''?>"><?=$td>0?$td:''?></td>
            <?php endfor; ?>
            <td class="td-gran-total-am">TOTAL<br><strong><?=$gran_total_dias?></strong></td>
            <td class="td-gran-total-vd">TOTAL<br><strong><?=$gran_total_dias?></strong></td>
        </tr>
        </tbody>
    </table>
    <?php
}
?>
</body>
</html>