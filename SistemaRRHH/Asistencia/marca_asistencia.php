<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$fechaHoy = date("Y-m-d");
$idUsuario = (int)$_SESSION['idUsuario'];

// Mensajes desde guardar_marca.php
$mensaje = $_SESSION['mensaje'] ?? null;
$tipoMsg = $_SESSION['tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo']);

/* ===============================
   OBTENER EMPLEADO DEL USUARIO LOGUEADO
================================= */
$stmtEmpleado = $conexion->prepare("
    SELECT idEmpleado, nombre, apellidos
    FROM empleado
    WHERE Usuario_idUsuario = ?
      AND Estado = 'Activo'
    LIMIT 1
");

if (!$stmtEmpleado) {
    die("Error al preparar empleado: " . $conexion->error);
}

$stmtEmpleado->bind_param("i", $idUsuario);
$stmtEmpleado->execute();
$resEmpleado = $stmtEmpleado->get_result();
$empleado = $resEmpleado->fetch_assoc();

if (!$empleado) {
    die("No se encontró un empleado activo asociado al usuario actual.");
}

$idEmpleado = (int)$empleado['idEmpleado'];
$nombreCompleto = trim($empleado['nombre'] . " " . $empleado['apellidos']);

/* ===============================
   OBTENER MARCAS DEL DÍA
   SOLO DEL USUARIO LOGUEADO
================================= */
$stmtMarcas = $conexion->prepare("
    SELECT 
        c.idControl_de_Asistencia,
        c.Hora,
        t.Tipo
    FROM control_asistencia c
    INNER JOIN tipo_de_marca t 
        ON c.Tipo_de_marca_idTipo_de_marca = t.idTipo_de_marca
    WHERE c.Fecha = CURDATE()
      AND c.Empleado_idEmpleado = ?
    ORDER BY c.Hora DESC
");

if (!$stmtMarcas) {
    die("Error al preparar marcas: " . $conexion->error);
}

$stmtMarcas->bind_param("i", $idEmpleado);
$stmtMarcas->execute();
$marcas = $stmtMarcas->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Marca de Asistencia</title>
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
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">Registro de Marca</h4>
            </div>

            <div class="card-body">
                <form method="POST" action="guardar_marca.php" class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Empleado</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($nombreCompleto) ?>" readonly>
                        <input type="hidden" name="idEmpleado" value="<?= $idEmpleado ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Modo</label>
                        <select name="modo_hora" class="form-select" id="modo_hora">
                            <option value="real" selected>Hora real</option>
                            <option value="demo">Hora demo</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Hora demo</label>
                        <input type="time" name="hora_demo" id="hora_demo" class="form-control" value="08:00">
                        <div class="form-text">Solo se usa si eliges “Hora demo”.</div>
                    </div>

                    <div class="col-md-6 d-flex align-items-end form-actions">
                        <button type="submit" class="btn btn-success w-100">
                            Registrar Marca
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Mis Marcas del Día (<?= htmlspecialchars($fechaHoy); ?>)</h4>

                <form class="d-flex gap-2 search-form" onsubmit="return false;">
                    <input type="text" id="buscarMarca" class="form-control" placeholder="Buscar...">
                </form>
            </div>

            <div class="card-body table-responsive">
                <table class="table table-striped table-hover align-middle" id="tablaMarcas">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Empleado</th>
                            <th>Tipo</th>
                            <th>Hora</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if($marcas->num_rows == 0): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No has registrado marcas hoy.</td>
                        </tr>
                    <?php else: ?>
                        <?php while($fila = $marcas->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$fila['idControl_de_Asistencia']; ?></td>
                                <td><?= htmlspecialchars($nombreCompleto); ?></td>
                                <td>
                                    <?php if($fila['Tipo'] === "Entrada"): ?>
                                        <span class="badge bg-primary">Entrada</span>
                                    <?php elseif($fila['Tipo'] === "Salida"): ?>
                                        <span class="badge bg-danger">Salida</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($fila['Tipo']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($fila['Hora']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const modo = document.getElementById("modo_hora");
const hora = document.getElementById("hora_demo");

function toggleHora() {
    const demo = modo.value === "demo";
    hora.disabled = !demo;
}
modo.addEventListener("change", toggleHora);
toggleHora();

document.getElementById("buscarMarca").addEventListener("keyup", function() {
    let filtro = this.value.toLowerCase();
    let filas = document.querySelectorAll("#tablaMarcas tbody tr");

    filas.forEach(function(fila) {
        let texto = fila.innerText.toLowerCase();
        fila.style.display = texto.includes(filtro) ? "" : "none";
    });
});
</script>

</body>
</html>