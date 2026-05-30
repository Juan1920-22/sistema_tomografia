<?php
session_start();
include('../includes/db.php');

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$mensaje = '';
$error = '';

$tablas = [
    'condicion' => [
        'tabla' => 'condiciones_pago',
        'id' => 'id_condicion',
        'nombre' => 'nombre_condicion',
        'titulo' => 'Condiciones de pago'
    ],
    'servicio' => [
        'tabla' => 'servicios_solicitantes',
        'id' => 'id_servicio',
        'nombre' => 'nombre_servicio',
        'titulo' => 'Servicios solicitantes'
    ],
    'tipo_atencion' => [
        'tabla' => 'tipos_atencion',
        'id' => 'id_tipo_atencion',
        'nombre' => 'nombre_tipo_atencion',
        'titulo' => 'Tipos de atención'
    ],
    'medico' => [
        'tabla' => 'medicos_turno',
        'id' => 'id_medico',
        'nombre' => 'nombre_medico',
        'titulo' => 'Médicos de turno'
    ],
    'examen' => [
        'tabla' => 'examenes_solicitados',
        'id' => 'id_examen',
        'nombre' => 'nombre_examen',
        'titulo' => 'Exámenes solicitados'
    ]
];

/* GUARDAR NUEVO REGISTRO */
if(isset($_POST['guardar'])){
    $tipo = $_POST['tipo'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $codigo_cpt = trim($_POST['codigo_cpt'] ?? '');

    if($tipo == '' || $nombre == ''){
        $error = "Debe seleccionar un tipo e ingresar un nombre.";
    } elseif(!isset($tablas[$tipo])){
        $error = "Tipo no válido.";
    } else {
        $info = $tablas[$tipo];

        if($tipo == 'examen'){
            $sql = "INSERT INTO {$info['tabla']} ({$info['nombre']}, codigo_cpt, descripcion) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $nombre, $codigo_cpt, $descripcion);
        } else {
            $sql = "INSERT INTO {$info['tabla']} ({$info['nombre']}, descripcion) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $nombre, $descripcion);
        }

        if($stmt->execute()){
            $mensaje = "Registro guardado correctamente.";
        } else {
            $error = "Error al guardar el registro.";
        }

        $stmt->close();
    }
}

/* ACTUALIZAR REGISTRO */
if(isset($_POST['actualizar'])){
    $tipo = $_POST['tipo_editar'] ?? '';
    $id = intval($_POST['id_editar'] ?? 0);
    $nombre = trim($_POST['nombre_editar'] ?? '');
    $descripcion = trim($_POST['descripcion_editar'] ?? '');
    $codigo_cpt = trim($_POST['codigo_cpt_editar'] ?? '');

    if($tipo == '' || $id <= 0 || $nombre == ''){
        $error = "Datos incompletos para actualizar.";
    } elseif(!isset($tablas[$tipo])){
        $error = "Tipo no válido.";
    } else {
        $info = $tablas[$tipo];

        if($tipo == 'examen'){
            $sql = "UPDATE {$info['tabla']} SET {$info['nombre']} = ?, codigo_cpt = ?, descripcion = ? WHERE {$info['id']} = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $nombre, $codigo_cpt, $descripcion, $id);
        } else {
            $sql = "UPDATE {$info['tabla']} SET {$info['nombre']} = ?, descripcion = ? WHERE {$info['id']} = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $nombre, $descripcion, $id);
        }

        if($stmt->execute()){
            $mensaje = "Registro actualizado correctamente.";
        } else {
            $error = "Error al actualizar.";
        }

        $stmt->close();
    }
}

/* ELIMINAR REGISTRO */
if(isset($_GET['eliminar']) && isset($_GET['tipo'])){
    $tipo = $_GET['tipo'];
    $id = intval($_GET['eliminar']);

    if(isset($tablas[$tipo]) && $id > 0){
        $info = $tablas[$tipo];

        $sql = "DELETE FROM {$info['tabla']} WHERE {$info['id']} = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if($stmt->execute()){
            $mensaje = "Registro eliminado correctamente.";
        } else {
            $error = "No se pudo eliminar. Puede estar siendo usado en una tomografía.";
        }

        $stmt->close();
    }
}

/* FILTRO DE LISTADO */
$filtro = $_GET['filtro'] ?? 'condicion';

if(!isset($tablas[$filtro])){
    $filtro = 'condicion';
}

$infoFiltro = $tablas[$filtro];

if($filtro == 'examen'){
    $sqlListado = "SELECT {$infoFiltro['id']} AS id, {$infoFiltro['nombre']} AS nombre, codigo_cpt, descripcion, estado 
                   FROM {$infoFiltro['tabla']}
                   ORDER BY {$infoFiltro['id']} DESC";
} else {
    $sqlListado = "SELECT {$infoFiltro['id']} AS id, {$infoFiltro['nombre']} AS nombre, '' AS codigo_cpt, descripcion, estado 
                   FROM {$infoFiltro['tabla']}
                   ORDER BY {$infoFiltro['id']} DESC";
}

$resultado = $conn->query($sqlListado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenimiento - Sistema Tomografía</title>
    <link rel="stylesheet" href="../css/mantenimiento.css">
</head>
<body>

<header class="topbar">
    <div class="topbar-title">HOSPITAL SAN JOSÉ DE CHINCHA</div>

    <nav class="topbar-menu">
        <a href="dashboard.php">Inicio</a>
        <a href="registrar.php">Registrar</a>
        <a href="historial_tomografia.php">Historial</a>
        <a href="reportes.php">Reportes</a>
        <a href="mantenimiento.php" class="active">Mantenimiento</a>
        <a href="logout.php" class="salir">Salir</a>
    </nav>
</header>

<section class="hero">
    <div class="hero-overlay">
        <span>Área de Diagnóstico por Imágenes</span>
        <h1>Mantenimiento del Sistema</h1>
        <p>Administre condiciones de pago, servicios, tipos de atención, médicos y exámenes utilizados en el registro de tomografías.</p>
    </div>
</section>

<main class="contenido">

    <section class="panel panel-formulario">

        <div class="panel-header">
            <a href="dashboard.php" class="btn-volver">← Volver al menú</a>
            <h2>Mantenimiento del sistema</h2>
        </div>

        <?php if($mensaje != ''): ?>
            <div class="alerta exito"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if($error != ''): ?>
            <div class="alerta error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="form-mantenimiento">

            <select name="tipo" id="tipo" required onchange="mostrarCPT()">
                <option value="">Seleccione tipo</option>
                <option value="condicion">Condición de pago</option>
                <option value="servicio">Servicio solicitante</option>
                <option value="tipo_atencion">Tipo de atención</option>
                <option value="medico">Médico de turno</option>
                <option value="examen">Examen solicitado</option>
                
            </select>

            <input type="text" name="nombre" placeholder="Ingrese nombre" required>

            <input 
                type="text" 
                name="codigo_cpt" 
                id="codigo_cpt" 
                placeholder="Ingrese código CPT / código de procedimiento"
                style="display:none;"
            >

            <textarea name="descripcion" placeholder="Ingrese descripción"></textarea>

            <button type="submit" name="guardar">Guardar registro</button>

        </form>

    </section>

    <section class="panel listado-panel">

        <div class="listado-header">
            <h2><?php echo $infoFiltro['titulo']; ?></h2>

            <form method="GET" class="filtro-form">
                <select name="filtro" onchange="this.form.submit()">
                    <option value="condicion" <?php if($filtro=='condicion') echo 'selected'; ?>>Condiciones de pago</option>
                    <option value="servicio" <?php if($filtro=='servicio') echo 'selected'; ?>>Servicios solicitantes</option>
                    <option value="tipo_atencion" <?php if($filtro=='tipo_atencion') echo 'selected'; ?>>Tipos de atención</option>
                    <option value="medico" <?php if($filtro=='medico') echo 'selected'; ?>>Médicos de turno</option>
                    <option value="examen" <?php if($filtro=='examen') echo 'selected'; ?>>Exámenes solicitados</option>
                </select>
            </form>
        </div>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <?php if($filtro == 'examen'): ?>
                            <th>Código CPT</th>
                        <?php endif; ?>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if($resultado && $resultado->num_rows > 0): ?>
                        <?php while($fila = $resultado->fetch_assoc()): ?>
                            <tr>
                                <form method="POST">
                                    <td>
                                        <?php echo $fila['id']; ?>
                                        <input type="hidden" name="id_editar" value="<?php echo $fila['id']; ?>">
                                        <input type="hidden" name="tipo_editar" value="<?php echo $filtro; ?>">
                                    </td>

                                    <td>
                                        <input type="text" name="nombre_editar" value="<?php echo htmlspecialchars($fila['nombre']); ?>">
                                    </td>

                                    <?php if($filtro == 'examen'): ?>
                                        <td>
                                            <input type="text" name="codigo_cpt_editar" value="<?php echo htmlspecialchars($fila['codigo_cpt']); ?>">
                                        </td>
                                    <?php else: ?>
                                        <input type="hidden" name="codigo_cpt_editar" value="">
                                    <?php endif; ?>

                                    <td>
                                        <input type="text" name="descripcion_editar" value="<?php echo htmlspecialchars($fila['descripcion']); ?>">
                                    </td>

                                    <td>
                                        <span class="estado-activo"><?php echo htmlspecialchars($fila['estado']); ?></span>
                                    </td>

                                    <td class="acciones">
                                        <button type="submit" name="actualizar" class="btn-editar">Guardar</button>

                                        <a 
                                            href="mantenimiento.php?filtro=<?php echo $filtro; ?>&tipo=<?php echo $filtro; ?>&eliminar=<?php echo $fila['id']; ?>" 
                                            class="btn-eliminar"
                                            onclick="return confirm('¿Seguro que desea eliminar este registro?');"
                                        >
                                            Eliminar
                                        </a>
                                    </td>
                                </form>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="sin-registros">No hay registros registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

</main>

<script>
function mostrarCPT() {
    const tipo = document.getElementById('tipo').value;
    const campoCPT = document.getElementById('codigo_cpt');

    if(tipo === 'examen'){
        campoCPT.style.display = 'block';
    } else {
        campoCPT.style.display = 'none';
        campoCPT.value = '';
    }
}
</script>

</body>
</html>