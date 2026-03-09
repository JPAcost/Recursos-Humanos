<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

if (!isset($_SESSION['idRoles']) || ($_SESSION['idRoles'] != 1 && $_SESSION['idRoles'] != 2)) {
    echo "<div class='alert alert-danger m-4'>
            No tiene permisos para acceder al módulo de horarios.
          </div>";
    exit();
}

$query = "
SELECT 
    et.idAsignacion,
    e.nombre,
    e.apellidos,
    et.fecha,
    h.Hora_entrada,
    h.Hora_Salida,
    h.tipo_jornada,
    et.Estado
FROM empleado_turno et
INNER JOIN empleado e 
    ON et.idEmpleado = e.idEmpleado
INNER JOIN horario h 
    ON et.idTurno = h.idHorario
ORDER BY et.fecha DESC
";

$resultado = $conexion->query($query);

if (!$resultado) {
    die("Error en la consulta: " . $conexion->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Módulo Turnos</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
<div class="container-fluid mt-4">

<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Gestión de Turnos</h4>
        <a href="asignar_turno.php" class="btn btn-light btn-sm">
            + Asignar Turno
        </a>
    </div>
</div>

<div class="card shadow">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Lista de Turnos Asignados</h4>

        <form class="d-flex gap-2 search-form" onsubmit="return false;">
            <input type="text" id="buscarTurno" class="form-control" placeholder="Buscar turno...">
        </form>
    </div>

    <div class="card-body table-responsive">
        <table class="table table-striped table-hover align-middle" id="tablaTurnos">
            <thead class="table-dark">
                <tr>
                    <th>Empleado</th>
                    <th>Fecha</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>

            <?php while($row = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['nombre'] . " " . $row['apellidos']) ?></td>
                <td><?= date("d/m/Y", strtotime($row['fecha'])) ?></td>
                <td><?= date("h:i A", strtotime($row['Hora_entrada'])) ?></td>
                <td><?= date("h:i A", strtotime($row['Hora_Salida'])) ?></td>

                <td>
                <?php
                if($row['tipo_jornada'] == 'normal'){
                    echo "<span class='badge bg-primary'>Normal</span>";
                } elseif($row['tipo_jornada'] == 'feriado'){
                    echo "<span class='badge bg-warning text-dark'>Feriado</span>";
                } else {
                    echo "<span class='badge bg-info text-dark'>Descanso</span>";
                }
                ?>
                </td>

                <td>
                <?= $row['Estado']
                    ? "<span class='badge bg-success'>Activo</span>"
                    : "<span class='badge bg-danger'>Anulado</span>" ?>
                </td>

                <td class="acciones-btn">
                    <?php if($row['Estado']): ?>
                    <a href="anular_turno.php?id=<?= (int)$row['idAsignacion'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('¿Desea anular este turno?');">
                        Anular
                    </a>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>

            </tbody>
        </table>
    </div>
</div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById("buscarTurno").addEventListener("keyup", function() {
    let filtro = this.value.toLowerCase();
    let filas = document.querySelectorAll("#tablaTurnos tbody tr");

    filas.forEach(function(fila) {
        let texto = fila.innerText.toLowerCase();
        fila.style.display = texto.includes(filtro) ? "" : "none";
    });
});
</script>

</body>
</html>