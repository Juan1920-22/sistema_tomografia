<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost","root","","sistema_tomografia");
if($conn->connect_error) die("Conexión fallida: " . $conn->connect_error);

// ─── ACCIÓN: ELIMINAR ────────────────────────────────────────────────────────
if(isset($_POST['action']) && $_POST['action'] === 'eliminar'){
    $id = intval($_POST['id_tomografia']);
    // Eliminar también los exámenes relacionados
    $stmt = $conn->prepare("DELETE FROM tomografia_examenes WHERE id_tomografia = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM tomografias WHERE id_tomografia = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// ─── ACCIÓN: EDITAR (guardar cambios) ────────────────────────────────────────
if(isset($_POST['action']) && $_POST['action'] === 'editar'){
    $id            = intval($_POST['id_tomografia']);
    $hc            = $conn->real_escape_string(trim($_POST['historia_clinica'] ?? ''));
    $dni           = $conn->real_escape_string(trim($_POST['dni'] ?? ''));
    $fecha         = $conn->real_escape_string(trim($_POST['fecha'] ?? ''));
    $apellidos     = $conn->real_escape_string(trim($_POST['apellidos'] ?? ''));
    $nombres       = $conn->real_escape_string(trim($_POST['nombres'] ?? ''));
    $sexo          = $conn->real_escape_string(trim($_POST['sexo'] ?? ''));
    $diagnostico   = $conn->real_escape_string(trim($_POST['diagnostico'] ?? ''));
    $hora_examen   = $conn->real_escape_string(trim($_POST['hora_examen'] ?? ''));
    $monto         = floatval($_POST['monto'] ?? 0);
    $numero_boleta = $conn->real_escape_string(trim($_POST['numero_boleta'] ?? ''));
    $convenio      = $conn->real_escape_string(trim($_POST['convenio'] ?? ''));

    // IDs de selects
    $id_condicion     = intval($_POST['id_condicion'] ?? 0);
    $id_servicio      = intval($_POST['id_servicio'] ?? 0);
    $id_tipo_atencion = intval($_POST['id_tipo_atencion'] ?? 0);
    $id_medico        = intval($_POST['id_medico'] ?? 0);

    // Resolver nombres desde IDs
    function getNombre($conn, $tabla, $campo_id, $campo_nombre, $id){
        $s = $conn->prepare("SELECT $campo_nombre FROM $tabla WHERE $campo_id = ?");
        $s->bind_param("i", $id);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        return $r ? $r[$campo_nombre] : '';
    }

    $condicion            = getNombre($conn,'condiciones_pago','id_condicion','nombre_condicion',$id_condicion);
    $servicio_solicitante = getNombre($conn,'servicios_solicitantes','id_servicio','nombre_servicio',$id_servicio);
    $tipo_atencion        = getNombre($conn,'tipos_atencion','id_tipo_atencion','nombre_tipo_atencion',$id_tipo_atencion);
    $medico_turno         = getNombre($conn,'medicos_turno','id_medico','nombre_medico',$id_medico);

    // Exámenes (array de IDs)
    $ids_examenes = $_POST['id_examen'] ?? [];
    $nombres_examenes = [];
    $codigos_cpt      = [];
    $ids_limpios      = [];

    foreach($ids_examenes as $idEx){
        $idEx = intval($idEx);
        if($idEx > 0 && !in_array($idEx, $ids_limpios)){
            $ids_limpios[] = $idEx;
            $s = $conn->prepare("SELECT nombre_examen, codigo_cpt FROM examenes_solicitados WHERE id_examen = ?");
            $s->bind_param("i", $idEx);
            $s->execute();
            $row = $s->get_result()->fetch_assoc();
            $s->close();
            if($row){
                $nombres_examenes[] = $row['nombre_examen'];
                if(!empty($row['codigo_cpt'])) $codigos_cpt[] = $row['codigo_cpt'];
            }
        }
    }

    $examen_solicitado = implode(', ', $nombres_examenes);
    $codigo_cpt        = implode(', ', $codigos_cpt);
    $id_examen_principal = $ids_limpios[0] ?? 0;

    // Actualizar tomografia
    $stmt = $conn->prepare("UPDATE tomografias SET
        historia_clinica=?, dni=?, fecha=?, apellidos=?, nombres=?, sexo=?,
        condicion=?, servicio_solicitante=?, medico_turno=?, examen_solicitado=?,
        tipo_atencion=?, diagnostico=?, monto=?, numero_boleta=?, convenio=?,
        hora_examen=?, id_condicion=?, id_servicio=?, id_tipo_atencion=?,
        id_medico=?, id_examen=?, codigo_cpt=?
        WHERE id_tomografia=?");
    $stmt->bind_param(
        "ssssssssssssdsssiiiisi",
        $hc, $dni, $fecha, $apellidos, $nombres, $sexo,
        $condicion, $servicio_solicitante, $medico_turno, $examen_solicitado,
        $tipo_atencion, $diagnostico, $monto, $numero_boleta, $convenio,
        $hora_examen, $id_condicion, $id_servicio, $id_tipo_atencion,
        $id_medico, $id_examen_principal, $codigo_cpt,
        $id
    );
    $stmt->execute();
    $stmt->close();

    // Actualizar tomografia_examenes
    $del = $conn->prepare("DELETE FROM tomografia_examenes WHERE id_tomografia=?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();

    foreach($ids_limpios as $idEx){
        $ins = $conn->prepare("INSERT INTO tomografia_examenes (id_tomografia, id_examen) VALUES (?,?)");
        $ins->bind_param("ii", $id, $idEx);
        $ins->execute();
        $ins->close();
    }

    echo json_encode(['success' => true]);
    exit;
}

// ─── ACCIÓN: OBTENER DATOS PARA EL MODAL ─────────────────────────────────────
if(isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])){
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM tomografias WHERE id_tomografia = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Cargar exámenes desde tomografia_examenes
    $stmt2 = $conn->prepare("
        SELECT te.id_examen, e.nombre_examen, e.codigo_cpt
        FROM tomografia_examenes te
        JOIN examenes_solicitados e ON e.id_examen = te.id_examen
        WHERE te.id_tomografia = ?
        LIMIT 3
    ");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $examenes_registrados = [];
    while($ex = $res2->fetch_assoc()){
        $examenes_registrados[] = $ex;
    }
    $stmt2->close();

    $data['examenes'] = $examenes_registrados;
    echo json_encode($data);
    exit;
}

// ─── FILTROS ─────────────────────────────────────────────────────────────────
$fecha_inicio  = $_POST['fecha_inicio']  ?? '';
$fecha_fin     = $_POST['fecha_fin']     ?? '';
$busqueda      = $_POST['busqueda']      ?? '';
$filtro_medico        = $_POST['medico']        ?? '';
$filtro_servicio      = $_POST['servicio']      ?? '';
$filtro_condicion     = $_POST['condicion']     ?? '';
$filtro_tipo_atencion = $_POST['tipo_atencion'] ?? '';
$filtro_examen        = $_POST['examen']        ?? '';

// ─── PAGINACIÓN ──────────────────────────────────────────────────────────────
$por_pagina    = 50;
$pagina_actual = max(1, intval($_POST['pagina'] ?? 1));
$offset        = ($pagina_actual - 1) * $por_pagina;

// ─── CONSULTA SEGURA ─────────────────────────────────────────────────────────
$where  = "WHERE 1=1";
$params = [];
$types  = "";

if(!empty($fecha_inicio))  { $where .= " AND fecha >= ?";  $params[] = $fecha_inicio; $types .= "s"; }
if(!empty($fecha_fin))     { $where .= " AND fecha <= ?";  $params[] = $fecha_fin;    $types .= "s"; }
if(!empty($busqueda)){
    $like = "%$busqueda%";
    $where .= " AND (historia_clinica LIKE ? OR apellidos LIKE ? OR nombres LIKE ? OR dni LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "ssss";
}
if(!empty($filtro_medico)        && $filtro_medico        !== 'Todos los médicos')   { $where .= " AND medico_turno = ?";         $params[] = $filtro_medico;        $types .= "s"; }
if(!empty($filtro_servicio)      && $filtro_servicio      !== 'Todos los servicios') { $where .= " AND servicio_solicitante = ?"; $params[] = $filtro_servicio;      $types .= "s"; }
if(!empty($filtro_condicion)     && $filtro_condicion     !== 'Todas')               { $where .= " AND condicion = ?";            $params[] = $filtro_condicion;     $types .= "s"; }
if(!empty($filtro_tipo_atencion) && $filtro_tipo_atencion !== 'Todos')               { $where .= " AND tipo_atencion = ?";        $params[] = $filtro_tipo_atencion; $types .= "s"; }
if(!empty($filtro_examen)        && $filtro_examen        !== 'Todos los exámenes')  { $like2 = "%$filtro_examen%"; $where .= " AND examen_solicitado LIKE ?"; $params[] = $like2; $types .= "s"; }

// Totales
$sql_count = "SELECT COUNT(*) as total, COALESCE(SUM(monto),0) as monto_total FROM tomografias $where";
$sc = $conn->prepare($sql_count);
if(!empty($params)) $sc->bind_param($types, ...$params);
$sc->execute();
$res_count     = $sc->get_result()->fetch_assoc();
$total_registros = $res_count['total'];
$monto_total     = $res_count['monto_total'];
$total_paginas   = max(1, ceil($total_registros / $por_pagina));
$sc->close();

// Datos paginados
$sql_data   = "SELECT * FROM tomografias $where ORDER BY fecha DESC, id_tomografia DESC LIMIT ? OFFSET ?";
$pd         = array_merge($params, [$por_pagina, $offset]);
$td         = $types . "ii";
$sd         = $conn->prepare($sql_data);
$sd->bind_param($td, ...$pd);
$sd->execute();
$result = $sd->get_result();

// ─── DATOS PARA SELECTS ───────────────────────────────────────────────────────
$q_medicos     = $conn->query("SELECT id_medico, nombre_medico FROM medicos_turno WHERE estado='Activo' ORDER BY nombre_medico");
$q_servicios   = $conn->query("SELECT id_servicio, nombre_servicio FROM servicios_solicitantes WHERE estado='Activo' ORDER BY nombre_servicio");
$q_condiciones = $conn->query("SELECT id_condicion, nombre_condicion FROM condiciones_pago WHERE estado='Activo' ORDER BY nombre_condicion");
$q_tipos       = $conn->query("SELECT id_tipo_atencion, nombre_tipo_atencion FROM tipos_atencion WHERE estado='Activo' ORDER BY nombre_tipo_atencion");
$q_examenes    = $conn->query("SELECT id_examen, nombre_examen, codigo_cpt FROM examenes_solicitados WHERE estado='Activo' ORDER BY nombre_examen");

// Guardar en arrays para reutilizar
$arr_medicos = $arr_servicios = $arr_condiciones = $arr_tipos = $arr_examenes = [];
while($r = $q_medicos->fetch_assoc())     $arr_medicos[]     = $r;
while($r = $q_servicios->fetch_assoc())   $arr_servicios[]   = $r;
while($r = $q_condiciones->fetch_assoc()) $arr_condiciones[] = $r;
while($r = $q_tipos->fetch_assoc())       $arr_tipos[]       = $r;
while($r = $q_examenes->fetch_assoc())    $arr_examenes[]    = $r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Historial de Tomografías</title>
<link rel="stylesheet" href="../css/historial_tomografia.css?v=3">
</head>
<body>

<!-- TOPBAR igual que registrar.php -->
<header class="topbar">
    <div class="topbar-title">HOSPITAL SAN JOSÉ DE CHINCHA</div>
    <nav class="topbar-menu">
        <a href="dashboard.php">Inicio</a>
        <a href="registrar.php">Registrar</a>
        <a href="historial_tomografia.php" class="active">Historial</a>
        <a href="reportes.php">Reportes</a>
        <a href="mantenimiento.php">Mantenimiento</a>
        <a href="logout.php" class="salir">Salir</a>
    </nav>
</header>

<main class="main-content">

    <h1>Historial de Tomografías</h1>

    <!-- ── FILTROS ── -->
    <form class="filtros-form" method="POST" id="form-filtros">
        <input type="hidden" name="pagina" value="1" id="input-pagina">
        <div class="filtros-grid">
            <div class="filtro-grupo">
                <label>Fecha inicio</label>
                <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
            </div>
            <div class="filtro-grupo">
                <label>Fecha final</label>
                <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
            </div>
            <div class="filtro-grupo">
                <label>Buscar paciente o H.C</label>
                <input type="text" name="busqueda" placeholder="Nombre, apellido, H.C, DNI..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
            <div class="filtro-grupo">
                <label>Médico turno</label>
                <select name="medico">
                    <option value="">Todos los médicos</option>
                    <?php foreach($arr_medicos as $m): ?>
                    <option <?= $filtro_medico === $m['nombre_medico'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nombre_medico']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-grupo">
                <label>Servicio solicitante</label>
                <select name="servicio">
                    <option value="">Todos los servicios</option>
                    <?php foreach($arr_servicios as $s): ?>
                    <option <?= $filtro_servicio === $s['nombre_servicio'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nombre_servicio']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-grupo">
                <label>Condición</label>
                <select name="condicion">
                    <option value="">Todas</option>
                    <?php foreach($arr_condiciones as $c): ?>
                    <option <?= $filtro_condicion === $c['nombre_condicion'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre_condicion']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-grupo">
                <label>Tipo de atención</label>
                <select name="tipo_atencion">
                    <option value="">Todos</option>
                    <?php foreach($arr_tipos as $t): ?>
                    <option <?= $filtro_tipo_atencion === $t['nombre_tipo_atencion'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nombre_tipo_atencion']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-grupo">
                <label>Examen solicitado</label>
                <select name="examen">
                    <option value="">Todos los exámenes</option>
                    <?php foreach($arr_examenes as $e): ?>
                    <option <?= $filtro_examen === $e['nombre_examen'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nombre_examen']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="botones-filtro">
            <button type="button" class="btn-rojo"   onclick="descargarPDF()">Descargar PDF</button>
            <button type="button" class="btn-morado" onclick="window.print()">Imprimir</button>
            <button type="submit" class="btn-azul">Buscar</button>
            <button type="button" class="btn-verde"  onclick="limpiarFiltros()">Limpiar</button>
        </div>
    </form>

    <!-- ── RESUMEN ── -->
    <div class="resumen-bar">
        <span>Registros encontrados: <strong><?= number_format($total_registros) ?></strong></span>
        <span class="sep">|</span>
        <span>Monto total: <strong>S/ <?= number_format($monto_total, 2) ?></strong></span>
    </div>

    <!-- ── PAGINACIÓN SUPERIOR ── -->
    <?php if($total_paginas > 1): ?>
    <div class="paginacion">
        <span class="pag-info">Página <strong><?= $pagina_actual ?></strong> de <strong><?= $total_paginas ?></strong></span>
        <?php if($pagina_actual > 1): ?>
            <button class="btn-pag" onclick="irPagina(<?= $pagina_actual - 1 ?>)">← Anterior</button>
        <?php endif; ?>
        <?php if($pagina_actual < $total_paginas): ?>
            <button class="btn-pag" onclick="irPagina(<?= $pagina_actual + 1 ?>)">Siguiente →</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── TABLA ── -->
    <div class="tabla-container">
        <table>
            <thead>
                <tr>
                    <th>H.C</th>
                    <th>DNI</th>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Sexo</th>
                    <th>Condición</th>
                    <th>Servicio</th>
                    <th>Médico</th>
                    <th>Tipo Atención</th>
                    <th>Exámenes</th>
                    <th>Diagnóstico</th>
                    <th>Monto</th>
                    <th>Boleta</th>
                    <th>Convenio</th>
                    <th>Hora</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php if($result->num_rows === 0): ?>
                <tr><td colspan="16" class="sin-datos">No se encontraron registros</td></tr>
            <?php else: ?>
            <?php while($row = $result->fetch_assoc()):
                // Obtener exámenes de tomografia_examenes
                $id_tom = $row['id_tomografia'];
                $stmtEx = $conn->prepare("
                    SELECT e.nombre_examen
                    FROM tomografia_examenes te
                    JOIN examenes_solicitados e ON e.id_examen = te.id_examen
                    WHERE te.id_tomografia = ?
                    LIMIT 3
                ");
                $stmtEx->bind_param("i", $id_tom);
                $stmtEx->execute();
                $resEx = $stmtEx->get_result();
                $lista_examenes = [];
                while($ex = $resEx->fetch_assoc()) $lista_examenes[] = $ex['nombre_examen'];
                $stmtEx->close();

                // Si no hay registros en tomografia_examenes, usar columna legacy
                if(empty($lista_examenes) && !empty($row['examen_solicitado'])){
                    $lista_examenes = explode(',', $row['examen_solicitado']);
                    $lista_examenes = array_map('trim', $lista_examenes);
                }
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['historia_clinica'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['dni'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['fecha'] ?? '') ?></td>
                    <td><?= htmlspecialchars(trim(($row['apellidos'] ?? '') . ' ' . ($row['nombres'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars($row['sexo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['condicion'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['servicio_solicitante'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['medico_turno'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['tipo_atencion'] ?? '') ?></td>
                    <td class="td-examenes">
                        <?php foreach($lista_examenes as $ex): ?>
                            <span class="examen-badge"><?= htmlspecialchars($ex) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><?= htmlspecialchars($row['diagnostico'] ?? '') ?></td>
                    <td>S/ <?= number_format($row['monto'] ?? 0, 2) ?></td>
                    <td><?= htmlspecialchars($row['numero_boleta'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['convenio'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['hora_examen'] ?? '-') ?></td>
                    <td class="td-acciones">
                        <button class="btn-editar"   onclick="abrirModal(<?= $id_tom ?>)">Editar</button>
                        <button class="btn-eliminar" onclick="confirmarEliminar(<?= $id_tom ?>)">Eliminar</button>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ── PAGINACIÓN INFERIOR ── -->
    <?php if($total_paginas > 1): ?>
    <div class="paginacion paginacion-bottom">
        <span class="pag-info">Página <strong><?= $pagina_actual ?></strong> de <strong><?= $total_paginas ?></strong></span>
        <?php if($pagina_actual > 1): ?>
            <button class="btn-pag" onclick="irPagina(<?= $pagina_actual - 1 ?>)">← Anterior</button>
        <?php endif; ?>
        <?php if($pagina_actual < $total_paginas): ?>
            <button class="btn-pag" onclick="irPagina(<?= $pagina_actual + 1 ?>)">Siguiente →</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<!-- ══════════════════════════════════════════════════════
     MODAL DE EDICIÓN
══════════════════════════════════════════════════════ -->
<div id="modal-editar" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <div class="modal-header">
        <h2>Editar Registro de Tomografía</h2>
        <button class="modal-cerrar" onclick="cerrarModal()">✕</button>
    </div>
    <div class="modal-body">
        <div id="modal-loading" class="modal-loading">Cargando datos...</div>

        <form id="form-editar" style="display:none;" autocomplete="off">
            <input type="hidden" name="action" value="editar">
            <input type="hidden" name="id_tomografia" id="edit-id">

            <div class="modal-grid">

                <!-- Fila 1 -->
                <div class="form-grupo">
                    <label>H.C.</label>
                    <input type="text" name="historia_clinica" id="edit-hc">
                </div>
                <div class="form-grupo">
                    <label>DNI</label>
                    <input type="text" name="dni" id="edit-dni" maxlength="15">
                </div>
                <div class="form-grupo">
                    <label>Fecha</label>
                    <input type="date" name="fecha" id="edit-fecha">
                </div>
                <div class="form-grupo">
                    <label>Hora examen</label>
                    <input type="time" name="hora_examen" id="edit-hora">
                </div>

                <!-- Fila 2 -->
                <div class="form-grupo">
                    <label>Apellidos</label>
                    <input type="text" name="apellidos" id="edit-apellidos">
                </div>
                <div class="form-grupo">
                    <label>Nombres</label>
                    <input type="text" name="nombres" id="edit-nombres">
                </div>
                <div class="form-grupo">
                    <label>Sexo</label>
                    <select name="sexo" id="edit-sexo">
                        <option value="Masculino">Masculino</option>
                        <option value="Femenino">Femenino</option>
                    </select>
                </div>

                <!-- Condición (combo) -->
                <div class="form-grupo">
                    <label>Condición</label>
                    <div class="combo" id="combo-condicion">
                        <input type="text" class="combo-input" placeholder="Buscar condición..." id="txt-condicion">
                        <input type="hidden" name="id_condicion" id="edit-id-condicion">
                        <div class="combo-list">
                            <?php foreach($arr_condiciones as $c): ?>
                            <div class="combo-item" data-id="<?= $c['id_condicion'] ?>" data-nombre="<?= htmlspecialchars($c['nombre_condicion']) ?>">
                                <?= htmlspecialchars($c['nombre_condicion']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Servicio (combo) -->
                <div class="form-grupo">
                    <label>Servicio solicitante</label>
                    <div class="combo" id="combo-servicio">
                        <input type="text" class="combo-input" placeholder="Buscar servicio..." id="txt-servicio">
                        <input type="hidden" name="id_servicio" id="edit-id-servicio">
                        <div class="combo-list">
                            <?php foreach($arr_servicios as $s): ?>
                            <div class="combo-item" data-id="<?= $s['id_servicio'] ?>" data-nombre="<?= htmlspecialchars($s['nombre_servicio']) ?>">
                                <?= htmlspecialchars($s['nombre_servicio']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Médico (combo) -->
                <div class="form-grupo">
                    <label>Médico turno</label>
                    <div class="combo" id="combo-medico">
                        <input type="text" class="combo-input" placeholder="Buscar médico..." id="txt-medico">
                        <input type="hidden" name="id_medico" id="edit-id-medico">
                        <div class="combo-list">
                            <?php foreach($arr_medicos as $m): ?>
                            <div class="combo-item" data-id="<?= $m['id_medico'] ?>" data-nombre="<?= htmlspecialchars($m['nombre_medico']) ?>">
                                <?= htmlspecialchars($m['nombre_medico']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Tipo atención (combo) -->
                <div class="form-grupo">
                    <label>Tipo de atención</label>
                    <div class="combo" id="combo-tipo">
                        <input type="text" class="combo-input" placeholder="Buscar tipo..." id="txt-tipo">
                        <input type="hidden" name="id_tipo_atencion" id="edit-id-tipo">
                        <div class="combo-list">
                            <?php foreach($arr_tipos as $t): ?>
                            <div class="combo-item" data-id="<?= $t['id_tipo_atencion'] ?>" data-nombre="<?= htmlspecialchars($t['nombre_tipo_atencion']) ?>">
                                <?= htmlspecialchars($t['nombre_tipo_atencion']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Monto -->
                <div class="form-grupo">
                    <label>Monto (S/)</label>
                    <input type="number" step="0.01" min="0" name="monto" id="edit-monto">
                </div>

                <!-- Boleta -->
                <div class="form-grupo">
                    <label>Número de boleta</label>
                    <input type="text" name="numero_boleta" id="edit-boleta">
                </div>

                <!-- Convenio -->
                <div class="form-grupo">
                    <label>Convenio</label>
                    <input type="text" name="convenio" id="edit-convenio">
                </div>

                <!-- Exámenes: 3 combos uno debajo del otro -->
                <div class="form-grupo form-grupo-full">
                    <label>Exámenes solicitados (máx. 3)</label>
                    <div class="examenes-edit-grid">

                        <!-- Examen 1 -->
                        <div class="combo" id="combo-ex1">
                            <input type="text" class="combo-input" placeholder="Examen 1" id="txt-ex1">
                            <input type="hidden" name="id_examen[]" id="edit-id-ex1">
                            <div class="combo-list">
                                <?php foreach($arr_examenes as $e): ?>
                                <div class="combo-item" data-id="<?= $e['id_examen'] ?>" data-nombre="<?= htmlspecialchars($e['nombre_examen']) ?>">
                                    <?= htmlspecialchars($e['nombre_examen']) ?><?= !empty($e['codigo_cpt']) ? ' - CPT: '.htmlspecialchars($e['codigo_cpt']) : '' ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Examen 2 -->
                        <div class="combo" id="combo-ex2">
                            <input type="text" class="combo-input" placeholder="Examen 2 (opcional)" id="txt-ex2">
                            <input type="hidden" name="id_examen[]" id="edit-id-ex2">
                            <div class="combo-list">
                                <?php foreach($arr_examenes as $e): ?>
                                <div class="combo-item" data-id="<?= $e['id_examen'] ?>" data-nombre="<?= htmlspecialchars($e['nombre_examen']) ?>">
                                    <?= htmlspecialchars($e['nombre_examen']) ?><?= !empty($e['codigo_cpt']) ? ' - CPT: '.htmlspecialchars($e['codigo_cpt']) : '' ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Examen 3 -->
                        <div class="combo" id="combo-ex3">
                            <input type="text" class="combo-input" placeholder="Examen 3 (opcional)" id="txt-ex3">
                            <input type="hidden" name="id_examen[]" id="edit-id-ex3">
                            <div class="combo-list">
                                <?php foreach($arr_examenes as $e): ?>
                                <div class="combo-item" data-id="<?= $e['id_examen'] ?>" data-nombre="<?= htmlspecialchars($e['nombre_examen']) ?>">
                                    <?= htmlspecialchars($e['nombre_examen']) ?><?= !empty($e['codigo_cpt']) ? ' - CPT: '.htmlspecialchars($e['codigo_cpt']) : '' ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Diagnóstico -->
                <div class="form-grupo form-grupo-full">
                    <label>Diagnóstico</label>
                    <textarea name="diagnostico" id="edit-diagnostico" rows="3"></textarea>
                </div>

            </div><!-- /modal-grid -->

            <div class="modal-footer">
                <button type="button" class="btn-cancelar" onclick="cerrarModal()">Cancelar</button>
                <button type="button" class="btn-guardar"  onclick="guardarCambios()">Guardar Cambios</button>
            </div>
        </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════ -->
<script>
// ── Paginación ──────────────────────────────────────
function irPagina(n){
    document.getElementById('input-pagina').value = n;
    document.getElementById('form-filtros').submit();
}
function limpiarFiltros(){ window.location.href = window.location.pathname; }
function descargarPDF()  { alert('Función de PDF en desarrollo.'); }

// ── Combos reutilizables ────────────────────────────
function iniciarCombo(comboEl){
    const input  = comboEl.querySelector('.combo-input');
    const lista  = comboEl.querySelector('.combo-list');
    const items  = Array.from(comboEl.querySelectorAll('.combo-item'));
    let idx = -1;

    function visibles(){ return items.filter(i => i.style.display !== 'none'); }

    function filtrar(){
        const txt = input.value.toLowerCase();
        let n = 0;
        items.forEach(item => {
            const show = item.textContent.toLowerCase().includes(txt);
            item.style.display = show ? 'block' : 'none';
            if(show) n++;
            item.classList.remove('activo');
        });
        idx = -1;
        lista.style.display = n > 0 ? 'block' : 'none';
    }

    function marcar(i){
        const v = visibles();
        if(!v.length) return;
        if(i < 0) i = v.length - 1;
        if(i >= v.length) i = 0;
        idx = i;
        v.forEach(x => x.classList.remove('activo'));
        v[idx].classList.add('activo');
        v[idx].scrollIntoView({ block:'nearest' });
    }

    function seleccionar(item){
        const hidden = comboEl.querySelector('input[type=hidden]');
        input.value  = item.dataset.nombre || item.textContent.trim();
        hidden.value = item.dataset.id;
        lista.style.display = 'none';
        idx = -1;
    }

    input.addEventListener('focus',  () => { filtrar(); lista.style.display = visibles().length ? 'block' : 'none'; });
    input.addEventListener('click',  () => { filtrar(); lista.style.display = visibles().length ? 'block' : 'none'; });
    input.addEventListener('input',  () => { comboEl.querySelector('input[type=hidden]').value = ''; filtrar(); });
    input.addEventListener('blur',   () => setTimeout(() => lista.style.display = 'none', 160));
    input.addEventListener('keydown', e => {
        if(e.key === 'ArrowDown'){ e.preventDefault(); marcar(idx+1); }
        if(e.key === 'ArrowUp')  { e.preventDefault(); marcar(idx-1); }
        if(e.key === 'Enter'){
            const v = visibles();
            if(lista.style.display === 'block' && idx >= 0 && v[idx]){
                e.preventDefault(); seleccionar(v[idx]);
            }
        }
        if(e.key === 'Escape') lista.style.display = 'none';
    });
    items.forEach(item => item.addEventListener('mousedown', e => { e.preventDefault(); seleccionar(item); }));
}

// ── Abrir modal ─────────────────────────────────────
function abrirModal(id){
    const modal   = document.getElementById('modal-editar');
    const loading = document.getElementById('modal-loading');
    const form    = document.getElementById('form-editar');
    modal.style.display = 'flex';
    loading.style.display = 'block';
    loading.textContent   = 'Cargando datos...';
    form.style.display    = 'none';

    fetch(`?action=get&id=${id}`)
        .then(r => r.json())
        .then(d => {
            // Campos simples
            document.getElementById('edit-id').value        = d.id_tomografia;
            document.getElementById('edit-hc').value        = d.historia_clinica ?? '';
            document.getElementById('edit-dni').value       = d.dni ?? '';
            document.getElementById('edit-fecha').value     = d.fecha ?? '';
            document.getElementById('edit-hora').value      = d.hora_examen ?? '';
            document.getElementById('edit-apellidos').value = d.apellidos ?? '';
            document.getElementById('edit-nombres').value   = d.nombres ?? '';
            document.getElementById('edit-monto').value     = d.monto ?? 0;
            document.getElementById('edit-boleta').value    = d.numero_boleta ?? '';
            document.getElementById('edit-convenio').value  = d.convenio ?? '';
            document.getElementById('edit-diagnostico').value = d.diagnostico ?? '';

            // Sexo
            const sexoSel = document.getElementById('edit-sexo');
            for(let i=0;i<sexoSel.options.length;i++){
                if(sexoSel.options[i].value === d.sexo){ sexoSel.selectedIndex=i; break; }
            }

            // Combos: cargar por id
            cargarCombo('combo-condicion', 'txt-condicion', 'edit-id-condicion', d.id_condicion);
            cargarCombo('combo-servicio',  'txt-servicio',  'edit-id-servicio',  d.id_servicio);
            cargarCombo('combo-medico',    'txt-medico',    'edit-id-medico',    d.id_medico);
            cargarCombo('combo-tipo',      'txt-tipo',      'edit-id-tipo',      d.id_tipo_atencion);

            // Exámenes (array desde tomografia_examenes)
            const exs = d.examenes || [];
            cargarComboExamen('combo-ex1','txt-ex1','edit-id-ex1', exs[0] ?? null);
            cargarComboExamen('combo-ex2','txt-ex2','edit-id-ex2', exs[1] ?? null);
            cargarComboExamen('combo-ex3','txt-ex3','edit-id-ex3', exs[2] ?? null);

            loading.style.display = 'none';
            form.style.display    = 'block';

            // Iniciar combos del modal si no están iniciados
            document.querySelectorAll('#form-editar .combo').forEach(c => {
                if(!c.dataset.iniciado){ iniciarCombo(c); c.dataset.iniciado='1'; }
            });
        })
        .catch(() => { loading.textContent = 'Error al cargar los datos.'; });
}

function cargarCombo(comboId, txtId, hiddenId, valor){
    const combo  = document.getElementById(comboId);
    const txt    = document.getElementById(txtId);
    const hidden = document.getElementById(hiddenId);
    if(!combo || valor == null){ if(txt) txt.value=''; if(hidden) hidden.value=''; return; }
    const item = combo.querySelector(`.combo-item[data-id="${valor}"]`);
    if(item){
        txt.value    = item.dataset.nombre || item.textContent.trim();
        hidden.value = valor;
    } else {
        txt.value=''; hidden.value='';
    }
}

function cargarComboExamen(comboId, txtId, hiddenId, examen){
    const txt    = document.getElementById(txtId);
    const hidden = document.getElementById(hiddenId);
    if(!examen){ txt.value=''; hidden.value=''; return; }
    txt.value    = examen.nombre_examen ?? '';
    hidden.value = examen.id_examen ?? '';
}

function cerrarModal(){
    document.getElementById('modal-editar').style.display = 'none';
}
document.getElementById('modal-editar').addEventListener('click', function(e){
    if(e.target === this) cerrarModal();
});

// ── Guardar cambios ─────────────────────────────────
function guardarCambios(){
    const form = document.getElementById('form-editar');

    // Validar examen 1
    if(!document.getElementById('edit-id-ex1').value){
        alert('Seleccione al menos el Examen 1.'); return;
    }

    const data = new FormData(form);
    fetch(window.location.href, { method:'POST', body:data })
        .then(r => r.json())
        .then(d => {
            if(d.success){ cerrarModal(); location.reload(); }
            else alert('Error al guardar los cambios.');
        })
        .catch(() => alert('Error de conexión.'));
}

// ── Eliminar ────────────────────────────────────────
function confirmarEliminar(id){
    if(!confirm('¿Eliminar este registro? Esta acción no se puede deshacer.')) return;
    const data = new FormData();
    data.append('action','eliminar');
    data.append('id_tomografia', id);
    fetch(window.location.href,{method:'POST',body:data})
        .then(r=>r.json())
        .then(d=>{ if(d.success) location.reload(); else alert('Error al eliminar.'); })
        .catch(()=>alert('Error de conexión.'));
}
</script>

</body>
</html>
