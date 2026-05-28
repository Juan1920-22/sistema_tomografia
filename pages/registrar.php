<?php
session_start();
include('../includes/db.php');

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$mensaje = '';
$error   = '';

/* ═══════════════════════════════════════════
   AJAX: buscar paciente por DNI
═══════════════════════════════════════════ */
if(isset($_GET['action']) && $_GET['action'] === 'buscar_dni'){
    $dni = $conn->real_escape_string(trim($_GET['dni'] ?? ''));
    $stmt = $conn->prepare("SELECT apellidos, nombres, sexo FROM tomografias WHERE dni=? ORDER BY id_tomografia DESC LIMIT 1");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode($row ?: null);
    exit;
}

/* ═══════════════════════════════════════════
   CARGAR DATOS DE MANTENIMIENTO
═══════════════════════════════════════════ */
$condiciones    = [];
$servicios      = [];
$tipos_atencion = [];
$medicos        = [];
$examenes       = [];

$res = $conn->query("SELECT id_condicion, nombre_condicion FROM condiciones_pago WHERE estado='Activo' ORDER BY nombre_condicion ASC");
if($res) while($r = $res->fetch_assoc()) $condiciones[] = $r;

$res = $conn->query("SELECT id_servicio, nombre_servicio FROM servicios_solicitantes WHERE estado='Activo' ORDER BY nombre_servicio ASC");
if($res) while($r = $res->fetch_assoc()) $servicios[] = $r;

$res = $conn->query("SELECT id_tipo_atencion, nombre_tipo_atencion FROM tipos_atencion WHERE estado='Activo' ORDER BY nombre_tipo_atencion ASC");
if($res) while($r = $res->fetch_assoc()) $tipos_atencion[] = $r;

$res = $conn->query("SELECT id_medico, nombre_medico FROM medicos_turno WHERE estado='Activo' ORDER BY nombre_medico ASC");
if($res) while($r = $res->fetch_assoc()) $medicos[] = $r;

$res = $conn->query("SELECT id_examen, nombre_examen, codigo_cpt FROM examenes_solicitados WHERE estado='Activo' ORDER BY nombre_examen ASC");
if($res) while($r = $res->fetch_assoc()) $examenes[] = $r;

/* Tabla exámenes (por si no existe) */
$conn->query("CREATE TABLE IF NOT EXISTS tomografia_examenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_tomografia INT NOT NULL,
    id_examen INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

/* ═══════════════════════════════════════════
   FUNCIONES HELPER
═══════════════════════════════════════════ */
function buscarNombre($array, $idCampo, $nombreCampo, $id){
    foreach($array as $item)
        if((int)$item[$idCampo] === (int)$id) return $item[$nombreCampo];
    return '';
}
function buscarCPT($array, $id){
    foreach($array as $item)
        if((int)$item['id_examen'] === (int)$id) return $item['codigo_cpt'] ?? '';
    return '';
}

/* ═══════════════════════════════════════════
   GUARDAR TOMOGRAFÍA
═══════════════════════════════════════════ */
if(isset($_POST['registrar'])){

    $historia_clinica = trim($_POST['historia_clinica'] ?? '');
    $dni              = trim($_POST['dni']              ?? '');
    $fecha            = trim($_POST['fecha']            ?? '');
    $apellidos        = trim($_POST['apellidos']        ?? '');
    $nombres          = trim($_POST['nombres']          ?? '');
    $sexo             = trim($_POST['sexo']             ?? '');
    $diagnostico      = trim($_POST['diagnostico']      ?? '');
    $hora_examen      = trim($_POST['hora_examen']      ?? '');
    $monto            = floatval($_POST['monto']        ?? 0);
    $numero_boleta    = trim($_POST['numero_boleta']    ?? '');
    $convenio         = trim($_POST['convenio']         ?? '');

    // Normalizar sexo
    $sexo_lower = strtolower($sexo);
    if($sexo_lower === 'f') $sexo = 'Femenino';
    if($sexo_lower === 'm') $sexo = 'Masculino';

    $id_condicion     = intval($_POST['id_condicion']     ?? 0);
    $id_servicio      = intval($_POST['id_servicio']      ?? 0);
    $id_tipo_atencion = intval($_POST['id_tipo_atencion'] ?? 0);
    $id_medico        = intval($_POST['id_medico']        ?? 0);

    // Exámenes
    $ids_examenes = $_POST['id_examen'] ?? [];
    $ids_limpios  = [];
    foreach($ids_examenes as $idEx){
        $idEx = intval($idEx);
        if($idEx > 0 && !in_array($idEx, $ids_limpios)) $ids_limpios[] = $idEx;
    }
    if(count($ids_limpios) > 3) $ids_limpios = array_slice($ids_limpios, 0, 3);

    // Validación
    if(!$historia_clinica || !$dni || !$fecha || !$apellidos || !$nombres || !$sexo
        || $id_condicion <= 0 || $id_servicio <= 0 || $id_tipo_atencion <= 0
        || $id_medico <= 0 || count($ids_limpios) === 0){
        $error = "Complete todos los campos obligatorios y seleccione opciones válidas.";
    } else {

        $condicion_nombre     = buscarNombre($condiciones,    'id_condicion',     'nombre_condicion',     $id_condicion);
        $servicio_nombre      = buscarNombre($servicios,      'id_servicio',      'nombre_servicio',      $id_servicio);
        $tipo_atencion_nombre = buscarNombre($tipos_atencion, 'id_tipo_atencion', 'nombre_tipo_atencion', $id_tipo_atencion);
        $medico_nombre        = buscarNombre($medicos,        'id_medico',        'nombre_medico',        $id_medico);

        $nombres_examenes = [];
        $codigos_cpt      = [];
        foreach($ids_limpios as $idEx){
            $n = buscarNombre($examenes, 'id_examen', 'nombre_examen', $idEx);
            $c = buscarCPT($examenes, $idEx);
            if($n) $nombres_examenes[] = $n;
            if($c) $codigos_cpt[]      = $c;
        }

        $examen_solicitado   = implode(', ', $nombres_examenes);
        $codigo_cpt          = implode(', ', $codigos_cpt);
        $id_examen_principal = $ids_limpios[0];

        $sql = "INSERT INTO tomografias (
            historia_clinica, dni, fecha, apellidos, nombres, sexo,
            condicion, servicio_solicitante, medico_turno, examen_solicitado,
            tipo_atencion, diagnostico, hora_examen,
            monto, numero_boleta, convenio,
            id_condicion, id_servicio, id_examen, id_tipo_atencion, id_medico, codigo_cpt
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        if(!$stmt){
            $error = "Error en la consulta: " . $conn->error;
        } else {
            // 22 campos:
            // s  historia_clinica
            // s  dni
            // s  fecha
            // s  apellidos
            // s  nombres
            // s  sexo
            // s  condicion_nombre
            // s  servicio_nombre
            // s  medico_nombre
            // s  examen_solicitado
            // s  tipo_atencion_nombre
            // s  diagnostico
            // s  hora_examen
            // d  monto
            // s  numero_boleta
            // s  convenio
            // i  id_condicion
            // i  id_servicio
            // i  id_examen_principal
            // i  id_tipo_atencion
            // i  id_medico
            // s  codigo_cpt
            $stmt->bind_param(
                "sssssssssssssdssiiiiis",
                $historia_clinica, $dni, $fecha, $apellidos, $nombres, $sexo,
                $condicion_nombre, $servicio_nombre, $medico_nombre, $examen_solicitado,
                $tipo_atencion_nombre, $diagnostico, $hora_examen,
                $monto, $numero_boleta, $convenio,
                $id_condicion, $id_servicio, $id_examen_principal, $id_tipo_atencion, $id_medico, $codigo_cpt
            );

            if($stmt->execute()){
                $id_tomografia = $conn->insert_id;
                foreach($ids_limpios as $idEx){
                    $s2 = $conn->prepare("INSERT INTO tomografia_examenes (id_tomografia, id_examen) VALUES (?,?)");
                    $s2->bind_param("ii", $id_tomografia, $idEx);
                    $s2->execute();
                    $s2->close();
                }
                $mensaje = "Tomografía registrada correctamente.";
            } else {
                $error = "Error al registrar: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Tomografía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/registrar.css?v=60">
</head>
<body>

<header class="topbar">
    <div class="topbar-title">HOSPITAL SAN JOSÉ DE CHINCHA</div>
    <nav class="topbar-menu">
        <a href="dashboard.php">Inicio</a>
        <a href="registrar.php" class="active">Registrar</a>
        <a href="historial_tomografia.php">Historial</a>
        <a href="reportes.php">Reportes</a>
        <a href="mantenimiento.php">Mantenimiento</a>
        <a href="logout.php" class="salir">Salir</a>
    </nav>
</header>

<main class="registrar-container">
<section class="panel">

    <a href="dashboard.php" class="btn-volver">Volver al Menú</a>
    <h1>Registrar Nueva Tomografía</h1>
    <p class="subtitulo">Complete los datos del paciente y del examen solicitado</p>

    <?php if($mensaje): ?><div class="alerta exito"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if($error):   ?><div class="alerta error"><?= htmlspecialchars($error)   ?></div><?php endif; ?>

    <form method="POST" class="form-registro" id="form-registro" autocomplete="off">

        <div class="grid-form" id="grid-form">

            <!-- H.C. -->
            <div class="campo">
                <label>H.C.</label>
                <input type="text" name="historia_clinica" id="f-hc"
                       class="campo-enter" placeholder="Ingrese H.C." required>
            </div>

            <!-- DNI -->
            <div class="campo">
                <label>DNI</label>
<input type="text" name="dni" id="f-dni"
       class="campo-enter" placeholder="Ingrese DNI" maxlength="8"
       pattern="[0-9]{8}" inputmode="numeric" required>
            </div>

            <!-- Fecha -->
            <div class="campo">
                <label>Fecha</label>
                <input type="date" name="fecha" id="f-fecha"
                       class="campo-enter" required max="<?= date('Y-m-d') ?>">
            </div>

            <!-- Sexo -->
            <div class="campo">
                <label>Sexo</label>
                <input type="text" name="sexo" id="f-sexo"
                       class="campo-enter" placeholder="M = Masculino / F = Femenino"
                       maxlength="9" required>
            </div>

            <!-- Apellidos -->
            <div class="campo">
                <label>Apellidos</label>
                <input type="text" name="apellidos" id="f-apellidos"
                       class="campo-enter" placeholder="Ingrese Apellidos" required>
            </div>

            <!-- Nombres -->
            <div class="campo">
                <label>Nombres</label>
                <input type="text" name="nombres" id="f-nombres"
                       class="campo-enter" placeholder="Ingrese Nombres" required>
            </div>

            <!-- Condición (combo) -->
            <div class="campo" id="campo-condicion">
                <label>Condición</label>
                <div class="combo">
                    <input type="text" class="combo-input campo-enter"
                           id="txt-condicion" placeholder="Busque o seleccione condición">
                    <input type="hidden" name="id_condicion" id="id_condicion">
                    <div class="combo-list" id="list-condicion">
                        <?php foreach($condiciones as $c): ?>
                        <div class="combo-item"
                             data-id="<?= $c['id_condicion'] ?>"
                             data-nombre="<?= htmlspecialchars($c['nombre_condicion']) ?>">
                            <?= htmlspecialchars($c['nombre_condicion']) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Monto (aparece dinámicamente) -->
            <div class="campo" id="campo-monto" style="display:none;">
                <label>Monto (S/)</label>
                <input type="number" name="monto" id="f-monto"
                       class="campo-enter" placeholder="Ingrese monto" step="0.01" min="0">
            </div>

            <!-- Boleta (aparece dinámicamente) -->
            <div class="campo" id="campo-boleta" style="display:none;">
                <label>N° Boleta</label>
                <input type="text" name="numero_boleta" id="f-boleta"
                       class="campo-enter" placeholder="N° de boleta">
            </div>

            <!-- Convenio (aparece dinámicamente) -->
            <div class="campo" id="campo-convenio" style="display:none;">
                <label>Tipo de convenio</label>
                <input type="text" name="convenio" id="f-convenio"
                       class="campo-enter" placeholder="Ingrese tipo de convenio">
            </div>

            <!-- Servicio solicitante (combo) -->
            <div class="campo" id="campo-servicio">
                <label>Servicio solicitante</label>
                <div class="combo">
                    <input type="text" class="combo-input campo-enter"
                           id="txt-servicio" placeholder="Busque o seleccione servicio">
                    <input type="hidden" name="id_servicio" id="id_servicio">
                    <div class="combo-list" id="list-servicio">
                        <?php foreach($servicios as $s): ?>
                        <div class="combo-item"
                             data-id="<?= $s['id_servicio'] ?>"
                             data-nombre="<?= htmlspecialchars($s['nombre_servicio']) ?>">
                            <?= htmlspecialchars($s['nombre_servicio']) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Médico de turno (combo) -->
            <div class="campo" id="campo-medico">
                <label>Médico de turno</label>
                <div class="combo">
                    <input type="text" class="combo-input campo-enter"
                           id="txt-medico" placeholder="Busque o seleccione médico">
                    <input type="hidden" name="id_medico" id="id_medico">
                    <div class="combo-list" id="list-medico">
                        <?php foreach($medicos as $m): ?>
                        <div class="combo-item"
                             data-id="<?= $m['id_medico'] ?>"
                             data-nombre="<?= htmlspecialchars($m['nombre_medico']) ?>">
                            <?= htmlspecialchars($m['nombre_medico']) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tipo de atención (combo) -->
            <div class="campo" id="campo-tipo">
                <label>Tipo de atención</label>
                <div class="combo">
                    <input type="text" class="combo-input campo-enter"
                           id="txt-tipo" placeholder="Busque o seleccione tipo de atención">
                    <input type="hidden" name="id_tipo_atencion" id="id_tipo_atencion">
                    <div class="combo-list" id="list-tipo">
                        <?php foreach($tipos_atencion as $t): ?>
                        <div class="combo-item"
                             data-id="<?= $t['id_tipo_atencion'] ?>"
                             data-nombre="<?= htmlspecialchars($t['nombre_tipo_atencion']) ?>">
                            <?= htmlspecialchars($t['nombre_tipo_atencion']) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Exámenes (3 combos, span 2 columnas) -->
            <div class="campo campo-examenes">
                <label>Exámenes solicitados (máx. 3)</label>
                <div class="examenes-grid">

                    <div class="combo">
                        <input type="text" class="combo-input campo-enter"
                               id="txt-ex1" placeholder="Busque o seleccione examen 1">
                        <input type="hidden" name="id_examen[]" id="id_examen_1">
                        <div class="combo-list">
                            <?php foreach($examenes as $e): ?>
                            <div class="combo-item"
                                 data-id="<?= $e['id_examen'] ?>"
                                 data-nombre="<?= htmlspecialchars($e['nombre_examen']) ?>">
                                <?= htmlspecialchars($e['nombre_examen']) ?>
                                <?= !empty($e['codigo_cpt']) ? ' - CPT: '.htmlspecialchars($e['codigo_cpt']) : '' ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="combo">
                        <input type="text" class="combo-input campo-enter"
                               id="txt-ex2" placeholder="Examen 2 (opcional)">
                        <input type="hidden" name="id_examen[]" id="id_examen_2">
                        <div class="combo-list">
                            <?php foreach($examenes as $e): ?>
                            <div class="combo-item"
                                 data-id="<?= $e['id_examen'] ?>"
                                 data-nombre="<?= htmlspecialchars($e['nombre_examen']) ?>">
                                <?= htmlspecialchars($e['nombre_examen']) ?>
                                <?= !empty($e['codigo_cpt']) ? ' - CPT: '.htmlspecialchars($e['codigo_cpt']) : '' ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="combo">
                        <input type="text" class="combo-input campo-enter"
                               id="txt-ex3" placeholder="Examen 3 (opcional)">
                        <input type="hidden" name="id_examen[]" id="id_examen_3">
                        <div class="combo-list">
                            <?php foreach($examenes as $e): ?>
                            <div class="combo-item"
                                 data-id="<?= $e['id_examen'] ?>"
                                 data-nombre="<?= htmlspecialchars($e['nombre_examen']) ?>">
                                <?= htmlspecialchars($e['nombre_examen']) ?>
                                <?= !empty($e['codigo_cpt']) ? ' - CPT: '.htmlspecialchars($e['codigo_cpt']) : '' ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Diagnóstico -->
            <div class="campo campo-diagnostico">
                <label>Diagnóstico</label>
                <textarea name="diagnostico" id="f-diagnostico"
                          placeholder="Ingrese diagnóstico"></textarea>
            </div>

            <!-- Hora -->
            <div class="campo">
                <label>Hora de examen</label>
                <input type="time" name="hora_examen" id="f-hora" class="campo-enter">
            </div>

        </div><!-- /grid-form -->

        <div class="botones">
            <button type="reset" class="btn-limpiar" id="btn-limpiar">Limpiar</button>
            <button type="submit" name="registrar" id="btnRegistrar" class="btn-registrar">
                Registrar Tomografía
            </button>
        </div>

    </form>

</section>
</main>

<!-- ════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function(){

    // ── Condiciones que muestran monto ──────────────────
    const COND_MONTO    = ['contado','exonerado parcial','pago parcial','particular',
                           'credito','essalud','sis','referido'];
    const COND_CONVENIO = ['convenio'];

    // ── Función para obtener todos los campos Enter en orden ──
    function getCamposEnter(){
        return Array.from(document.querySelectorAll('.campo-enter'))
                    .filter(el => {
                        const c = el.closest('.campo') || el.closest('.combo');
                        return c && c.offsetParent !== null; // solo visibles
                    });
    }

    function pasarAlSiguiente(actual){
        const campos = getCamposEnter();
        const idx    = campos.indexOf(actual);
        if(idx >= 0 && campos[idx + 1]) campos[idx + 1].focus();
    }

    // ── Normalizar sexo ──────────────────────────────────
    function normalizarSexo(){
        const s = document.getElementById('f-sexo');
        if(!s) return;
        const v = s.value.trim();
        if(v === 'm' || v === 'M') s.value = 'Masculino';
        if(v === 'f' || v === 'F') s.value = 'Femenino';
    }
    document.getElementById('f-sexo').addEventListener('keyup', normalizarSexo);
    document.getElementById('f-sexo').addEventListener('blur',  normalizarSexo);
// DNI: solo números, máx 8
document.getElementById('f-dni').addEventListener('keypress', function(e){
    if(!/[0-9]/.test(e.key)) e.preventDefault();
});
document.getElementById('f-dni').addEventListener('input', function(){
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8);
});
    // ── DNI: autocompletar datos del paciente ────────────
    document.getElementById('f-dni').addEventListener('blur', function(){
        const dni = this.value.trim();
        if(dni.length < 6) return;
        fetch(`?action=buscar_dni&dni=${encodeURIComponent(dni)}`)
            .then(r => r.json())
            .then(d => {
                if(d){
                    const ap = document.getElementById('f-apellidos');
                    const nm = document.getElementById('f-nombres');
                    const sx = document.getElementById('f-sexo');
                    if(!ap.value) ap.value = d.apellidos ?? '';
                    if(!nm.value) nm.value = d.nombres   ?? '';
                    if(!sx.value) sx.value = d.sexo      ?? '';
                }
            })
            .catch(() => {});
    });

    // ── Mostrar/ocultar Monto y Convenio ─────────────────
    function actualizarCamposCondicion(nombreCondicion){
        const v = nombreCondicion.toLowerCase().trim();
        const mostrarMonto    = COND_MONTO.includes(v);
        const mostrarConvenio = COND_CONVENIO.includes(v);

        const cMonto    = document.getElementById('campo-monto');
        const cBoleta   = document.getElementById('campo-boleta');
        const cConvenio = document.getElementById('campo-convenio');

        cMonto.style.display    = mostrarMonto    ? '' : 'none';
        cBoleta.style.display   = mostrarMonto    ? '' : 'none';
        cConvenio.style.display = mostrarConvenio ? '' : 'none';

        if(!mostrarMonto){
            document.getElementById('f-monto').value  = '';
            document.getElementById('f-boleta').value = '';
        }
        if(!mostrarConvenio){
            document.getElementById('f-convenio').value = '';
        }
    }

    // ── COMBOS ────────────────────────────────────────────
    function iniciarCombo(comboEl, onSeleccionar){
        const input  = comboEl.querySelector('.combo-input');
        const hidden = comboEl.querySelector('input[type=hidden]');
        const lista  = comboEl.querySelector('.combo-list');
        const items  = Array.from(comboEl.querySelectorAll('.combo-item'));
        let idx = -1;

        function visibles(){ return items.filter(i => i.style.display !== 'none'); }

        function filtrar(){
            const txt = input.value.toLowerCase();
            let n = 0;
            items.forEach(item => {
                const show = item.textContent.toLowerCase().includes(txt);
                item.style.display = show ? '' : 'none';
                item.classList.remove('activo');
                if(show) n++;
            });
            idx = -1;
            lista.style.display = n > 0 ? 'block' : 'none';
        }

        function marcar(i){
            const v = visibles();
            if(!v.length) return;
            // Wrapping correcto en ambas direcciones
            if(i < 0) i = v.length - 1;
            if(i >= v.length) i = 0;
            idx = i;
            v.forEach(x => x.classList.remove('activo'));
            v[idx].classList.add('activo');
            v[idx].scrollIntoView({ block: 'nearest' });
        }

        function seleccionar(item){
            const nombre = item.dataset.nombre || item.textContent.trim();
            input.value  = nombre;
            hidden.value = item.dataset.id;
            lista.style.display = 'none';
            idx = -1;
            if(onSeleccionar) onSeleccionar(nombre, item.dataset.id);
        }

        function abrirLista(){
            filtrar();
            if(visibles().length) lista.style.display = 'block';
        }

        input.addEventListener('focus', abrirLista);
        input.addEventListener('click', abrirLista);
        input.addEventListener('input', () => { hidden.value = ''; filtrar(); });
        input.addEventListener('blur',  () => setTimeout(() => lista.style.display = 'none', 160));

        input.addEventListener('keydown', e => {
            // Ctrl+Enter → foco en botón registrar
            if(e.ctrlKey && e.key === 'Enter'){
                e.preventDefault();
                document.getElementById('btnRegistrar').focus();
                return;
            }
            if(e.key === 'ArrowDown'){
                e.preventDefault();
                if(lista.style.display !== 'block') abrirLista();
                marcar(idx + 1);
                return;
            }
            if(e.key === 'ArrowUp'){
                e.preventDefault();
                if(lista.style.display !== 'block') abrirLista();
                marcar(idx - 1);
                return;
            }
            if(e.key === 'Enter'){
                e.preventDefault();
                const v = visibles();
                if(lista.style.display === 'block' && idx >= 0 && v[idx]){
                    seleccionar(v[idx]);
                    pasarAlSiguiente(input);
                } else {
                    // Coincidencia exacta
                    const ex = items.find(i =>
                        i.textContent.trim().toLowerCase() === input.value.trim().toLowerCase()
                    );
                    if(ex){ seleccionar(ex); pasarAlSiguiente(input); }
                    else pasarAlSiguiente(input);
                }
            }
            if(e.key === 'Escape') lista.style.display = 'none';
        });

        items.forEach(item =>
            item.addEventListener('mousedown', e => { e.preventDefault(); seleccionar(item); })
        );
    }

    // Iniciar todos los combos
    document.querySelectorAll('.combo').forEach(combo => {
        const input = combo.querySelector('.combo-input');
        if(!input) return;

        // Callback especial para condición
        if(input.id === 'txt-condicion'){
            iniciarCombo(combo, (nombre) => actualizarCamposCondicion(nombre));
        } else {
            iniciarCombo(combo, null);
        }
    });

    // ── Campos simples: Enter pasa al siguiente ──────────
    document.querySelectorAll('.campo-enter').forEach(campo => {
        if(campo.classList.contains('combo-input')) return; // ya manejado

        campo.addEventListener('keydown', function(e){
            if(e.ctrlKey && e.key === 'Enter'){
                e.preventDefault();
                document.getElementById('btnRegistrar').focus();
                return;
            }
            if(e.key === 'Enter'){
                e.preventDefault();
                if(campo.id === 'f-sexo') normalizarSexo();
                pasarAlSiguiente(campo);
            }
        });
    });

    // ── Limpiar también oculta campos dinámicos ──────────
    document.getElementById('btn-limpiar').addEventListener('click', () => {
        setTimeout(() => {
            actualizarCamposCondicion('');
            document.querySelectorAll('.combo-input').forEach(i => i.value = '');
            document.querySelectorAll('input[type=hidden]').forEach(i => i.value = '');
        }, 50);
    });

    // ── Validación antes de enviar ───────────────────────
    document.getElementById('form-registro').addEventListener('submit', function(e){
        normalizarSexo();

        const checks = [
            ['id_condicion',     'Seleccione una condición válida.'],
            ['id_servicio',      'Seleccione un servicio solicitante válido.'],
            ['id_medico',        'Seleccione un médico de turno válido.'],
            ['id_tipo_atencion', 'Seleccione un tipo de atención válido.'],
            ['id_examen_1',      'Seleccione al menos un examen solicitado.'],
        ];
        for(const [id, msg] of checks){
            if(!document.getElementById(id).value){
                alert(msg); e.preventDefault(); return;
            }
        }
    });

});
</script>

</body>
</html>