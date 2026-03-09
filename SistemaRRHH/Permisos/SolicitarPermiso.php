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

if (!can('Permisos_ver_empleado')) {
    echo "<div class='alert danger' style='margin:20px'>No tiene permisos para solicitar permisos.</div>";
    exit();
}

$tipos = $conexion->query("SELECT idTipo_permiso, tipo FROM tipo_permiso WHERE Estado = 1 ORDER BY tipo ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Solicitar Permiso</title>
  <link rel="stylesheet" href="../css/permisos.css">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
  <h1>Solicitar Permiso</h1>

  <div class="form-box">
    <form method="POST" action="GuardarPermiso.php" class="grid">
      <div class="col-6">
        <label>Tipo de permiso</label>
        <select name="tipo_permiso" required>
          <option value="">Seleccione...</option>
          <?php while($t = $tipos->fetch_assoc()): ?>
            <option value="<?php echo (int)$t['idTipo_permiso']; ?>">
              <?php echo htmlspecialchars($t['tipo']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-3">
        <label>Fecha inicio</label>
        <input class="input" type="date" name="fecha_inicio" required>
      </div>

      <div class="col-3">
        <label>Fecha fin</label>
        <input class="input" type="date" name="fecha_fin" required>
      </div>

      <div class="col-12">
        <label>Motivo</label>
        <textarea name="motivo" rows="3" maxlength="255" required></textarea>
      </div>

      <div class="col-12" style="display:flex; gap:10px;">
        <button class="btn" type="submit" name="guardar">Enviar a jefatura</button>
        <a class="btn" href="MisPermisos.php" style="background:#6b7280;">Volver</a>
      </div>
    </form>
  </div>

</div>
</body>
</html>