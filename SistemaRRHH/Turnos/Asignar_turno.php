<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$usuario = $_SESSION['idUsuario'];

$empleados = $conexion->query("
    SELECT idEmpleado, nombre, apellidos
    FROM empleado
    WHERE Estado = 'Activo'
    ORDER BY nombre ASC
");

$horarios = $conexion->query("
    SELECT idHorario, fecha, Hora_entrada, Hora_Salida, tipo_jornada
    FROM horario
    WHERE Estado = 1
    ORDER BY fecha DESC, Hora_entrada ASC
");

$mensaje = $_SESSION['mensaje'] ?? null;
$tipoMsg = $_SESSION['tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Turno</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
<div class="container-fluid mt-4">

<?php if($mensaje): ?>
<div class="alert alert-<?= htmlspecialchars($tipoMsg) ?> alert-dismissible fade show">
    <?= htmlspecialchars($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">Asignar Turno Manual</h4>
    </div>

    <div class="card-body">
        <form method="POST" action="guardar_turno.php" class="row g-3">

            <div class="col-md-4">
                <label class="form-label">Empleado</label>
                <select name="idEmpleado" class="form-select" required>
                    <option value="">Seleccione</option>
                    <?php while($e = $empleados->fetch_assoc()): ?>
                        <option value="<?= (int)$e['idEmpleado'] ?>">
                            <?= htmlspecialchars($e['nombre']." ".$e['apellidos']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Fecha (para asignación)</label>
                <input type="date" name="fecha" class="form-control" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Horario disponible (plantilla)</label>
                <select name="idHorario" class="form-select" required>
                    <option value="">Seleccione</option>
                    <?php while($h = $horarios->fetch_assoc()): ?>
                        <option value="<?= (int)$h['idHorario'] ?>">
                            <?= date("d/m/Y", strtotime($h['fecha'])) ?>
                            | <?= date("h:i A", strtotime($h['Hora_entrada'])) ?> - <?= date("h:i A", strtotime($h['Hora_Salida'])) ?>
                            | <?= htmlspecialchars($h['tipo_jornada']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-12 form-actions">
                <button type="submit" name="guardar" class="btn btn-success px-4">Guardar</button>
                <a href="Listar_turnos.php" class="btn btn-secondary">Volver</a>
            </div>

        </form>
    </div>
</div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>