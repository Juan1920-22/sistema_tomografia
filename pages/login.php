<?php
session_start();
include('../includes/db.php');

$error = '';

if(isset($_POST['login'])){
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    $query = "SELECT * FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows == 1){
        $user = $result->fetch_assoc();
        if(hash('sha256', $password) === $user['password']){
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_rol'] = $user['rol'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Contraseña incorrecta";
        }
    } else {
        $error = "Usuario no encontrado";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Sistema Tomografía</title>
<link rel="stylesheet" href="../css/login.css">
<link href="https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <!-- Sección izquierda -->
    <div class="login-left">
        <h1>¡Bienvenido!</h1>
        <p>Ingrese sus datos para acceder al sistema de Tomografía del Hospital San José de Chincha.</p>
        <img src="../img/imagen1.png" alt="Logo Hospital" class="login-logo">
    </div>

    <!-- Sección derecha: formulario -->
    <div class="login-right">
        <h2>Sistema de Tomografía</h2>
        <p class="hospital-name">HOSPITAL SAN JOSÉ DE CHINCHA</p>

        <form method="POST" class="login-form">
            <?php if($error !== ''): ?>
                <div class="login-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <input type="text" name="usuario" placeholder="Ingrese su usuario" required>
            <input type="password" name="password" placeholder="Ingrese su contraseña" required>
            <button type="submit" name="login">Ingresar al sistema</button>
        </form>

        <p class="login-footer">EsSalud · Sistema Institucional</p>
    </div>
</div>

</body>
</html>