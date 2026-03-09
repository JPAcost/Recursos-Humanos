<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

/* ============================
   FILTRO MES / AÑO
============================ */
$mes  = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date("m");
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date("Y");

/* ============================
   OBTENER PLANILLA
============================ */
$stmt = $conexion->prepare("
SELECT
    p.idPlanilla,
    p.Fecha_planilla,
    pl.salario_base,
    pl.total_horas_extra,
    pl.total_deducciones,
    pl.total_a_pagar,
    e.nombre,
    e.apellidos
FROM planilla p
INNER JOIN planillas pl
ON pl.Planilla_idPlanilla = p.idPlanilla
INNER JOIN empleado e
ON e.idEmpleado = pl.Empleado_idEmpleado
WHERE p.Mes = ?
AND p.Anio = ?
");

$stmt->bind_param("ii",$mes,$anio);
$stmt->execute();
$result = $stmt->get_result();

/* ============================
   TOTALES
============================ */
$totalNomina = 0;
$totalDeducciones = 0;
$totalHorasExtra = 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">
<title>Reporte de Planilla</title>

<link rel="stylesheet" href="../assets/css/bootstrap.min.css">

<style>

body{
background:#f4f6f9;
}

.card{
border-radius:12px;
}

</style>

</head>

<body>

<div class="container mt-4">

<div class="card shadow">

<div class="card-header bg-primary text-white">
<h4>Reporte de Planilla</h4>
</div>

<div class="card-body">

<form method="GET" class="row mb-3">

<div class="col-md-4">
<label>Mes</label>
<input type="number" name="mes" class="form-control" value="<?php echo $mes ?>">
</div>

<div class="col-md-4">
<label>Año</label>
<input type="number" name="anio" class="form-control" value="<?php echo $anio ?>">
</div>

<div class="col-md-4 mt-4">
<button class="btn btn-primary">
Consultar
</button>
</div>

</form>

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>
<th>Empleado</th>
<th>Salario Base</th>
<th>Horas Extra</th>
<th>Deducciones</th>
<th>Total a Pagar</th>
</tr>

</thead>

<tbody>

<?php if($result->num_rows > 0){ ?>

<?php while($row = $result->fetch_assoc()){ ?>

<?php

$totalNomina += $row['total_a_pagar'];
$totalDeducciones += $row['total_deducciones'];
$totalHorasExtra += $row['total_horas_extra'];

?>

<tr>

<td>
<?php echo $row['nombre']." ".$row['apellidos']; ?>
</td>

<td>
₡<?php echo number_format($row['salario_base'],2,'.',','); ?>
</td>

<td>
₡<?php echo number_format($row['total_horas_extra'],2,'.',','); ?>
</td>

<td>
₡<?php echo number_format($row['total_deducciones'],2,'.',','); ?>
</td>

<td>
₡<?php echo number_format($row['total_a_pagar'],2,'.',','); ?>
</td>

</tr>

<?php } ?>

<?php } else { ?>

<tr>
<td colspan="5" class="text-center">
No hay planillas generadas para este periodo
</td>
</tr>

<?php } ?>

</tbody>

</table>

<hr>

<h5>Resumen</h5>

<p>
<strong>Total Horas Extra:</strong>
₡<?php echo number_format($totalHorasExtra,2,'.',','); ?>
</p>

<p>
<strong>Total Deducciones:</strong>
₡<?php echo number_format($totalDeducciones,2,'.',','); ?>
</p>

<p>
<strong>Total Nómina:</strong>
₡<?php echo number_format($totalNomina,2,'.',','); ?>
</p>

<a href="../index.php" class="btn btn-secondary">
Volver
</a>

</div>
</div>

</div>

</body>
</html>