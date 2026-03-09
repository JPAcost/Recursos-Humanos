<?php
include("../config/conexion.php");
include(__DIR__ . "/../Seguridad/validar_rol.php");
$empleados = $conexion->query("SELECT COUNT(*) total FROM empleado")->fetch_assoc()['total'];

$incapacidades = $conexion->query("SELECT COUNT(*) total FROM incapacidad WHERE Estado='Activo'")->fetch_assoc()['total'];

$aguinaldos = $conexion->query("SELECT SUM(monto_calculado) total FROM aguinaldo WHERE estado='Activo'")->fetch_assoc()['total'];

$liquidaciones = $conexion->query("SELECT SUM(total_liquidacion) total FROM liquidacion WHERE estado='Activo'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">

<div class="container mt-4">
<div class="row">

<div class="col-md-3">
<div class="card shadow">
<div class="card-body">
<h6>Empleados</h6>
<h3><?= $empleados ?></h3>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card shadow">
<div class="card-body">
<h6>Incapacidades</h6>
<h3><?= $incapacidades ?></h3>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card shadow">
<div class="card-body">
<h6>Aguinaldos</h6>
<h3>₡<?= number_format($aguinaldos,2) ?></h3>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card shadow">
<div class="card-body">
<h6>Liquidaciones</h6>
<h3>₡<?= number_format($liquidaciones,2) ?></h3>
</div>
</div>
</div>

</div>
</div>

</body>
</html>