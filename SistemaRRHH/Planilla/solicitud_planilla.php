<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$idUsuario = (int)($_SESSION['idUsuario'] ?? 0);

if ($idUsuario <= 0) {
    die("Sesión inválida.");
}

/* ============================
   EMPLEADO ASOCIADO AL USUARIO
============================ */
$stmtEmp = $conexion->prepare("
    SELECT 
        e.idEmpleado,
        e.nombre,
        e.apellidos
    FROM empleado e
    WHERE e.Usuario_idUsuario = ?
    LIMIT 1
");

if (!$stmtEmp) {
    die("Error preparando empleado: " . $conexion->error);
}

$stmtEmp->bind_param("i", $idUsuario);
$stmtEmp->execute();
$empleado = $stmtEmp->get_result()->fetch_assoc();

if (!$empleado) {
    die("No se encontró un empleado asociado al usuario actual.");
}

/* ============================
   JEFATURAS
============================ */
$jefaturas = $conexion->query("
    SELECT 
        u.idUsuario,
        u.Nombre,
        u.Apellido,
        u.Apellido2
    FROM usuario u
    WHERE u.Estado = b'1'
      AND u.idRoles = 1
    ORDER BY u.Nombre, u.Apellido, u.Apellido2
");

if (!$jefaturas) {
    die("Error consultando jefaturas: " . $conexion->error);
}

/* ============================
   RRHH / ADMIN
============================ */
$rrhh = $conexion->query("
    SELECT 
        u.idUsuario,
        u.Nombre,
        u.Apellido,
        u.Apellido2
    FROM usuario u
    WHERE u.Estado = b'1'
      AND u.idRoles = 2
    ORDER BY u.Nombre, u.Apellido, u.Apellido2
");

if (!$rrhh) {
    die("Error consultando RRHH/Admin: " . $conexion->error);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Planilla</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        body{
            background:#f4f6f9;
        }
        .card{
            border:none;
            border-radius:14px;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Nueva Solicitud de Planilla</h4>
        </div>
        <div class="card-body">

            <form action="guardar_solicitud_planilla.php" method="POST">

                <div class="mb-3">
                    <label class="form-label">Empleado</label>
                    <input
                        type="text"
                        class="form-control"
                        value="<?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellidos']); ?>"
                        readonly
                    >
                    <input type="hidden" name="idEmpleado" value="<?php echo (int)$empleado['idEmpleado']; ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Tipo de solicitud</label>
                    <select name="tipo_solicitud" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="Horas Extra">Horas Extra</option>
                        <option value="Bonificacion">Bonificación</option>
                        <option value="Deduccion">Deducción</option>
                        <option value="Ajuste Salarial">Ajuste Salarial</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Monto</label>
                    <input
                        type="number"
                        name="monto"
                        class="form-control"
                        min="0.01"
                        step="0.01"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea
                        name="descripcion"
                        class="form-control"
                        rows="4"
                        maxlength="255"
                        required
                    ></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Jefatura</label>
                    <select name="idJefatura" class="form-select" required>
                        <option value="">Seleccione</option>
                        <?php while ($j = $jefaturas->fetch_assoc()) { ?>
                            <option value="<?php echo (int)$j['idUsuario']; ?>">
                                <?php echo htmlspecialchars(trim($j['Nombre'] . ' ' . $j['Apellido'] . ' ' . $j['Apellido2'])); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Usuario RRHH / Admin</label>
                    <select name="idRRHH" class="form-select" required>
                        <option value="">Seleccione</option>
                        <?php while ($r = $rrhh->fetch_assoc()) { ?>
                            <option value="<?php echo (int)$r['idUsuario']; ?>">
                                <?php echo htmlspecialchars(trim($r['Nombre'] . ' ' . $r['Apellido'] . ' ' . $r['Apellido2'])); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-success">Guardar Solicitud</button>
                <a href="../index.php" class="btn btn-secondary">Cancelar</a>
            </form>

        </div>
    </div>
</div>
</body>
</html>