<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}
$usuario = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Sistema Tomografía</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
<header class="topbar">
    <div class="logo">HOSPITAL SAN JOSÉ DE CHINCHA</div>
    <nav>
        <a href="dashboard.php">Inicio</a>
        <a href="registrar.php">Registrar</a>
        <a href="historial_tomografia.php">Historial</a>
        <a href="reportes.php">Reportes</a>
        <a href="mantenimiento.php">Mantenimiento</a>
        <a href="logout.php" class="btn-salir">Salir</a>
    </nav>
</header>

<section class="hero">
    <img src="../img/images2.jfif" alt="Hospital" class="hero-img">
    <div class="hero-text">
        <h1>Sistema de Gestión de Tomografía</h1>
        <p>Optimiza el registro, consulta y seguimiento de las atenciones tomográficas del Área de Diagnóstico por Imágenes.</p>
    </div>
</section>

<section class="modulos">
    <a href="registrar.php" class="modulo registrar">
        <h3>Registrar</h3>
        <p>Ingresar datos del paciente, examen, fecha y observaciones.</p>
        <span>➜</span>
    </a>
    <a href="historial_tomografia.php" class="modulo historial">
        <h3>Historial</h3>
        <p>Buscar, consultar y editar los registros almacenados.</p>
        <span>➜</span>
    </a>
    <a href="reportes.php" class="modulo reportes">
        <h3>Reportes</h3>
        <p>Generar informes estadísticos y reportes por servicio, por Tomografía, por CPT-Code y económico</p>
        <span>➜</span>
    </a>
    <a href="mantenimiento.php" class="modulo mantenimiento">
        <h3>Mantenimiento</h3>
        <p>Administrar configuraciones, usuarios y datos generales del sistema.</p>
        <span>➜</span>
    </a>
</section>
</body>
</html>