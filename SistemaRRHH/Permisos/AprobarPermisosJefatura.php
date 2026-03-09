<?php
session_start();
require_once("../config/conexion.php");
require_once("../includes/Permisos.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

if (!can('Permisos_aprobados_Jefatura') && !isJefatura() && !isAdmin()) {
    echo "<div class='alert danger' style='margin:20px'>No tiene permisos para acceder a este módulo.</div>";
    exit();
}

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

$mensaje = $_SESSION['mensaje'] ?? null;
$tipoMsg = $_SESSION['tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo']);

$sql = "
    SELECT 
        p.idPermiso,
        p.fecha_solicitud,
        p.fecha_inicio,
        p.fecha_fin,
        p.motivo,
        p.estado,
        p.jefe_comentario,
        p.jefe_fecha,
        p.rh_comentario,
        p.rh_fecha,
        tp.tipo AS tipo_permiso,
        e.idEmpleado,
        e.nombre,
        e.apellidos
    FROM permiso p
    INNER JOIN empleado e
        ON e.idEmpleado = p.Empleado_idEmpleado
    LEFT JOIN tipo_permiso tp
        ON tp.idTipo_permiso = p.Tipo_permiso_idTipo_permiso
    WHERE p.estado = 0
    ORDER BY p.idPermiso DESC
";

$resultado = $conexion->query($sql);

if (!$resultado) {
    die("Error al consultar permisos pendientes de jefatura: " . $conexion->error);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aprobar Permisos - Jefatura</title>
    <link rel="stylesheet" href="../css/permisos.css">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <h1>Aprobación de Permisos - Jefatura</h1>
    <p style="margin-top:-15px;color:#666;">
        Aquí se muestran los permisos pendientes de revisión por jefatura.
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
                    <th>Tipo</th>
                    <th>Solicitud</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Gestión</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultado->num_rows > 0): ?>
                    <?php while ($r = $resultado->fetch_assoc()): ?>
                        <?php [$txtEstado, $claseEstado] = estadoTexto($r['estado']); ?>
                        <tr>
                            <td><?php echo (int)$r['idPermiso']; ?></td>
                            <td><?php echo htmlspecialchars($r['nombre'] . " " . $r['apellidos']); ?></td>
                            <td><?php echo htmlspecialchars($r['tipo_permiso'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_solicitud']); ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_inicio']); ?></td>
                            <td><?php echo htmlspecialchars($r['fecha_fin']); ?></td>
                            <td><?php echo htmlspecialchars($r['motivo']); ?></td>
                            <td>
                                <span class="badge <?php echo htmlspecialchars($claseEstado); ?>">
                                    <?php echo htmlspecialchars($txtEstado); ?>
                                </span>
                            </td>
                            <td>
                                <form action="AccionJefatura.php" method="POST" style="display:flex;flex-direction:column;gap:8px;min-width:220px;">
                                    <input type="hidden" name="idPermiso" value="<?php echo (int)$r['idPermiso']; ?>">

                                    <textarea 
                                        name="comentario" 
                                        rows="3" 
                                        placeholder="Comentario de jefatura (opcional)"
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
                        <td colspan="9" style="text-align:center;">No hay permisos pendientes de aprobación por jefatura.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>