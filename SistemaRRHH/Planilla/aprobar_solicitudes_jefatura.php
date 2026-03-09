<?php
session_start();
include("../config/conexion.php");

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

function isJefatura(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'jefatura';
}

if (!isJefatura() && !isAdmin()) {
    echo "<div style='margin:20px;padding:12px;border-radius:8px;background:#f8d7da;color:#842029;'>
            No tiene permisos para acceder a este módulo.
          </div>";
    exit();
}

$idUsuario = (int)($_SESSION['idUsuario'] ?? 0);

$sql = "
    SELECT 
        sp.idSolicitud,
        sp.tipo_solicitud,
        sp.monto,
        sp.descripcion,
        sp.estado,
        sp.fecha_creacion,
        sp.observacion,
        e.idEmpleado,
        e.nombre,
        e.apellidos
    FROM solicitudes_planilla sp
    INNER JOIN empleado e ON e.idEmpleado = sp.idEmpleado
    WHERE sp.estado = 'Pendiente_Jefatura'
      AND sp.estado_registro = b'1'
      AND sp.idJefatura = ?
    ORDER BY sp.idSolicitud DESC
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error preparando consulta: " . $conexion->error);
}

$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$solicitudes = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aprobación de Solicitudes - Jefatura</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        body{
            background:#f4f6f9;
        }
        .card{
            border:none;
            border-radius:14px;
        }
        .table th{
            background:#0d6efd;
            color:#fff;
            vertical-align:middle;
        }
        .table td{
            vertical-align:middle;
        }
        .badge-pendiente{
            background:#fff3cd;
            color:#856404;
            padding:6px 10px;
            border-radius:8px;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Solicitudes de Planilla - Jefatura</h4>
        </div>
        <div class="card-body">

            <?php if ($solicitudes->num_rows > 0) { ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Empleado</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th style="width: 260px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $solicitudes->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo (int)$row['idSolicitud']; ?></td>
                                    <td><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tipo_solicitud']); ?></td>
                                    <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                                    <td><?php echo htmlspecialchars($row['fecha_creacion']); ?></td>
                                    <td><span class="badge-pendiente"><?php echo htmlspecialchars($row['estado']); ?></span></td>
                                    <td>
                                        <form action="procesar_solicitud_jefatura.php" method="POST" class="d-flex flex-column gap-2">
                                            <input type="hidden" name="idSolicitud" value="<?php echo (int)$row['idSolicitud']; ?>">

                                            <textarea
                                                name="observacion"
                                                class="form-control"
                                                rows="2"
                                                placeholder="Observación (opcional para aprobar, recomendada para rechazar)"
                                            ></textarea>

                                            <div class="d-flex gap-2">
                                                <button type="submit" name="accion" value="aprobar" class="btn btn-success btn-sm w-50">
                                                    Aprobar
                                                </button>
                                                <button type="submit" name="accion" value="rechazar" class="btn btn-danger btn-sm w-50">
                                                    Rechazar
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } else { ?>
                <div class="alert alert-info mb-0">
                    No hay solicitudes pendientes para jefatura.
                </div>
            <?php } ?>

        </div>
    </div>
</div>
</body>
</html>