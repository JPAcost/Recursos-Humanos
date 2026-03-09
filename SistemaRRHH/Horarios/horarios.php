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

$usuarioSesion = $_SESSION['idUsuario'];

/* ============================
   FILTROS / BUSCADOR
============================ */
$buscar = $_GET['buscar'] ?? '';
$fechaFiltro = $_GET['fecha'] ?? '';

$where = "WHERE 1=1 ";
$params = [];
$types = "";

/* Buscar por tipo jornada */
if ($buscar !== '') {
    $where .= "AND tipo_jornada LIKE ? ";
    $params[] = "%$buscar%";
    $types .= "s";
}

/* Filtrar por fecha exacta */
if ($fechaFiltro !== '') {
    $where .= "AND fecha = ? ";
    $params[] = $fechaFiltro;
    $types .= "s";
}

/* ============================
   LISTADO
============================ */
$sql = "
SELECT idHorario, fecha, Hora_entrada, Hora_Salida, tipo_jornada, Estado
FROM horario
$where
ORDER BY fecha DESC, Hora_entrada ASC
";

$stmt = $conexion->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$lista = $stmt->get_result();

/* ============================
   MENSAJES (SESSION)
============================ */
$mensaje = $_SESSION['mensaje'] ?? null;
$tipoMsg = $_SESSION['tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Módulo Horarios</title>

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

        <!-- FORMULARIO -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Plantilla de Horarios (Crear por rango)</h4>
            </div>

            <div class="card-body">
                <form method="POST" action="guardar_horario.php" class="row g-3">

                    <div class="col-md-3">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Hora Entrada</label>
                        <input type="time" name="Hora_entrada" class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Hora Salida</label>
                        <input type="time" name="Hora_Salida" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo Jornada</label>
                        <select name="tipo_jornada" class="form-select" required>
                            <option value="normal">Normal</option>
                            <option value="feriado">Feriado</option>
                            <option value="descanso">Descanso</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Días a aplicar</label>
                        <select name="modo" class="form-select" required>
                            <option value="L_V">Solo Lunes a Viernes</option>
                            <option value="TODOS">Todos los días</option>
                        </select>
                    </div>

                    <div class="col-md-4 d-flex align-items-end form-actions">
                        <button type="submit" name="guardar" class="btn btn-success w-100">
                            Guardar Plantilla
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- LISTA -->
        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Horarios Registrados</h4>

                <form method="GET" class="d-flex gap-2 align-items-center search-form">
                    <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($fechaFiltro) ?>">
                    <input type="text" name="buscar" class="form-control" placeholder="Tipo jornada..."
                           value="<?= htmlspecialchars($buscar) ?>">
                    <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
                    <a href="horarios.php" class="btn btn-secondary btn-sm">Limpiar</a>
                </form>
            </div>

            <div class="card-body table-responsive">
                <table class="table table-striped table-hover align-middle" id="tablaHorarios">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($h = $lista->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$h['idHorario'] ?></td>
                            <td><?= date("d/m/Y", strtotime($h['fecha'])) ?></td>
                            <td><?= date("h:i A", strtotime($h['Hora_entrada'])) ?></td>
                            <td><?= date("h:i A", strtotime($h['Hora_Salida'])) ?></td>
                            <td>
                                <?php if($h['tipo_jornada'] == 'normal'): ?>
                                    <span class="badge bg-primary">Normal</span>
                                <?php elseif($h['tipo_jornada'] == 'feriado'): ?>
                                    <span class="badge bg-warning text-dark">Feriado</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">Descanso</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $h['Estado']
                                    ? "<span class='badge bg-success'>Activo</span>"
                                    : "<span class='badge bg-danger'>Inactivo</span>" ?>
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
</body>
</html>