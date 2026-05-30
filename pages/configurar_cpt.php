<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost","root","","sistema_tomografia");
if($conn->connect_error) die("Conexión fallida: ".$conn->connect_error);

// Crear tabla si no existe
$conn->query("
CREATE TABLE IF NOT EXISTS cpt_codigos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_examen INT NOT NULL UNIQUE,
    codigo_cpt VARCHAR(20) NOT NULL,
    co_codups VARCHAR(20) DEFAULT '00003414',
    servicio_especialidad VARCHAR(20) DEFAULT '080900',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$mensaje = '';
$error   = '';

// ── GUARDAR / ACTUALIZAR ─────────────────────────────────────
if(isset($_POST['action']) && $_POST['action'] === 'guardar'){
    $id_examen            = intval($_POST['id_examen'] ?? 0);
    $codigo_cpt           = trim($_POST['codigo_cpt'] ?? '');
    $co_codups            = trim($_POST['co_codups'] ?? '00003414');
    $servicio_especialidad= trim($_POST['servicio_especialidad'] ?? '080900');

    if($id_examen <= 0 || $codigo_cpt === ''){
        $error = 'Seleccione un examen e ingrese el código CPT.';
    } else {
        // INSERT OR UPDATE
        $stmt = $conn->prepare("
            INSERT INTO cpt_codigos (id_examen, codigo_cpt, co_codups, servicio_especialidad)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE
                codigo_cpt=VALUES(codigo_cpt),
                co_codups=VALUES(co_codups),
                servicio_especialidad=VALUES(servicio_especialidad)
        ");
        $stmt->bind_param("isss", $id_examen, $codigo_cpt, $co_codups, $servicio_especialidad);
        if($stmt->execute()) $mensaje = 'Código CPT guardado correctamente.';
        else $error = 'Error al guardar: '.$stmt->error;
        $stmt->close();
    }
}

// ── ELIMINAR ─────────────────────────────────────────────────
if(isset($_POST['action']) && $_POST['action'] === 'eliminar'){
    $id = intval($_POST['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM cpt_codigos WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $mensaje = 'Registro eliminado.';
}

// ── OBTENER PARA EDITAR (AJAX) ───────────────────────────────
if(isset($_GET['action']) && $_GET['action'] === 'get'){
    $id = intval($_GET['id']);
    $s  = $conn->prepare("SELECT cc.*, e.nombre_examen FROM cpt_codigos cc JOIN examenes_solicitados e ON e.id_examen=cc.id_examen WHERE cc.id=?");
    $s->bind_param("i",$id); $s->execute();
    echo json_encode($s->get_result()->fetch_assoc());
    exit;
}

// ── CARGAR DATOS ─────────────────────────────────────────────
$examenes = [];
$res = $conn->query("SELECT id_examen, nombre_examen FROM examenes_solicitados WHERE estado='Activo' ORDER BY nombre_examen ASC");
while($r = $res->fetch_assoc()) $examenes[] = $r;

$lista = $conn->query("
    SELECT cc.id, cc.codigo_cpt, cc.co_codups, cc.servicio_especialidad,
           e.nombre_examen
    FROM cpt_codigos cc
    JOIN examenes_solicitados e ON e.id_examen = cc.id_examen
    ORDER BY e.nombre_examen ASC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configurar CPT-Code</title>
<link rel="stylesheet" href="../css/reporte_cpt.css?v=1">
</head>
<body class="body-config">

<div class="config-container">

    <a href="reporte_cpt.php" class="btn-volver">← Volver a Reporte CPT-Code</a>

    <h1 class="config-titulo">Mantenimiento CPT-Code</h1>
    <p class="config-subtitulo">Administra los códigos CPT asociados a los exámenes tomográficos.</p>

    <?php if($mensaje): ?><div class="alerta exito"><?=htmlspecialchars($mensaje)?></div><?php endif; ?>
    <?php if($error):   ?><div class="alerta error"><?=htmlspecialchars($error)?></div><?php endif; ?>

    <!-- FORMULARIO -->
    <form method="POST" class="form-config" id="form-config">
        <input type="hidden" name="action" value="guardar">
        <input type="hidden" name="id_editar" id="id_editar" value="0">

        <div class="config-grid">
            <div class="config-campo">
                <label>Examen solicitado</label>
                <select name="id_examen" id="sel-examen" required>
                    <option value="">Seleccione examen</option>
                    <?php foreach($examenes as $e): ?>
                    <option value="<?=$e['id_examen']?>"><?=htmlspecialchars($e['nombre_examen'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="config-campo">
                <label>Código CPT</label>
                <input type="text" name="codigo_cpt" id="inp-cpt" placeholder="Ej. 76700" required>
            </div>
            <div class="config-campo">
                <label>CO-CODUPS</label>
                <input type="text" name="co_codups" id="inp-codups" value="00003414">
            </div>
            <div class="config-campo">
                <label>Servicio / Especialidad</label>
                <input type="text" name="servicio_especialidad" id="inp-serv" value="080900">
            </div>
            <div class="config-campo config-campo-btn">
                <button type="submit" class="btn-guardar-config">Guardar</button>
                <button type="button" class="btn-cancelar-config" onclick="cancelarEdicion()">Cancelar</button>
            </div>
        </div>
    </form>

    <!-- TABLA LISTADO -->
    <div class="tabla-config-wrapper">
        <table class="tabla-config">
            <thead>
                <tr>
                    <th>Examen solicitado</th>
                    <th>Código CPT</th>
                    <th>CO-CODUPS</th>
                    <th>Servicio / Especialidad</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if($lista->num_rows === 0): ?>
                <tr><td colspan="5" class="sin-datos">No hay códigos CPT configurados aún</td></tr>
            <?php else: ?>
            <?php while($r = $lista->fetch_assoc()): ?>
                <tr>
                    <td><?=htmlspecialchars($r['nombre_examen'])?></td>
                    <td><?=htmlspecialchars($r['codigo_cpt'])?></td>
                    <td><?=htmlspecialchars($r['co_codups'])?></td>
                    <td><?=htmlspecialchars($r['servicio_especialidad'])?></td>
                    <td class="td-acciones">
                        <button class="btn-editar-cfg"   onclick="cargarEditar(<?=$r['id']?>)">Editar</button>
                        <button class="btn-eliminar-cfg" onclick="eliminar(<?=$r['id']?>)">Eliminar</button>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function cargarEditar(id){
    fetch(`?action=get&id=${id}`)
        .then(r=>r.json())
        .then(d=>{
            document.getElementById('id_editar').value   = d.id;
            document.getElementById('sel-examen').value  = d.id_examen;
            document.getElementById('inp-cpt').value     = d.codigo_cpt;
            document.getElementById('inp-codups').value  = d.co_codups;
            document.getElementById('inp-serv').value    = d.servicio_especialidad;
            window.scrollTo({top:0, behavior:'smooth'});
        });
}

function cancelarEdicion(){
    document.getElementById('id_editar').value   = '0';
    document.getElementById('sel-examen').value  = '';
    document.getElementById('inp-cpt').value     = '';
    document.getElementById('inp-codups').value  = '00003414';
    document.getElementById('inp-serv').value    = '080900';
}

function eliminar(id){
    if(!confirm('¿Eliminar este código CPT?')) return;
    const f = new FormData();
    f.append('action','eliminar');
    f.append('id', id);
    fetch(window.location.href,{method:'POST',body:f})
        .then(()=>location.reload());
}
</script>
</body>
</html>