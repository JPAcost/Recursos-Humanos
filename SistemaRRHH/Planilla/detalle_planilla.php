<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$idPlanilla = (int)($_GET['id'] ?? 0);

if ($idPlanilla <= 0) {
    die("ID de planilla inválido.");
}

/* ============================
   CABECERA
============================ */
$stmtCab = $conexion->prepare("
    SELECT idPlanilla, Fecha_planilla, Mes, Anio, Estado
    FROM planilla
    WHERE idPlanilla = ?
    LIMIT 1
");

if (!$stmtCab) {
    die("Error preparando cabecera: " . $conexion->error);
}

$stmtCab->bind_param("i", $idPlanilla);
$stmtCab->execute();
$cabecera = $stmtCab->get_result()->fetch_assoc();

if (!$cabecera) {
    die("No se encontró la planilla.");
}

/* ============================
   DETALLE
============================ */
$stmtDet = $conexion->prepare("
    SELECT
        pl.idDetalle_Planilla,
        pl.Fecha_planilla,
        pl.salario_base,
        pl.total_horas_extra,
        pl.total_deducciones,
        pl.total_a_pagar,
        e.nombre,
        e.apellidos
    FROM planillas pl
    INNER JOIN empleado e ON e.idEmpleado = pl.Empleado_idEmpleado
    WHERE pl.Planilla_idPlanilla = ?
    ORDER BY e.nombre, e.apellidos
");

if (!$stmtDet) {
    die("Error preparando detalle: " . $conexion->error);
}

$stmtDet->bind_param("i", $idPlanilla);
$stmtDet->execute();
$detalle = $stmtDet->get_result();

$totalHorasExtra = 0;
$totalDeducciones = 0;
$totalPagar = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Planilla</title>
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
        }
    </style>
</head>
<body>

<div class="container mt-4">

    <div class="card shadow mb-4">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0">Detalle de Planilla</h4>
        </div>
        <div class="card-body">
            <p><strong>ID Planilla:</strong> <?php echo (int)$cabecera['idPlanilla']; ?></p>
            <p><strong>Fecha:</strong> <?php echo htmlspecialchars($cabecera['Fecha_planilla']); ?></p>
            <p><strong>Periodo:</strong> <?php echo htmlspecialchars($cabecera['Mes'] . '/' . $cabecera['Anio']); ?></p>
            <p><strong>Estado:</strong> <?php echo htmlspecialchars($cabecera['Estado']); ?></p>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Empleados incluidos</h5>
        </div>
        <div class="card-body">

            <?php if ($detalle->num_rows > 0) { ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Salario Base</th>
                                <th>Horas Extra</th>
                                <th>Deducciones</th>
                                <th>Total a Pagar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $detalle->fetch_assoc()) { ?>
                                <?php
                                $totalHorasExtra += (float)$row['total_horas_extra'];
                                $totalDeducciones += (float)$row['total_deducciones'];
                                $totalPagar += (float)$row['total_a_pagar'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellidos']); ?></td>
                                    <td>₡<?php echo number_format((float)$row['salario_base'], 2, '.', ','); ?></td>
                                    <td>₡<?php echo number_format((float)$row['total_horas_extra'], 2, '.', ','); ?></td>
                                    <td>₡<?php echo number_format((float)$row['total_deducciones'], 2, '.', ','); ?></td>
                                    <td>₡<?php echo number_format((float)$row['total_a_pagar'], 2, '.', ','); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <hr>
                <p><strong>Total Horas Extra:</strong> ₡<?php echo number_format($totalHorasExtra, 2, '.', ','); ?></p>
                <p><strong>Total Deducciones:</strong> ₡<?php echo number_format($totalDeducciones, 2, '.', ','); ?></p>
                <p><strong>Total Neto a Pagar:</strong> ₡<?php echo number_format($totalPagar, 2, '.', ','); ?></p>
            <?php } else { ?>
                <div class="alert alert-info">
                    Esta planilla no tiene detalles registrados.
                </div>
            <?php } ?>

            <a href="planilla.php" class="btn btn-secondary">Volver</a>
        </div>
    </div>

</div>

</body>
</html>