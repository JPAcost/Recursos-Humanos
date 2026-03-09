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

if (!can('Vacaciones_ver_Empleado') && !can('Vacaciones_solicitar_Empleado')) {
    echo "<div class='alert danger' style='margin:20px'>No tiene permisos para ver vacaciones.</div>";
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

function estadoTexto($e) {
    switch ((int)$e) {
        case 0:
            return ["Pendiente Jefatura", "warn"];
        case 1:
            return ["Rechazado por Jefatura", "bad"];
        case 2:
            return ["Pendiente RH", "warn"];
        case 3:
            return ["Aprobado por RH", "ok"];
        case 4:
            return ["Rechazado por RH", "bad"];
        default:
            return ["Desconocido", ""];
    }
}

function tieneFechaReal($fecha) {
    return !empty($fecha) && $fecha !== '1900-01-01 00:00:00' && $fecha !== '0000-00-00 00:00:00';
}

$mensaje = $_SESSION['mensaje'] ?? null;
$tipoMsg = $_SESSION['tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo']);

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

$stmt = $conexion->prepare("
    SELECT *
    FROM vacaciones
    WHERE Empleado_idEmpleado = ?
    ORDER BY idVacaciones DESC
");

if (!$stmt) {
    die("Error al consultar vacaciones: " . $conexion->error);
}

$stmt->bind_param("i", $idEmpleado);
$stmt->execute();
$vacaciones = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Vacaciones</title>
    <link rel="stylesheet" href="../css/permisos.css">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <h1>Mis Vacaciones</h1>
    <p style="margin-top:-20px;color:#666;">
        <?php echo htmlspecialchars($emp['nombre'] . " " . $emp['apellidos']); ?>
    </p>

    <?php if ($mensaje): ?>
        <div class="alert <?php echo ($tipoMsg === 'success' ? 'success' : ($tipoMsg === 'danger' ? 'danger' : 'warning')); ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div class="cards" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <a class="card-link" href="SolicitarVacacion.php">
            <div class="card">
                <h3>Nueva Solicitud</h3>
                <p style="font-size:18px;font-weight:600;">Solicitar vacaciones</p>
            </div>
        </a>

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

    <div style="height:15px;"></div>

    <div class="table-box">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Solicitud</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Días Solicitados</th>
                    <th>Días Aprobados</th>
                    <th>Estado</th>
                    <th>Comentarios</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($vacaciones && $vacaciones->num_rows > 0): ?>
                    <?php while ($r = $vacaciones->fetch_assoc()): ?>
                        <?php [$txt, $cls] = estadoTexto($r['estado']); ?>
                        <tr>
                            <td><?php echo (int)$r['idVacaciones']; ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_solicitud']); ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_inicio']); ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_fin']); ?></td>
                            <td><?php echo (int)$r['dias_solicitados']; ?></td>
                            <td><?php echo (int)$r['dias_aprobados']; ?></td>
                            <td>
                                <span class="badge <?php echo htmlspecialchars($cls); ?>">
                                    <?php echo htmlspecialchars($txt); ?>
                                </span>
                            </td>
                            <td>
                                <div style="color:#666;font-size:13px;">
                                    <?php
                                        $hayComentarioJefe = tieneFechaReal($r['jefe_fecha']);
                                        $hayComentarioRH   = tieneFechaReal($r['rh_fecha']);
                                    ?>

                                    <?php if ($hayComentarioJefe): ?>
                                        <div style="margin-bottom:4px;">
                                            <b>Jefatura:</b>
                                            <?php echo htmlspecialchars($r['jefe_comentario'] !== '' ? $r['jefe_comentario'] : 'Sin comentario'); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($hayComentarioRH): ?>
                                        <div>
                                            <b>RRHH:</b>
                                            <?php echo htmlspecialchars($r['rh_comentario'] !== '' ? $r['rh_comentario'] : 'Sin comentario'); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!$hayComentarioJefe && !$hayComentarioRH): ?>
                                        <span>Sin comentarios</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">No hay solicitudes registradas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>