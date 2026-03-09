<?php
session_start();
require_once("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

function can($permiso){
    return isset($_SESSION[$permiso]) && (int)$_SESSION[$permiso] === 1;
}

if (!can('Vacaciones_solicitar_Empleado')) {
    $_SESSION['mensaje'] = "No tiene permisos para solicitar vacaciones.";
    $_SESSION['tipo'] = "danger";
    header("Location: MisVacaciones.php");
    exit();
}

$idUsuario = (int)($_SESSION['idUsuario'] ?? 0);

$stmtEmp = $conexion->prepare("
    SELECT idEmpleado, nombre, apellidos, Fecha_de_Ingreso
    FROM empleado
    WHERE Usuario_idUsuario = ?
    LIMIT 1
");

if (!$stmtEmp) {
    die("Error al consultar empleado: " . $conexion->error);
}

$stmtEmp->bind_param("i", $idUsuario);
$stmtEmp->execute();
$emp = $stmtEmp->get_result()->fetch_assoc();

if (!$emp) {
    echo "<div class='alert danger' style='margin:20px'>No se encontró el empleado asociado a este usuario.</div>";
    exit();
}

$idEmpleado = (int)$emp['idEmpleado'];

$stmtSaldo = $conexion->prepare("
    SELECT
        FLOOR(TIMESTAMPDIFF(WEEK, Fecha_de_Ingreso, CURDATE()) / 50) * 12 AS dias_acumulados,
        COALESCE((
            SELECT SUM(v.dias_aprobados)
            FROM vacaciones v
            WHERE v.Empleado_idEmpleado = ?
              AND v.estado = 3
        ), 0) AS dias_tomados
    FROM empleado
    WHERE idEmpleado = ?
    LIMIT 1
");

if (!$stmtSaldo) {
    die("Error al calcular saldo: " . $conexion->error);
}

$stmtSaldo->bind_param("ii", $idEmpleado, $idEmpleado);
$stmtSaldo->execute();
$saldo = $stmtSaldo->get_result()->fetch_assoc();

$diasAcumulados  = (int)($saldo['dias_acumulados'] ?? 0);
$diasTomados     = (int)($saldo['dias_tomados'] ?? 0);
$diasDisponibles = $diasAcumulados - $diasTomados;

if ($diasDisponibles < 0) {
    $diasDisponibles = 0;
}

$mensaje = $_SESSION['mensaje'] ?? null;
$tipoMsg = $_SESSION['tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Vacaciones</title>
    <link rel="stylesheet" href="../css/permisos.css">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <h1>Solicitar Vacaciones</h1>
    <p style="margin-top:-18px;color:#666;">
        <?php echo htmlspecialchars($emp['nombre'] . " " . $emp['apellidos']); ?>
    </p>

    <?php if ($mensaje): ?>
        <div class="alert <?php echo ($tipoMsg === 'success' ? 'success' : ($tipoMsg === 'danger' ? 'danger' : 'warning')); ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div class="cards" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom:18px;">
        <div class="card">
            <h3>Días acumulados</h3>
            <p style="font-size:22px;font-weight:700;"><?php echo $diasAcumulados; ?></p>
        </div>
        <div class="card">
            <h3>Días tomados</h3>
            <p style="font-size:22px;font-weight:700;"><?php echo $diasTomados; ?></p>
        </div>
        <div class="card">
            <h3>Días disponibles</h3>
            <p style="font-size:22px;font-weight:700;"><?php echo $diasDisponibles; ?></p>
        </div>
    </div>

    <div class="table-box" style="max-width:720px;">
        <form action="GuardarVacacion.php" method="POST" style="display:grid; gap:16px;">
            <div>
                <label for="fecha_inicio"><b>Fecha de inicio</b></label>
                <input
                    type="date"
                    name="fecha_inicio"
                    id="fecha_inicio"
                    required
                    style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;margin-top:6px;"
                >
            </div>

            <div>
                <label for="fecha_fin"><b>Fecha de fin</b></label>
                <input
                    type="date"
                    name="fecha_fin"
                    id="fecha_fin"
                    required
                    style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;margin-top:6px;"
                >
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" name="guardar" value="1" class="btn btn-success">
                    Enviar solicitud
                </button>
                <a href="MisVacaciones.php" class="btn" style="text-decoration:none;padding:10px 14px;border:1px solid #ccc;border-radius:8px;">
                    Volver
                </a>
            </div>
        </form>
    </div>
</div>

</body>
</html>