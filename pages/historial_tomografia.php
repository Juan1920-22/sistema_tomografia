<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

// Conexión a la base de datos
$conn = new mysqli("localhost", "root", "", "sistema_tomografia");
if($conn->connect_error) die("Conexión fallida: ".$conn->connect_error);

$result = $conn->query("SELECT * FROM tomografias ORDER BY fecha DESC");
$total_registros = $result->num_rows;
$monto_total = 0;
while($row = $result->fetch_assoc()){
    $monto_total += $row['monto'] ?? 0;
}
$result->data_seek(0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Historial de Tomografías</title>
<link rel="stylesheet" href="../css/historial_tomografia.css">
</head>
<body>

<main class="main-content">
    <a href="dashboard.php" class="btn-volver">← Volver al menú</a>
    <h1>Historial de Tomografías</h1>

    <form class="filtros-form" method="POST">
        <div class="filtros">
            <input type="date" name="fecha_inicio" placeholder="Fecha inicio">
            <input type="date" name="fecha_fin" placeholder="Fecha fin">
            <input type="text" name="busqueda" placeholder="Buscar paciente o H.C">
            <select name="medico">
                <option>Todos los médicos</option>
            </select>
            <select name="servicio">
                <option>Todos los servicios</option>
            </select>
            <select name="condicion">
                <option>Todas</option>
            </select>
            <select name="tipo_atencion">
                <option>Todos</option>
            </select>
            <select name="examen">
                <option>Todos los exámenes</option>
            </select>
        </div>
        <div class="botones">
            <button type="submit" class="btn-rojo">Descargar PDF</button>
            <button type="submit" class="btn-morado">Imprimir</button>
            <button type="submit" class="btn-azul">Buscar</button>
            <button type="reset" class="btn-verde">Limpiar</button>
        </div>
    </form>

    <p>Registros encontrados: <?php echo $total_registros; ?> | Monto total: S/ <?php echo number_format($monto_total,2); ?></p>

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
                    <th>Examen</th>
                    <th>Diagnóstico</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['historia_clinica']; ?></td>
                    <td><?php echo $row['dni']; ?></td>
                    <td><?php echo $row['fecha']; ?></td>
                    <td><?php echo $row['apellidos'] . ' ' . $row['nombres']; ?></td>
                    <td><?php echo $row['sexo']; ?></td>
                    <td><?php echo $row['condicion']; ?></td>
                    <td><?php echo $row['servicio_solicitante']; ?></td>
                    <td><?php echo $row['medico_turno']; ?></td>
                    <td><?php echo $row['tipo_atencion']; ?></td>
                    <td><?php echo $row['examen_solicitado']; ?></td>
                    <td><?php echo $row['diagnostico']; ?></td>
                    <td>
                        <button class="btn-editar">Editar</button>
                        <button class="btn-eliminar">Eliminar</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>