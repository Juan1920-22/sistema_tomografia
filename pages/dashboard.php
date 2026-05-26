<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['user_name'];

// Conexión segura
$conn = new mysqli("localhost", "root", "", "sistema_tomografia");
if($conn->connect_error) die("Conexión fallida: ".$conn->connect_error);

// Tomografías registradas
$total_tomografias = $conn->query("SELECT COUNT(*) as total FROM tomografias")->fetch_assoc()['total'];

// Pacientes activos (DISTINCT historia_clinica)
$total_pacientes = $conn->query("SELECT COUNT(DISTINCT historia_clinica) as total FROM tomografias WHERE condicion='Activo'")->fetch_assoc()['total'];

// Reportes pendientes
$total_reportes = $conn->query("SELECT COUNT(*) as total FROM tomografias WHERE condicion='Pendiente'")->fetch_assoc()['total'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Sistema Tomografía</title>
<link rel="stylesheet" href="../css/dashboard.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="dashboard-wrapper">

    <!-- Menú lateral -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Bienvenido</h2>
            <p>Sistema de Tomografía</p>
        </div>
        <nav class="sidebar-menu">
            <ul>
                <li><a href="registrar.php">Registrar Tomografía</a></li>
                <li><a href="historial_tomografia.php">Historial</a></li>
                <li><a href="reportes.php">Reportes</a></li>
                <li><a href="mantenimiento.php">Mantenimiento</a></li>
                <li><a href="logout.php">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Contenido principal -->
    <main class="main-content">
        <h1>Dashboard</h1>
        <div class="cards">
            <div class="card card-blue">
                <div class="card-icon"><i class="fas fa-file-medical"></i></div>
                <h3>Tomografías Registradas</h3>
                <p><?php echo $total_tomografias; ?></p>
            </div>
            <div class="card card-green">
                <div class="card-icon"><i class="fas fa-user-check"></i></div>
                <h3>Pacientes Activos</h3>
                <p><?php echo $total_pacientes; ?></p>
            </div>
            <div class="card card-red">
                <div class="card-icon"><i class="fas fa-clock"></i></div>
                <h3>Reportes Pendientes</h3>
                <p><?php echo $total_reportes; ?></p>
            </div>
        </div>
    </main>
</div>
</body>
</html>