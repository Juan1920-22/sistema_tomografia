<?php
session_start();
include('../includes/db.php');

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$mensaje = '';
$error = '';

/* ================================
   CARGAR DATOS DE MANTENIMIENTO
================================ */
$condiciones = [];
$servicios = [];
$tipos_atencion = [];
$medicos = [];
$examenes = [];

$res = $conn->query("SELECT id_condicion, nombre_condicion FROM condiciones_pago WHERE estado='Activo' ORDER BY nombre_condicion ASC");
if($res){ while($row = $res->fetch_assoc()){ $condiciones[] = $row; } }

$res = $conn->query("SELECT id_servicio, nombre_servicio FROM servicios_solicitantes WHERE estado='Activo' ORDER BY nombre_servicio ASC");
if($res){ while($row = $res->fetch_assoc()){ $servicios[] = $row; } }

$res = $conn->query("SELECT id_tipo_atencion, nombre_tipo_atencion FROM tipos_atencion WHERE estado='Activo' ORDER BY nombre_tipo_atencion ASC");
if($res){ while($row = $res->fetch_assoc()){ $tipos_atencion[] = $row; } }

$res = $conn->query("SELECT id_medico, nombre_medico FROM medicos_turno WHERE estado='Activo' ORDER BY nombre_medico ASC");
if($res){ while($row = $res->fetch_assoc()){ $medicos[] = $row; } }

$res = $conn->query("SELECT id_examen, nombre_examen, codigo_cpt FROM examenes_solicitados WHERE estado='Activo' ORDER BY nombre_examen ASC");
if($res){ while($row = $res->fetch_assoc()){ $examenes[] = $row; } }

/* Tabla para guardar hasta 3 exámenes */
$conn->query("
CREATE TABLE IF NOT EXISTS tomografia_examenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_tomografia INT NOT NULL,
    id_examen INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

/* ================================
   FUNCIONES
================================ */
function buscarNombre($array, $idCampo, $nombreCampo, $id){
    foreach($array as $item){
        if((int)$item[$idCampo] === (int)$id){
            return $item[$nombreCampo];
        }
    }
    return '';
}

function buscarCPT($array, $id){
    foreach($array as $item){
        if((int)$item['id_examen'] === (int)$id){
            return $item['codigo_cpt'] ?? '';
        }
    }
    return '';
}

/* ================================
   GUARDAR TOMOGRAFÍA
================================ */
if(isset($_POST['registrar'])){

    $historia_clinica = trim($_POST['historia_clinica'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $fecha = trim($_POST['fecha'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $hora_examen = trim($_POST['hora_examen'] ?? '');

    if(strtolower($sexo) === 'f'){
        $sexo = 'Femenino';
    }

    if(strtolower($sexo) === 'm'){
        $sexo = 'Masculino';
    }

    $id_condicion = intval($_POST['id_condicion'] ?? 0);
    $id_servicio = intval($_POST['id_servicio'] ?? 0);
    $id_tipo_atencion = intval($_POST['id_tipo_atencion'] ?? 0);
    $id_medico = intval($_POST['id_medico'] ?? 0);

    $ids_examenes = $_POST['id_examen'] ?? [];
    $ids_examenes_limpios = [];

    foreach($ids_examenes as $idEx){
        $idEx = intval($idEx);

        if($idEx > 0 && !in_array($idEx, $ids_examenes_limpios)){
            $ids_examenes_limpios[] = $idEx;
        }
    }

    if(count($ids_examenes_limpios) > 3){
        $ids_examenes_limpios = array_slice($ids_examenes_limpios, 0, 3);
    }

    if(
        $historia_clinica == '' ||
        $dni == '' ||
        $fecha == '' ||
        $apellidos == '' ||
        $nombres == '' ||
        $sexo == '' ||
        $id_condicion <= 0 ||
        $id_servicio <= 0 ||
        $id_tipo_atencion <= 0 ||
        $id_medico <= 0 ||
        count($ids_examenes_limpios) == 0
    ){
        $error = "Complete todos los campos obligatorios y seleccione opciones válidas.";
    } else {

        $condicion = buscarNombre($condiciones, 'id_condicion', 'nombre_condicion', $id_condicion);
        $servicio_solicitante = buscarNombre($servicios, 'id_servicio', 'nombre_servicio', $id_servicio);
        $tipo_atencion = buscarNombre($tipos_atencion, 'id_tipo_atencion', 'nombre_tipo_atencion', $id_tipo_atencion);
        $medico_turno = buscarNombre($medicos, 'id_medico', 'nombre_medico', $id_medico);

        $nombres_examenes = [];
        $codigos_cpt = [];

        foreach($ids_examenes_limpios as $idEx){
            $nombreExamen = buscarNombre($examenes, 'id_examen', 'nombre_examen', $idEx);
            $codigoCPT = buscarCPT($examenes, $idEx);

            if($nombreExamen != ''){
                $nombres_examenes[] = $nombreExamen;
            }

            if($codigoCPT != ''){
                $codigos_cpt[] = $codigoCPT;
            }
        }

        $examen_solicitado = implode(', ', $nombres_examenes);
        $codigo_cpt = implode(', ', $codigos_cpt);
        $id_examen_principal = $ids_examenes_limpios[0];

        $sql = "INSERT INTO tomografias (
                    historia_clinica,
                    dni,
                    fecha,
                    apellidos,
                    nombres,
                    sexo,
                    condicion,
                    servicio_solicitante,
                    medico_turno,
                    examen_solicitado,
                    tipo_atencion,
                    diagnostico,
                    hora_examen,
                    id_condicion,
                    id_servicio,
                    id_examen,
                    id_tipo_atencion,
                    id_medico,
                    codigo_cpt
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if(!$stmt){
            $error = "Error en la consulta: " . $conn->error;
        } else {

            $stmt->bind_param(
                "sssssssssssssiiiiis",
                $historia_clinica,
                $dni,
                $fecha,
                $apellidos,
                $nombres,
                $sexo,
                $condicion,
                $servicio_solicitante,
                $medico_turno,
                $examen_solicitado,
                $tipo_atencion,
                $diagnostico,
                $hora_examen,
                $id_condicion,
                $id_servicio,
                $id_examen_principal,
                $id_tipo_atencion,
                $id_medico,
                $codigo_cpt
            );

            if($stmt->execute()){
                $id_tomografia = $conn->insert_id;

                foreach($ids_examenes_limpios as $idExamen){
                    $stmtEx = $conn->prepare("INSERT INTO tomografia_examenes (id_tomografia, id_examen) VALUES (?, ?)");
                    $stmtEx->bind_param("ii", $id_tomografia, $idExamen);
                    $stmtEx->execute();
                    $stmtEx->close();
                }

                $mensaje = "Tomografía registrada correctamente.";
            } else {
                $error = "Error al registrar la tomografía: " . $stmt->error;
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

    <link rel="stylesheet" href="../css/registrar.css?v=50">
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

        <?php if($mensaje != ''): ?>
            <div class="alerta exito"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if($error != ''): ?>
            <div class="alerta error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="form-registro" autocomplete="off">

            <div class="grid-form">

                <div class="campo">
                    <label>H.C.</label>
                    <input type="text" name="historia_clinica" class="campo-enter" placeholder="Ingrese H.C." required>
                </div>

                <div class="campo">
                    <label>DNI</label>
                    <input type="text" name="dni" class="campo-enter" placeholder="Ingrese DNI" maxlength="8" required>
                </div>

                <div class="campo">
                    <label>Fecha</label>
                    <input type="date" name="fecha" class="campo-enter" required>
                </div>

                <div class="campo">
                    <label>Sexo</label>
                    <input 
                        type="text" 
                        name="sexo" 
                        id="sexo" 
                        class="campo-enter" 
                        placeholder="M = Masculino / F = Femenino" 
                        maxlength="9"
                        required
                    >
                </div>

                <div class="campo">
                    <label>Apellidos</label>
                    <input type="text" name="apellidos" class="campo-enter" placeholder="Ingrese Apellidos" required>
                </div>

                <div class="campo">
                    <label>Nombres</label>
                    <input type="text" name="nombres" class="campo-enter" placeholder="Ingrese Nombres" required>
                </div>

                <div class="campo">
                    <label>Condición</label>
                    <div class="combo">
                        <input type="text" class="combo-input campo-enter" placeholder="Busque o seleccione condición" data-hidden="id_condicion">
                        <input type="hidden" name="id_condicion" id="id_condicion">

                        <div class="combo-list">
                            <?php foreach($condiciones as $c): ?>
                                <div class="combo-item" data-id="<?php echo $c['id_condicion']; ?>">
                                    <?php echo htmlspecialchars($c['nombre_condicion']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="campo">
                    <label>Servicio solicitante</label>
                    <div class="combo">
                        <input type="text" class="combo-input campo-enter" placeholder="Busque o seleccione servicio" data-hidden="id_servicio">
                        <input type="hidden" name="id_servicio" id="id_servicio">

                        <div class="combo-list">
                            <?php foreach($servicios as $s): ?>
                                <div class="combo-item" data-id="<?php echo $s['id_servicio']; ?>">
                                    <?php echo htmlspecialchars($s['nombre_servicio']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="campo">
                    <label>Médico de turno</label>
                    <div class="combo">
                        <input type="text" class="combo-input campo-enter" placeholder="Busque o seleccione médico" data-hidden="id_medico">
                        <input type="hidden" name="id_medico" id="id_medico">

                        <div class="combo-list">
                            <?php foreach($medicos as $m): ?>
                                <div class="combo-item" data-id="<?php echo $m['id_medico']; ?>">
                                    <?php echo htmlspecialchars($m['nombre_medico']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="campo">
                    <label>Tipo de atención</label>
                    <div class="combo">
                        <input type="text" class="combo-input campo-enter" placeholder="Busque o seleccione tipo de atención" data-hidden="id_tipo_atencion">
                        <input type="hidden" name="id_tipo_atencion" id="id_tipo_atencion">

                        <div class="combo-list">
                            <?php foreach($tipos_atencion as $t): ?>
                                <div class="combo-item" data-id="<?php echo $t['id_tipo_atencion']; ?>">
                                    <?php echo htmlspecialchars($t['nombre_tipo_atencion']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="campo campo-examenes">
                    <label>Exámenes solicitados (máx. 3)</label>

                    <div class="examenes-grid">

                        <div class="combo">
                            <input type="text" class="combo-input campo-enter" placeholder="Busque o seleccione examen 1" data-hidden="id_examen_1">
                            <input type="hidden" name="id_examen[]" id="id_examen_1">

                            <div class="combo-list">
                                <?php foreach($examenes as $e): ?>
                                    <div class="combo-item" data-id="<?php echo $e['id_examen']; ?>">
                                        <?php 
                                            echo htmlspecialchars($e['nombre_examen']); 
                                            if(!empty($e['codigo_cpt'])){
                                                echo " - CPT: " . htmlspecialchars($e['codigo_cpt']);
                                            }
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="combo">
                            <input type="text" class="combo-input campo-enter" placeholder="Busque o seleccione examen 2" data-hidden="id_examen_2">
                            <input type="hidden" name="id_examen[]" id="id_examen_2">

                            <div class="combo-list">
                                <?php foreach($examenes as $e): ?>
                                    <div class="combo-item" data-id="<?php echo $e['id_examen']; ?>">
                                        <?php 
                                            echo htmlspecialchars($e['nombre_examen']); 
                                            if(!empty($e['codigo_cpt'])){
                                                echo " - CPT: " . htmlspecialchars($e['codigo_cpt']);
                                            }
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="combo">
                            <input type="text" class="combo-input campo-enter" placeholder="Busque o seleccione examen 3" data-hidden="id_examen_3">
                            <input type="hidden" name="id_examen[]" id="id_examen_3">

                            <div class="combo-list">
                                <?php foreach($examenes as $e): ?>
                                    <div class="combo-item" data-id="<?php echo $e['id_examen']; ?>">
                                        <?php 
                                            echo htmlspecialchars($e['nombre_examen']); 
                                            if(!empty($e['codigo_cpt'])){
                                                echo " - CPT: " . htmlspecialchars($e['codigo_cpt']);
                                            }
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="campo campo-diagnostico">
                    <label>Diagnóstico</label>
                    <textarea name="diagnostico" placeholder="Ingrese diagnóstico"></textarea>
                </div>

                <div class="campo">
                    <label>Hora de examen</label>
                    <input type="time" name="hora_examen" class="campo-enter">
                </div>

            </div>

            <div class="botones">
                <button type="reset" class="btn-limpiar">Limpiar</button>
                <button type="submit" name="registrar" id="btnRegistrar" class="btn-registrar">
                    Registrar Tomografía
                </button>
            </div>

        </form>

    </section>

</main>

<script>
document.addEventListener("DOMContentLoaded", function(){

    const combos = document.querySelectorAll(".combo");

    combos.forEach(combo => {
        const input = combo.querySelector(".combo-input");
        const hiddenId = input.dataset.hidden;
        const hidden = document.getElementById(hiddenId);
        const lista = combo.querySelector(".combo-list");
        const items = Array.from(combo.querySelectorAll(".combo-item"));

        let indiceActivo = -1;

        function abrirLista(){
            lista.style.display = "block";
            filtrar();
        }

        function cerrarLista(){
            setTimeout(() => {
                lista.style.display = "none";
                limpiarActivo();
            }, 150);
        }

        function limpiarActivo(){
            items.forEach(item => item.classList.remove("activo"));
            indiceActivo = -1;
        }

        function itemsVisibles(){
            return items.filter(item => item.style.display !== "none");
        }

        function marcarActivo(index){
            const visibles = itemsVisibles();

            if(visibles.length === 0) return;

            visibles.forEach(item => item.classList.remove("activo"));

            if(index < 0) index = visibles.length - 1;
            if(index >= visibles.length) index = 0;

            indiceActivo = index;

            visibles[indiceActivo].classList.add("activo");
            visibles[indiceActivo].scrollIntoView({
                block: "nearest"
            });
        }

        function filtrar(){
            const texto = input.value.toLowerCase().trim();
            let encontrados = 0;

            items.forEach(item => {
                const contenido = item.textContent.toLowerCase();

                if(contenido.includes(texto)){
                    item.style.display = "block";
                    encontrados++;
                } else {
                    item.style.display = "none";
                }

                item.classList.remove("activo");
            });

            indiceActivo = -1;

            if(encontrados === 0){
                lista.style.display = "none";
            } else {
                lista.style.display = "block";
            }
        }

        function seleccionarItem(item){
            input.value = item.textContent.trim();
            hidden.value = item.dataset.id;
            lista.style.display = "none";
            limpiarActivo();
        }

        input.addEventListener("focus", abrirLista);
        input.addEventListener("click", abrirLista);

        input.addEventListener("input", function(){
            hidden.value = "";
            abrirLista();
            filtrar();
        });

        input.addEventListener("blur", cerrarLista);

        input.addEventListener("keydown", function(e){

            if(e.ctrlKey && e.key === "Enter"){
                e.preventDefault();
                document.getElementById("btnRegistrar").focus();
                return;
            }

            if(e.key === "ArrowDown"){
                e.preventDefault();
                abrirLista();
                marcarActivo(indiceActivo + 1);
                return;
            }

            if(e.key === "ArrowUp"){
                e.preventDefault();
                abrirLista();
                marcarActivo(indiceActivo - 1);
                return;
            }

            if(e.key === "Enter"){
                const visiblesActuales = itemsVisibles();

                if(lista.style.display === "block" && indiceActivo >= 0 && visiblesActuales[indiceActivo]){
                    e.preventDefault();
                    seleccionarItem(visiblesActuales[indiceActivo]);
                    pasarAlSiguiente(input);
                    return;
                }

                const coincidencia = items.find(item => 
                    item.textContent.trim().toLowerCase() === input.value.trim().toLowerCase()
                );

                if(coincidencia){
                    e.preventDefault();
                    seleccionarItem(coincidencia);
                    pasarAlSiguiente(input);
                    return;
                }

                e.preventDefault();
            }
        });

        items.forEach(item => {
            item.addEventListener("mousedown", function(e){
                e.preventDefault();
                seleccionarItem(this);
            });
        });
    });

    function normalizarSexo(){
    const sexo = document.getElementById("sexo");

    if(!sexo) return;

    const valor = sexo.value.trim();

    if(valor === "f" || valor === "F"){
        sexo.value = "Femenino";
    } else if(valor === "m" || valor === "M"){
        sexo.value = "Masculino";
    }
}
const campoSexo = document.getElementById("sexo");

if(campoSexo){
    campoSexo.addEventListener("keyup", function(){
        const valor = campoSexo.value.trim();

        if(valor === "f" || valor === "F"){
            campoSexo.value = "Femenino";
        }

        if(valor === "m" || valor === "M"){
            campoSexo.value = "Masculino";
        }
    });
}

    function pasarAlSiguiente(campoActual){
        const campos = Array.from(document.querySelectorAll(".campo-enter"));
        const index = campos.indexOf(campoActual);

        if(index >= 0 && campos[index + 1]){
            campos[index + 1].focus();
        }
    }

    const campos = document.querySelectorAll(".campo-enter");

    campos.forEach(campo => {
        campo.addEventListener("keydown", function(e){

            if(e.ctrlKey && e.key === "Enter"){
                e.preventDefault();
                document.getElementById("btnRegistrar").focus();
                return;
            }

            if(campo.classList.contains("combo-input")){
                return;
            }

            if(e.key === "Enter"){
                e.preventDefault();

                if(campo.id === "sexo"){
                    normalizarSexo();
                }

                pasarAlSiguiente(campo);
            }
        });

        campo.addEventListener("blur", function(){
            if(campo.id === "sexo"){
                normalizarSexo();
            }
        });
    });

    const form = document.querySelector(".form-registro");

    form.addEventListener("submit", function(e){

        normalizarSexo();

        const idCondicion = document.getElementById("id_condicion").value;
        const idServicio = document.getElementById("id_servicio").value;
        const idMedico = document.getElementById("id_medico").value;
        const idTipo = document.getElementById("id_tipo_atencion").value;
        const idExamen1 = document.getElementById("id_examen_1").value;

        if(idCondicion === ""){
            alert("Seleccione una condición válida.");
            e.preventDefault();
            return;
        }

        if(idServicio === ""){
            alert("Seleccione un servicio solicitante válido.");
            e.preventDefault();
            return;
        }

        if(idMedico === ""){
            alert("Seleccione un médico de turno válido.");
            e.preventDefault();
            return;
        }

        if(idTipo === ""){
            alert("Seleccione un tipo de atención válido.");
            e.preventDefault();
            return;
        }

        if(idExamen1 === ""){
            alert("Seleccione al menos un examen solicitado.");
            e.preventDefault();
            return;
        }
    });

});
</script>

</body>
</html>