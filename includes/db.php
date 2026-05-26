<?php
$host = "localhost";
$user = "root";         // tu usuario de phpMyAdmin
$password = "";         // tu contraseña
$dbname = "sistema_tomografia";

$conn = new mysqli($host, $user, $password, $dbname);

if($conn->connect_error){
    die("Conexión fallida: " . $conn->connect_error);
}
?>