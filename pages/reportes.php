<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema Tomografía</title>
    <link rel="stylesheet" href="../css/reportes.css">
</head>
<body>

<div class="reportes-container">

    <a href="dashboard.php" class="btn-volver">← Volver al menú</a>

    <h1>Módulo de Reportes</h1>

    <p class="subtitulo">
        Generación de informes estadísticos del Área de Tomografía.
    </p>

    <div class="reportes-grid">

        <div class="reporte-card borde-azul">
            <h2>Reporte por Servicio</h2>
            <p>
                Muestra la producción mensual por servicio solicitante y tipo de atención:
                ambulatorio, hospitalaria, emergencia, periférica y particular.
            </p>

            <a href="reporte_servicio.php" class="btn-reporte azul">
                Generar reporte
            </a>
        </div>

        <div class="reporte-card borde-verde">
            <h2>Reporte por Tomografía</h2>
            <p>
                Muestra la producción mensual por examen tomográfico solicitado y condición:
                SIS, particular, convenio, crédito, contado, entre otros.
            </p>

            <a href="reporte_tomografia.php" class="btn-reporte verde">
                Generar reporte
            </a>
        </div>

        <div class="reporte-card borde-morado">
            <h2>Reporte CPT-Code</h2>
            <p>
                Genera el informe de procedimientos tomográficos según la tabla de
                codificación CPT-Code.
            </p>

            <a href="reporte_cpt.php" class="btn-reporte morado">
                Generar reporte
            </a>
        </div>

        <div class="reporte-card borde-naranja">
            <h2>Reporte Económico</h2>
            <p>
                Muestra montos recaudados por fecha, condición, boleta, convenio
                y tipo de atención.
            </p>

            <a href="reporte_economico.php" class="btn-reporte naranja">
                Generar reporte
            </a>
        </div>

    </div>

</div>

</body>
</html>