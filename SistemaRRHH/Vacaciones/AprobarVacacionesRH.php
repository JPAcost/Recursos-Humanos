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

function isAdmin(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'admin';
}

if (!can('Vacaciones_aprobados_RH') && !isAdmin()) {
    echo "<div class='alert danger' style='margin:20px'>No tiene permisos para acceder a este módulo.</div>";
    exit();
}

function estadoTexto($e) {
    switch ((int)$e) {
        case 0: return ["Pendiente Jefatura", "warn"];
        case 1: return ["Rechazado por Jefatura", "bad"];
        case 2: return ["Pendiente RH", "warn"];
        case 3: return ["Aprobado por RH", "ok"];
        case 4: return ["Rechazado por RH", "bad"];
        default: return ["Desconocido", ""];
    }
}

function tieneFechaReal($fecha) {
    return !empty($fecha) && $fecha !== '1900-01-01 00:00:00' && $fecha !== '0000-00-00 00:00:00';
}

$mensaje = $_SESSION['mensaje'] ?? null;
$tipoMsg = $_SESSION['tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo']);

$sql = "
    SELECT 
        v.idVacaciones,
        v.fecha_solicitud,
        v.fecha_inicio,
        v.fecha_fin,
        v.dias_solicitados,
        v.dias_aprobados,
        v.estado,
        v.jefe_fecha,
        v.jefe_comentario,
        e.idEmpleado,
        e.nombre,
        e.apellidos
    FROM vacaciones v
    INNER JOIN empleado e
        ON e.idEmpleado = v.Empleado_idEmpleado
    WHERE v.estado = 2
    ORDER BY v.idVacaciones DESC
";

$resultado = $conexion->query($sql);

if (!$resultado) {
    die("Error al consultar vacaciones pendientes de RH: " . $conexion->error);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aprobar Vacaciones - RRHH</title>
    <link rel="stylesheet" href="../css/permisos.css">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <h1>Aprobación de Vacaciones - RRHH</h1>
    <p style="margin-top:-15px;color:#666;">
        Aquí se muestran las solicitudes aprobadas por jefatura y pendientes de revisión por RRHH.
    </p>

    <?php if ($mensaje): ?>
        <div class="alert <?php echo ($tipoMsg === 'success' ? 'success' : ($tipoMsg === 'danger' ? 'danger' : 'warning')); ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div class="table-box">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Empleado</th>
                    <th>Solicitud</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Días solicitados</th>
                    <th>Días aprobados</th>
                    <th>Comentario Jefatura</th>
                    <th>Estado</th>
                    <th>Gestión</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultado && $resultado->num_rows > 0): ?>
                    <?php while ($r = $resultado->fetch_assoc()): ?>
                        <?php [$txtEstado, $claseEstado] = estadoTexto($r['estado']); ?>
                        <tr>
                            <td><?php echo (int)$r['idVacaciones']; ?></td>
                            <td><?php echo htmlspecialchars($r['nombre'] . " " . $r['apellidos']); ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_solicitud']); ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_inicio']); ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_fin']); ?></td>
                            <td><?php echo (int)$r['dias_solicitados']; ?></td>
                            <td><?php echo (int)$r['dias_aprobados']; ?></td>
                            <td>
                                <?php if (tieneFechaReal($r['jefe_fecha'])): ?>
                                    <div>
                                        <b>Jefatura:</b>
                                        <?php echo htmlspecialchars($r['jefe_comentario'] !== '' ? $r['jefe_comentario'] : 'Sin comentario'); ?>
                                    </div>
                                    <small style="color:#666;">
                                        <?php echo htmlspecialchars($r['jefe_fecha']); ?>
                                    </small>
                                <?php else: ?>
                                    <span>Sin comentario</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo htmlspecialchars($claseEstado); ?>">
                                    <?php echo htmlspecialchars($txtEstado); ?>
                                </span>
                            </td>
                            <td>
                                <form action="AccionVacacionesRH.php" method="POST" style="display:flex;flex-direction:column;gap:8px;min-width:220px;">
                                    <input type="hidden" name="idVacacion" value="<?php echo (int)$r['idVacaciones']; ?>">

                                    <textarea
                                        name="comentario"
                                        rows="3"
                                        placeholder="Comentario de RRHH (opcional)"
                                        style="resize:vertical;padding:8px;border:1px solid #ccc;border-radius:8px;"
                                    ></textarea>

                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <button type="submit" name="accion" value="aprobar" class="btn btn-success">
                                            Aprobar
                                        </button>
                                        <button type="submit" name="accion" value="rechazar" class="btn btn-danger">
                                            Rechazar
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align:center;">No hay solicitudes pendientes de aprobación por RRHH.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>