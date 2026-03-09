<?php
session_start();
require_once("../config/conexion.php");
require_once("../includes/Permisos.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

if (!can('Permisos_ver_empleado')) {
    echo "<div class='alert danger' style='margin:20px'>No tiene permisos para ver permisos.</div>";
    exit();
}

$idUsuario = (int)($_SESSION['idUsuario'] ?? 0);

$stmtEmp = $conexion->prepare("
    SELECT idEmpleado, nombre, apellidos
    FROM empleado
    WHERE Usuario_idUsuario = ?
    LIMIT 1
");

if (!$stmtEmp) {
    die("Error al preparar consulta de empleado: " . $conexion->error);
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
            return ["Pendiente RRHH", "warn"];
        case 3:
            return ["Aprobado por RRHH", "ok"];
        case 4:
            return ["Rechazado por RRHH", "bad"];
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

$stmt = $conexion->prepare("
    SELECT 
        p.*,
        tp.tipo AS tipo_permiso
    FROM permiso p
    LEFT JOIN tipo_permiso tp 
        ON tp.idTipo_permiso = p.Tipo_permiso_idTipo_permiso
    WHERE p.Empleado_idEmpleado = ?
    ORDER BY p.idPermiso DESC
");

if (!$stmt) {
    die("Error al preparar consulta de permisos: " . $conexion->error);
}

$stmt->bind_param("i", $idEmpleado);
$stmt->execute();
$permisos = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Permisos</title>
    <link rel="stylesheet" href="../css/permisos.css">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <h1>Mis Permisos</h1>
    <p style="margin-top:-20px;color:#666;">
        <?php echo htmlspecialchars($emp['nombre'] . " " . $emp['apellidos']); ?>
    </p>

    <?php if ($mensaje): ?>
        <div class="alert <?php echo ($tipoMsg === 'success' ? 'success' : ($tipoMsg === 'danger' ? 'danger' : 'warning')); ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div class="cards" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
        <a class="card-link" href="SolicitarPermiso.php">
            <div class="card">
                <h3>Nuevo Permiso</h3>
                <p style="font-size:18px;font-weight:600;">Solicitar</p>
            </div>
        </a>
    </div>

    <div style="height:15px;"></div>

    <div class="table-box">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tipo</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Comentarios</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($permisos && $permisos->num_rows > 0): ?>
                    <?php while ($r = $permisos->fetch_assoc()): ?>
                        <?php [$txt, $cls] = estadoTexto($r['estado']); ?>
                        <tr>
                            <td><?php echo (int)$r['idPermiso']; ?></td>
                            <td><?php echo htmlspecialchars($r['tipo_permiso'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_inicio']); ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_fin']); ?></td>
                            <td><?php echo htmlspecialchars($r['motivo']); ?></td>
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
                        <td colspan="7" style="text-align:center;">No hay permisos registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>