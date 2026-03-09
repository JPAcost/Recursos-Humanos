<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$idUsuario = (int)$_SESSION['idUsuario'];

/* ============================
   OBTENER EMPLEADO
============================ */
$stmtEmp = $conexion->prepare("
    SELECT idEmpleado, nombre, apellidos
    FROM empleado
    WHERE Usuario_idUsuario = ?
");

$stmtEmp->bind_param("i", $idUsuario);
$stmtEmp->execute();
$empleado = $stmtEmp->get_result()->fetch_assoc();

if (!$empleado) {
    die("Empleado no encontrado.");
}

$idEmpleado = (int)$empleado['idEmpleado'];

/* ============================
   OBTENER SOLICITUDES
============================ */
$stmt = $conexion->prepare("
    SELECT
        tipo_solicitud,
        monto,
        descripcion,
        estado,
        fecha_creacion
    FROM solicitudes_planilla
    WHERE idEmpleado = ?
    ORDER BY fecha_creacion DESC
");

$stmt->bind_param("i", $idEmpleado);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Mis Solicitudes de Planilla</title>
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
<h4>Mis Solicitudes de Planilla</h4>
</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">
<tr>
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
<td colspan="5" class="text-center">
No tiene solicitudes registradas.
</td>
</tr>

<?php } ?>

</tbody>

</table>

<a href="solicitud_planilla.php" class="btn btn-success">
Nueva Solicitud
</a>

<a href="../index.php" class="btn btn-secondary">
Volver
</a>

</div>
</div>

</div>

</body>
</html>