<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$mesActual  = (int)date("m");
$anioActual = (int)date("Y");

/* ============================
   RESUMEN DEL MES ACTUAL
============================ */

// Total empleados activos
$resEmp = $conexion->query("
    SELECT COUNT(*) AS total
    FROM empleado
    WHERE Estado = 'Activo'
");
$totalEmpleados = 0;
if ($resEmp && $row = $resEmp->fetch_assoc()) {
    $totalEmpleados = (int)$row['total'];
}

// Total solicitudes pendientes jefatura
$resPendJ = $conexion->query("
    SELECT COUNT(*) AS total
    FROM solicitudes_planilla
    WHERE estado = 'Pendiente_Jefatura'
      AND estado_registro = b'1'
");
$totalPendJefatura = 0;
if ($resPendJ && $row = $resPendJ->fetch_assoc()) {
    $totalPendJefatura = (int)$row['total'];
}

// Total solicitudes pendientes RRHH
$resPendR = $conexion->query("
    SELECT COUNT(*) AS total
    FROM solicitudes_planilla
    WHERE estado = 'Pendiente_RRHH'
      AND estado_registro = b'1'
");
$totalPendRRHH = 0;
if ($resPendR && $row = $resPendR->fetch_assoc()) {
    $totalPendRRHH = (int)$row['total'];
}

// Buscar planilla del mes actual
$stmtPlanillaMes = $conexion->prepare("
    SELECT idPlanilla, Fecha_planilla
    FROM planilla
    WHERE Mes = ? AND Anio = ?
    LIMIT 1
");

$idPlanillaMes = 0;
if ($stmtPlanillaMes) {
    $stmtPlanillaMes->bind_param("ii", $mesActual, $anioActual);
    $stmtPlanillaMes->execute();
    $planMes = $stmtPlanillaMes->get_result()->fetch_assoc();

    if ($planMes) {
        $idPlanillaMes = (int)$planMes['idPlanilla'];
    }
}

// Totales del mes actual
$totalNominaMes = 0.00;
$totalHorasExtraMes = 0.00;
$totalDeduccionesMes = 0.00;
$totalPagadosMes = 0;

if ($idPlanillaMes > 0) {
    $stmtTotales = $conexion->prepare("
        SELECT 
            COUNT(*) AS total_empleados,
            COALESCE(SUM(total_horas_extra), 0) AS total_horas_extra,
            COALESCE(SUM(total_deducciones), 0) AS total_deducciones,
            COALESCE(SUM(total_a_pagar), 0) AS total_nomina
        FROM planillas
        WHERE Planilla_idPlanilla = ?
    ");

    if ($stmtTotales) {
        $stmtTotales->bind_param("i", $idPlanillaMes);
        $stmtTotales->execute();
        $tot = $stmtTotales->get_result()->fetch_assoc();

        if ($tot) {
            $totalPagadosMes     = (int)$tot['total_empleados'];
            $totalHorasExtraMes  = (float)$tot['total_horas_extra'];
            $totalDeduccionesMes = (float)$tot['total_deducciones'];
            $totalNominaMes      = (float)$tot['total_nomina'];
        }
    }
}

/* ============================
   HISTORIAL DE PLANILLAS
============================ */
$sqlHistorial = "
    SELECT 
        p.idPlanilla,
        p.Fecha_planilla,
        p.Mes,
        p.Anio,
        p.Estado,
        COUNT(pl.idDetalle_Planilla) AS total_empleados,
        COALESCE(SUM(pl.total_a_pagar), 0) AS total_nomina
    FROM planilla p
    LEFT JOIN planillas pl ON pl.Planilla_idPlanilla = p.idPlanilla
    GROUP BY p.idPlanilla, p.Fecha_planilla, p.Mes, p.Anio, p.Estado
    ORDER BY p.Anio DESC, p.Mes DESC, p.idPlanilla DESC
    LIMIT 10
";

$resHistorial = $conexion->query($sqlHistorial);

function nombreMes($mes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[(int)$mes] ?? 'Mes';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Módulo de Planillas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body{
            margin:0;
            background:#f4f6f9;
            font-family:'Segoe UI', sans-serif;
        }

        .layout{
            display:flex;
            min-height:100vh;
        }

        .content{
            flex:1;
            padding:25px;
        }

        .module-header,
        .card{
            border:none;
            border-radius:16px;
        }

        .stat-card{
            color:#fff;
            border-radius:18px;
            overflow:hidden;
            position:relative;
        }

        .stat-card .icon{
            font-size:2.2rem;
            opacity:0.25;
            position:absolute;
            right:18px;
            top:14px;
        }

        .bg-card-1{ background:linear-gradient(135deg, #0d6efd, #3d8bfd); }
        .bg-card-2{ background:linear-gradient(135deg, #198754, #39b980); }
        .bg-card-3{ background:linear-gradient(135deg, #fd7e14, #ff9f43); }
        .bg-card-4{ background:linear-gradient(135deg, #6f42c1, #9b6bff); }
        .bg-card-5{ background:linear-gradient(135deg, #dc3545, #ef6674); }
        .bg-card-6{ background:linear-gradient(135deg, #20c997, #49d7b5); }

        .stat-card .card-body{
            padding:1.25rem;
        }

        .stat-label{
            font-size:0.92rem;
            opacity:0.95;
        }

        .stat-value{
            font-size:1.7rem;
            font-weight:700;
            margin-top:6px;
        }

        .quick-btn{
            border-radius:12px;
            padding:14px 12px;
            font-weight:600;
        }

        .table thead th{
            background:#212529;
            color:#fff;
            vertical-align:middle;
        }

        .table td{
            vertical-align:middle;
        }

        .section-title{
            font-weight:700;
        }

        @media (max-width: 991px){
            .layout{
                flex-direction:column;
            }

            .content{
                padding:15px;
            }
        }
    </style>
</head>
<body>

<div class="layout">
    <?php include("../includes/sidebar.php"); ?>

    <div class="content">

        <div class="card shadow module-header mb-4">
            <div class="card-body">
                <h2 class="mb-1">
                    <i class="bi bi-cash-stack"></i>
                    Módulo de Planillas
                </h2>
                <div class="text-muted">
                    Panel general de nómina, solicitudes y control de pagos
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card stat-card bg-card-1 shadow-sm">
                    <div class="card-body">
                        <div class="stat-label">Total Nómina del Mes</div>
                        <div class="stat-value">₡<?php echo number_format($totalNominaMes, 2, '.', ','); ?></div>
                        <i class="bi bi-wallet2 icon"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card stat-card bg-card-2 shadow-sm">
                    <div class="card-body">
                        <div class="stat-label">Empleados Pagados</div>
                        <div class="stat-value"><?php echo $totalPagadosMes; ?></div>
                        <i class="bi bi-people-fill icon"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card stat-card bg-card-3 shadow-sm">
                    <div class="card-body">
                        <div class="stat-label">Horas Extra del Mes</div>
                        <div class="stat-value">₡<?php echo number_format($totalHorasExtraMes, 2, '.', ','); ?></div>
                        <i class="bi bi-clock-history icon"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card stat-card bg-card-4 shadow-sm">
                    <div class="card-body">
                        <div class="stat-label">Deducciones del Mes</div>
                        <div class="stat-value">₡<?php echo number_format($totalDeduccionesMes, 2, '.', ','); ?></div>
                        <i class="bi bi-receipt-cutoff icon"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card stat-card bg-card-5 shadow-sm">
                    <div class="card-body">
                        <div class="stat-label">Pendientes Jefatura</div>
                        <div class="stat-value"><?php echo $totalPendJefatura; ?></div>
                        <i class="bi bi-person-check-fill icon"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card stat-card bg-card-6 shadow-sm">
                    <div class="card-body">
                        <div class="stat-label">Pendientes RRHH</div>
                        <div class="stat-value"><?php echo $totalPendRRHH; ?></div>
                        <i class="bi bi-building-check icon"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card stat-card bg-card-1 shadow-sm">
                    <div class="card-body">
                        <div class="stat-label">Empleados Activos</div>
                        <div class="stat-value"><?php echo $totalEmpleados; ?></div>
                        <i class="bi bi-person-vcard-fill icon"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card stat-card bg-card-2 shadow-sm">
                    <div class="card-body">
                        <div class="stat-label">Planilla del Mes</div>
                        <div class="stat-value"><?php echo $idPlanillaMes > 0 ? 'Generada' : 'Pendiente'; ?></div>
                        <i class="bi bi-file-earmark-text-fill icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <span class="section-title">
                    <i class="bi bi-lightning-charge-fill"></i>
                    Accesos rápidos
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6 col-xl-3">
                        <a href="generar.php" class="btn btn-success w-100 quick-btn">
                            <i class="bi bi-calculator"></i>
                            Generar Planilla
                        </a>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <a href="reporte_planilla.php" class="btn btn-primary w-100 quick-btn">
                            <i class="bi bi-bar-chart-line-fill"></i>
                            Reporte Mensual
                        </a>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <a href="solicitud_planilla.php" class="btn btn-warning w-100 quick-btn">
                            <i class="bi bi-file-earmark-plus-fill"></i>
                            Nueva Solicitud
                        </a>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <a href="mis_solicitudes_planilla.php" class="btn btn-info text-white w-100 quick-btn">
                            <i class="bi bi-card-checklist"></i>
                            Mis Solicitudes
                        </a>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <a href="aprobar_solicitudes_jefatura.php" class="btn btn-dark w-100 quick-btn">
                            <i class="bi bi-person-check"></i>
                            Aprobación Jefatura
                        </a>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <a href="validar_solicitudes_rrhh.php" class="btn btn-secondary w-100 quick-btn">
                            <i class="bi bi-building-check"></i>
                            Validación RRHH
                        </a>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <a href="gestion_solicitudes_planilla.php" class="btn btn-outline-dark w-100 quick-btn">
                            <i class="bi bi-list-ul"></i>
                            Gestión de Solicitudes
                        </a>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <a href="detalle_planilla.php?id=<?php echo $idPlanillaMes; ?>" class="btn btn-outline-success w-100 quick-btn <?php echo $idPlanillaMes <= 0 ? 'disabled' : ''; ?>">
                            <i class="bi bi-eye-fill"></i>
                            Ver Planilla del Mes
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <span class="section-title">
                    <i class="bi bi-clock-history"></i>
                    Historial reciente de planillas
                </span>
            </div>
            <div class="card-body">
                <?php if ($resHistorial && $resHistorial->num_rows > 0) { ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Periodo</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th>Empleados</th>
                                    <th>Total Nómina</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $resHistorial->fetch_assoc()) { ?>
                                    <tr>
                                        <td><?php echo (int)$row['idPlanilla']; ?></td>
                                        <td><?php echo htmlspecialchars(nombreMes($row['Mes']) . ' ' . $row['Anio']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Fecha_planilla']); ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo htmlspecialchars($row['Estado']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo (int)$row['total_empleados']; ?></td>
                                        <td>₡<?php echo number_format((float)$row['total_nomina'], 2, '.', ','); ?></td>
                                        <td>
                                            <a href="detalle_planilla.php?id=<?php echo (int)$row['idPlanilla']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="reporte_planilla.php?mes=<?php echo (int)$row['Mes']; ?>&anio=<?php echo (int)$row['Anio']; ?>" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-bar-chart"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } else { ?>
                    <div class="alert alert-info mb-0">
                        Aún no hay planillas generadas.
                    </div>
                <?php } ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>