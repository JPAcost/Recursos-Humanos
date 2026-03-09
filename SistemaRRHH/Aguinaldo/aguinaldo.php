<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require("../config/permisos.php");

verificarSesion();
verificarPermiso('Aguinaldos_calcular_RH');

include("../config/conexion.php");

$anioActual    = (int)date("Y");
$periodoActual = $anioActual . "-12-01"; 

$mensaje = $_SESSION['mensaje'] ?? null;
$tipoMsg = $_SESSION['tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo']);

// Historial
$result = $conexion->query("
  SELECT 
    a.id_aguinaldo,
    a.periodo,
    a.monto_calculado,
    a.Fecha_Creacion,
    e.nombre,
    e.apellidos
  FROM aguinaldo a
  INNER JOIN empleado e ON a.Empleado_idEmpleado = e.idEmpleado
  WHERE a.Estado = 1
  ORDER BY a.periodo DESC, a.Fecha_Creacion DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Aguinaldo - Sistema RRHH</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .alert{ padding:12px; border-radius:8px; margin: 15px 0; }
    .alert-success{ background:#e7f6ea; border:1px solid #bfe6c8; }
    .alert-danger{ background:#fde7e7; border:1px solid #f3bcbc; }
    .btn{ display:inline-block; padding:10px 14px; border-radius:10px; text-decoration:none; border:0; cursor:pointer; }
    .btn-primary{ background:#1d4ed8; color:white; }
    table{ width:100%; border-collapse:collapse; margin-top:12px; }
    th,td{ padding:10px; border-bottom:1px solid #e5e7eb; text-align:left; }
    th{ background:#f9fafb; }
    .topbar{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
  </style>
</head>
<body>

<div class="sidebar">
  <h2>SISTEMA RRHH</h2>
  <ul>
    <li><a href="../dashboard.php">Dashboard</a></li>
    <li><a href="../Empleados/empleado.php">Empleados</a></li>
    <li><a href="../Planilla/planillas.php">Nómina</a></li>
    <li><a href="../Aguinaldo/aguinaldo.php">Aguinaldo</a></li>
    <li><a href="../Seguridad/logout.php">Cerrar sesión</a></li>
  </ul>
</div>

<div class="main-content">
  <div class="topbar">
    <h1>Aguinaldo</h1>

    <form action="generar_aguinaldo.php" method="POST"
          onsubmit="return confirm('¿Generar aguinaldo para el periodo <?= htmlspecialchars($periodoActual) ?>?');">
      <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodoActual) ?>">
      <button class="btn btn-primary" type="submit">Generar Aguinaldo (<?= $anioActual ?>)</button>
    </form>
  </div>

  <?php if ($mensaje): ?>
    <div class="alert <?= ($tipoMsg === 'danger') ? 'alert-danger' : 'alert-success' ?>">
      <?= htmlspecialchars($mensaje) ?>
    </div>
  <?php endif; ?>

  <p><strong>Cálculo:</strong> suma de <code>planillas.total_a_pagar</code> (últimos 12 meses) / 12.</p>

  <h2>Historial</h2>
  <table>
    <tr>
      <th>Empleado</th>
      <th>Periodo</th>
      <th>Monto</th>
      <th>Fecha creación</th>
    </tr>

    <?php if ($result && $result->num_rows > 0): ?>
      <?php while($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['nombre'] . " " . $row['apellidos']) ?></td>
          <td><?= htmlspecialchars($row['periodo']) ?></td>
          <td>₡<?= number_format((float)$row['monto_calculado'], 0, ',', '.') ?></td>
          <td><?= htmlspecialchars($row['Fecha_Creacion']) ?></td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="4">No hay aguinaldos registrados.</td></tr>
    <?php endif; ?>
  </table>
</div>

</body>
</html>