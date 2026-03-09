<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

/* ============================
   OBTENER SOLICITUDES
============================ */

$sql = "
SELECT
    sp.idSolicitud,
    sp.tipo_solicitud,
    sp.monto,
    sp.descripcion,
    sp.estado,
    sp.fecha_creacion,
    e.nombre,
    e.apellidos
FROM solicitudes_planilla sp
INNER JOIN empleado e
ON e.idEmpleado = sp.idEmpleado
ORDER BY sp.fecha_creacion DESC
";

$result = $conexion->query($sql);

?>

<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">
<title>Gestión de Solicitudes Planilla</title>

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

<div class="card-header bg-dark text-white">
<h4>Gestión de Solicitudes de Planilla</h4>
</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>
<th>Empleado</th>
<th>Tipo</th>
<th>Monto</th>
<th>Descripción</th>
<th>Estado</th>
<th>Fecha</th>
</tr>

</thead>

<tbody>

<?php if($result->num_rows > 0){ ?>

<?php while($row = $result->fetch_assoc()){ ?>

<tr>

<td>
<?php echo htmlspecialchars($row['nombre']." ".$row['apellidos']); ?>
</td>

<td>
<?php echo htmlspecialchars($row['tipo_solicitud']); ?>
</td>

<td>
₡<?php echo number_format($row['monto'],2,'.',','); ?>
</td>

<td>
<?php echo htmlspecialchars($row['descripcion']); ?>
</td>

<td>

<?php

$estado = $row['estado'];

$color = "secondary";

if($estado == "Pendiente_Jefatura") $color = "warning";
if($estado == "Pendiente_RRHH") $color = "info";
if($estado == "Validado_RRHH") $color = "success";
if($estado == "Rechazado") $color = "danger";
if($estado == "Aplicado_Planilla") $color = "primary";

?>

<span class="badge bg-<?php echo $color; ?>">
<?php echo $estado; ?>
</span>

</td>

<td>
<?php echo date("d/m/Y", strtotime($row['fecha_creacion'])); ?>
</td>

</tr>

<?php } ?>

<?php } else { ?>

<tr>
<td colspan="6" class="text-center">
No hay solicitudes registradas.
</td>
</tr>

<?php } ?>

</tbody>

</table>

<a href="../index.php" class="btn btn-secondary">
Volver
</a>

</div>
</div>

</div>

</body>
</html><?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

/* ============================
   OBTENER SOLICITUDES
============================ */

$sql = "
SELECT
    sp.idSolicitud,
    sp.tipo_solicitud,
    sp.monto,
    sp.descripcion,
    sp.estado,
    sp.fecha_creacion,
    e.nombre,
    e.apellidos
FROM solicitudes_planilla sp
INNER JOIN empleado e
ON e.idEmpleado = sp.idEmpleado
ORDER BY sp.fecha_creacion DESC
";

$result = $conexion->query($sql);

?>

<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">
<title>Gestión de Solicitudes Planilla</title>

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

<div class="card-header bg-dark text-white">
<h4>Gestión de Solicitudes de Planilla</h4>
</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>
<th>Empleado</th>
<th>Tipo</th>
<th>Monto</th>
<th>Descripción</th>
<th>Estado</th>
<th>Fecha</th>
</tr>

</thead>

<tbody>

<?php if($result->num_rows > 0){ ?>

<?php while($row = $result->fetch_assoc()){ ?>

<tr>

<td>
<?php echo htmlspecialchars($row['nombre']." ".$row['apellidos']); ?>
</td>

<td>
<?php echo htmlspecialchars($row['tipo_solicitud']); ?>
</td>

<td>
₡<?php echo number_format($row['monto'],2,'.',','); ?>
</td>

<td>
<?php echo htmlspecialchars($row['descripcion']); ?>
</td>

<td>

<?php

$estado = $row['estado'];

$color = "secondary";

if($estado == "Pendiente_Jefatura") $color = "warning";
if($estado == "Pendiente_RRHH") $color = "info";
if($estado == "Validado_RRHH") $color = "success";
if($estado == "Rechazado") $color = "danger";
if($estado == "Aplicado_Planilla") $color = "primary";

?>

<span class="badge bg-<?php echo $color; ?>">
<?php echo $estado; ?>
</span>

</td>

<td>
<?php echo date("d/m/Y", strtotime($row['fecha_creacion'])); ?>
</td>

</tr>

<?php } ?>

<?php } else { ?>

<tr>
<td colspan="6" class="text-center">
No hay solicitudes registradas.
</td>
</tr>

<?php } ?>

</tbody>

</table>

<a href="../index.php" class="btn btn-secondary">
Volver
</a>

</div>
</div>

</div>

</body>
</html>