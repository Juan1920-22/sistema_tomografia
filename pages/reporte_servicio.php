<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost","root","","sistema_tomografia");
if($conn->connect_error) die("Conexión fallida: ".$conn->connect_error);

// ── Mes y año ────────────────────────────────────────────────
$mes_sel  = isset($_GET['mes'])  ? max(1,min(12,intval($_GET['mes'])))   : (int)date('m');
$anio_sel = isset($_GET['anio']) ? max(2020,min(2099,intval($_GET['anio']))) : (int)date('Y');
$dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes_sel, $anio_sel);

$nombres_meses = ['','ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO',
                  'JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
$nombre_mes = $nombres_meses[$mes_sel];

// ── Paginación ───────────────────────────────────────────────
$srv_por_pagina = 4;
$pagina = isset($_GET['pagina']) ? max(1,intval($_GET['pagina'])) : 1;

// ── Servicios desde BD (en el texto original tal cual) ───────
$res = $conn->query("SELECT nombre_servicio FROM servicios_solicitantes WHERE estado='Activo' ORDER BY nombre_servicio ASC");
$todos_servicios = [];
while($r = $res->fetch_assoc()) $todos_servicios[] = trim($r['nombre_servicio']);

$total_servicios = count($todos_servicios);
$total_paginas   = max(1, ceil($total_servicios / $srv_por_pagina));
$pagina          = min($pagina, $total_paginas);
$servicios_pagina = array_slice($todos_servicios, ($pagina-1)*$srv_por_pagina, $srv_por_pagina);

// ── Tipos de atención (tal cual están en la BD) ──────────────
$tipos_atencion = ['Particular','Periférica','Ambulatorio','Emergencia','Hospitalaria'];

// ── Consultar datos del mes ──────────────────────────────────
$fecha_ini = sprintf('%04d-%02d-01', $anio_sel, $mes_sel);
$fecha_fin = sprintf('%04d-%02d-%02d', $anio_sel, $mes_sel, $dias_en_mes);

$stmt = $conn->prepare("
    SELECT DAY(fecha) as dia, servicio_solicitante, tipo_atencion
    FROM tomografias
    WHERE fecha BETWEEN ? AND ?
");
$stmt->bind_param("ss", $fecha_ini, $fecha_fin);
$stmt->execute();
$res_data = $stmt->get_result();

// Clave normalizada para comparar sin importar mayúsculas/tildes
function norm($s){ return mb_strtolower(trim($s), 'UTF-8'); }

$datos = []; // datos[norm_srv][norm_tipo][dia] = count
while($row = $res_data->fetch_assoc()){
    $sk = norm($row['servicio_solicitante']);
    $tk = norm($row['tipo_atencion']);
    $d  = (int)$row['dia'];
    $datos[$sk][$tk][$d] = ($datos[$sk][$tk][$d] ?? 0) + 1;
}
$stmt->close();

function getVal($datos,$srv,$tip,$dia){
    return $datos[norm($srv)][norm($tip)][$dia] ?? 0;
}
function getTotalTipo($datos,$srv,$tip,$dias){
    $t=0; for($d=1;$d<=$dias;$d++) $t+=getVal($datos,$srv,$tip,$d); return $t;
}
function getTotalServicio($datos,$srv,$tipos,$dias){
    $t=0; foreach($tipos as $tip) $t+=getTotalTipo($datos,$srv,$tip,$dias); return $t;
}
function getTotalDia($datos,$servicios,$tipos,$dia){
    $t=0;
    foreach($servicios as $srv) foreach($tipos as $tip) $t+=getVal($datos,$srv,$tip,$dia);
    return $t;
}

$anios = range(2020, 2030);
function buildUrl($p,$mes,$anio){ return "?pagina=$p&mes=$mes&anio=$anio"; }

// ── Pre-calcular totales generales ───────────────────────────
$total_general = 0;
foreach($todos_servicios as $srv)
    $total_general += getTotalServicio($datos,$srv,$tipos_atencion,$dias_en_mes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporte por Servicio</title>
<link rel="stylesheet" href="../css/reporte_servicio.css?v=3">
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
    <div class="reporte-titulo">INFORME ESTADÍSTICO POR SERVICIO</div>
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

<!-- TABLA PANTALLA (solo página actual) -->
<div class="tabla-wrapper no-print">
    <div class="tabla-scroll">
        <?php renderTabla($servicios_pagina, $tipos_atencion, $datos, $dias_en_mes, $todos_servicios); ?>
    </div>
</div>

<!-- TABLA COMPLETA PARA IMPRIMIR -->
<div class="tabla-wrapper only-print">
    <div class="tabla-scroll">
        <?php renderTabla($todos_servicios, $tipos_atencion, $datos, $dias_en_mes, $todos_servicios); ?>
    </div>
</div>

<?php
function renderTabla($servicios, $tipos, $datos, $dias, $todos_servicios){
    global $total_general;
    $n_tipos = count($tipos); // 5
    ?>
    <table class="tabla-reporte">
        <thead>
            <tr>
                <th rowspan="2" class="th-serv">SERV.</th>
                <th rowspan="2" class="th-tipo">TIPO<br>ATENCIÓN</th>
                <th colspan="<?=$dias?>" class="th-dias-label">DÍAS DEL MES</th>
                <th rowspan="2" class="th-total">TOTAL</th>
                <th rowspan="2" class="th-total-serv">TOTAL<br>SERV.</th>
            </tr>
            <tr>
                <?php for($d=1;$d<=$dias;$d++): ?>
                <th class="th-dia"><?=$d?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($servicios as $idx => $srv):
            $total_srv = getTotalServicio($datos, $srv, $tipos, $dias);
            $bg_class  = $idx % 2 === 0 ? 'bg-par' : 'bg-impar';
        ?>
            <?php foreach($tipos as $ti => $tip):
                $total_tipo = getTotalTipo($datos, $srv, $tip, $dias);
                $es_primera = ($ti === 0);
                $es_media   = ($ti === 2); // fila del medio (índice 2 de 5) para el rowspan
            ?>
            <tr>
                <?php if($es_primera): ?>
                <td rowspan="<?=$n_tipos?>" class="td-servicio <?=$bg_class?>"><?=htmlspecialchars(strtoupper($srv))?></td>
                <?php endif; ?>

                <td class="td-tipo"><?=htmlspecialchars(strtoupper($tip))?></td>

                <?php for($d=1;$d<=$dias;$d++):
                    $v = getVal($datos,$srv,$tip,$d);
                ?>
                <td class="td-num <?=$v>0?'td-val':''?>"><?=$v>0?$v:''?></td>
                <?php endfor; ?>

                <td class="td-total-tipo <?=$total_tipo>0?'td-total-am':''?>"><?=$total_tipo>0?$total_tipo:''?></td>

                <?php if($es_primera): // rowspan=5 en la primera fila del servicio ?>
                <td rowspan="<?=$n_tipos?>" class="td-total-serv <?=$total_srv>0?'td-total-vd':''?>"><?=$total_srv>0?$total_srv:''?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <!-- FILA TOTAL DIARIO -->
        <tr class="tr-total-diario">
            <td colspan="2" class="td-label-total">TOTAL DIARIO</td>
            <?php
            $gran_total_dias = 0;
            for($d=1;$d<=$dias;$d++):
                $td = getTotalDia($datos, $todos_servicios, $tipos, $d);
                $gran_total_dias += $td;
            ?>
            <td class="td-dia-total <?=$td>0?'td-dia-val':''?>"><?=$td>0?$td:''?></td>
            <?php endfor; ?>
            <!-- TOTAL (suma de todos los totales de tipo) -->
            <td class="td-gran-total-am">TOTAL<br><strong><?=$gran_total_dias?></strong></td>
            <!-- TOTAL SERV (suma total general) -->
            <td class="td-gran-total-vd">TOTAL<br><strong><?=$gran_total_dias?></strong></td>
        </tr>
        </tbody>
    </table>
    <?php
}
?>
</body>
</html>