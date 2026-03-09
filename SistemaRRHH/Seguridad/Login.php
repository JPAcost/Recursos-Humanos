<?php
session_start();
include("../config/conexion.php");

/* =========================
   FUNCIÓN: LIMPIAR
========================= */
function clean($v){
    return trim($v ?? '');
}

/* =========================
   REGISTRO
========================= */
if (isset($_POST['register'])) {

    $nombre    = clean($_POST['nombre']);
    $apellido  = clean($_POST['apellido']);
    $apellido2 = clean($_POST['apellido2']);
    $email     = clean($_POST['email']);
    $passPlain = $_POST['password'] ?? '';

    if ($nombre === '' || $apellido === '' || $apellido2 === '' || $email === '' || $passPlain === '') {
        $error = "Todos los campos son obligatorios.";
    } else {

        $password = password_hash($passPlain, PASSWORD_DEFAULT);

        //  Rol por defecto: USUARIO 
        $rolDefault = 4;

        // Verificar correo (PREPARED)
        $stmtV = $conexion->prepare("SELECT 1 FROM usuario WHERE Email = ? LIMIT 1");
        $stmtV->bind_param("s", $email);
        $stmtV->execute();
        $existe = $stmtV->get_result()->num_rows > 0;

        if ($existe) {
            $error = "El correo ya está registrado";
        } else {

            $stmtI = $conexion->prepare("
                INSERT INTO usuario 
                (Nombre, Apellido, Apellido2, Email, Password, idRoles, Fecha_de_creacion, Fecha_modificacion, Estado)
                VALUES 
                (?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)
            ");
            $stmtI->bind_param("sssssi", $nombre, $apellido, $apellido2, $email, $password, $rolDefault);

            if ($stmtI->execute()) {
                $success = "Cuenta creada correctamente. Ahora puedes iniciar sesión.";
            } else {
                $error = "Error al registrar: " . $conexion->error;
            }
        }
    }
}

/* =========================
   LOGIN
========================= */
if (isset($_POST['login'])) {

    $email = clean($_POST['email']);
    $pass  = $_POST['password'] ?? '';

    // 1) Buscar usuario activo por email (PREPARED)
    $stmtU = $conexion->prepare("
        SELECT idUsuario, Nombre, Apellido, Apellido2, Email, Password, idRoles
        FROM usuario
        WHERE Email = ? AND Estado = 1
        LIMIT 1
    ");
    $stmtU->bind_param("s", $email);
    $stmtU->execute();
    $user = $stmtU->get_result()->fetch_assoc();

    if ($user) {

        if (password_verify($pass, $user['Password'])) {

            // 2) Buscar rol + permisos del usuario (PREPARED)
            $idRol = (int)$user['idRoles'];

            $stmtR = $conexion->prepare("SELECT * FROM roles WHERE idRoles = ? AND Estado = 1 LIMIT 1");
            $stmtR->bind_param("i", $idRol);
            $stmtR->execute();
            $rol = $stmtR->get_result()->fetch_assoc();

            if (!$rol) {
                // Rol inválido o desactivado
                session_destroy();
                $error = "Tu rol no es válido o está desactivado. Contacta al administrador.";
            } else {

                //  Datos básicos
                $_SESSION['idUsuario'] = (int)$user['idUsuario'];
                $_SESSION['nombre']    = $user['Nombre'];
                $_SESSION['idRoles']   = $idRol;

                // Nombre del rol (ADMIN/JEFATURA/USUARIO)
             
                $_SESSION['NombreRol'] = $rol['NombreRol'] ?? '';

                //  Cargar permisos dinámicamente a sesión
                $excluir = [
                    'idRoles', 'NombreRol',
                    'Fecha_creacion', 'Usuario_creacion', 'Fecha_modificacion', 'Estado'
                ];

                foreach ($rol as $campo => $valor) {
                    if (!in_array($campo, $excluir)) {
                        $_SESSION[$campo] = (int)$valor; // bits a 0/1
                    }
                }

                header("Location: ../dashboard.php");
                exit();
            }

        } else {
            $error = "Contraseña incorrecta";
        }

    } else {
        $error = "Usuario no encontrado o inactivo";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Romanas Lacost</title>
<link rel="stylesheet" href="../css/login.css">
<script>
document.addEventListener("DOMContentLoaded", function(){

    const btnMostrarLogin = document.querySelector(".btn-login");
    const btnMostrarRegister = document.querySelector(".btn-register");

    const formLogin = document.querySelector(".login-form");
    const formRegister = document.querySelector(".register-form");

    btnMostrarLogin.addEventListener("click", () => {
        formLogin.style.display = "block";
        formRegister.style.display = "none";
    });

    btnMostrarRegister.addEventListener("click", () => {
        formLogin.style.display = "none";
        formRegister.style.display = "block";
    });

});
</script>
</head>

<body>

<?php if(isset($error)){ ?>
    <div style="color:red;text-align:center;margin-bottom:10px;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php } ?>

<?php if(isset($success)){ ?>
    <div style="color:lightgreen;text-align:center;margin-bottom:10px;">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php } ?>

<div class="container-form">

    <!-- PANEL IZQUIERDO -->
    <div class="welcome-back">
        <div class="message">
            <h2>Bienvenido al sistema</h2>
            <p>Selecciona una opción</p>
            <button type="button" class="btn-login">Iniciar Sesión</button>
            <button type="button" class="btn-register">Registrarse</button>
        </div>
    </div>

    <!-- FORM LOGIN -->
    <form class="formulario login-form" method="POST">
        <h2 class="create-account">Iniciar Sesión</h2>

        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Contraseña" required>

        <button type="submit" name="login">Ingresar</button>
    </form>

    <!-- FORM REGISTRO -->
    <form class="formulario register-form" method="POST" style="display:none;">
        <h2 class="create-account">Crear Cuenta</h2>

        <input type="text" name="nombre" placeholder="Nombre" required>
        <input type="text" name="apellido" placeholder="Primer Apellido" required>
        <input type="text" name="apellido2" placeholder="Segundo Apellido" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Contraseña" required>

        <button type="submit" name="register">Registrarse</button>
    </form>

</div>

</body>
</html>