<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

function isAdmin(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'admin';
}

$idUsuario = (int)($_SESSION['idUsuario'] ?? 0);

if (!isAdmin()) {
    echo "<div style='margin:20px;padding:12px;border-radius:8px;background:#f8d7da;color:#842029;'>
            No tiene permisos para acceder a este módulo.
          </div>";
    exit();
}

$sql = "
    SELECT 
        sp.idSolicitud,
        sp.tipo_solicitud,
        sp.monto,
        sp.descripcion,
        sp.estado,
        sp.fecha_creacion,
        sp.fecha_aprobacion_jefatura,
        sp.observacion,
        e.idEmpleado,
        e.nombre,
        e.apellidos
    FROM solicitudes_planilla sp
    INNER JOIN empleado e ON e.idEmpleado = sp.idEmpleado
    WHERE sp.estado = 'Pendiente_RRHH'
      AND sp.estado_registro = b'1'
      AND sp.idRRHH = ?
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
    <title>Validación de Solicitudes - RRHH</title>
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
            background:#198754;
            color:#fff;
            vertical-align:middle;
        }
        .table td{
            vertical-align:middle;
        }
        .badge-rrhh{
            background:#cff4fc;
            color:#055160;
            padding:6px 10px;
            border-radius:8px;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0">Solicitudes de Planilla - RRHH</h4>
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
                                <th>Fecha Solicitud</th>
                                <th>Aprobación Jefatura</th>
                                <th>Estado</th>
                                <th>Monto</th>
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
                                    <td><?php echo htmlspecialchars($row['fecha_aprobacion_jefatura']); ?></td>
                                    <td>₡<?php echo number_format((float)$row['monto'], 2, '.', ','); ?></td>
                                    <td><span class="badge-rrhh"><?php echo htmlspecialchars($row['estado']); ?></span></td>
                                    <td>
                                        <form action="procesar_solicitud_rrhh.php" method="POST" class="d-flex flex-column gap-2">
                                            <input type="hidden" name="idSolicitud" value="<?php echo (int)$row['idSolicitud']; ?>">

                                            <textarea
                                                name="observacion"
                                                class="form-control"
                                                rows="2"
                                                placeholder="Observación (opcional para validar, obligatoria para rechazar)"
                                            ><?php echo htmlspecialchars($row['observacion']); ?></textarea>

                                            <div class="d-flex gap-2">
                                                <button type="submit" name="accion" value="validar" class="btn btn-success btn-sm w-50">
                                                    Validar
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
                    No hay solicitudes pendientes para RRHH.
                </div>
            <?php } ?>

        </div>
    </div>
</div>
</body>
</html>