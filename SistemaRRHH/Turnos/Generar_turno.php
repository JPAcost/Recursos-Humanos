<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$usuario = (int)$_SESSION['idUsuario'];

/* ===============================
   FERIADOS CR (básico)
================================ */
function esFeriadoCR($fecha){
    $anio = date("Y", strtotime($fecha));
    $feriados = [
        "$anio-01-01",
        "$anio-04-11",
        "$anio-05-01",
        "$anio-07-25",
        "$anio-08-15",
        "$anio-09-15",
        "$anio-12-25"
    ];
    return in_array($fecha, $feriados);
}

/* ===============================
   PROCESO GENERADOR
================================ */
if (isset($_POST['generar'])) {

    $fechaInicio = $_POST['fecha_inicio'] ?? null;
    $fechaFin    = $_POST['fecha_fin'] ?? null;
    $modo        = $_POST['modo'] ?? 'L_V';
    $preview     = isset($_POST['preview']) ? 1 : 0;

    try {
        if (!$fechaInicio || !$fechaFin) {
            throw new Exception("Debe indicar Fecha Inicio y Fecha Fin.");
        }

        if ($fechaInicio > $fechaFin) {
            throw new Exception("La fecha inicio no puede ser mayor que la fecha fin.");
        }

        $fechaActual      = $fechaInicio;
        $totalCreados     = 0;
        $totalOmitidos    = 0;
        $totalPermisos    = 0;
        $totalSinHorario  = 0;
        $simulados        = [];

        while ($fechaActual <= $fechaFin) {

            if ($modo === "L_V") {
                $diaSemana = date('N', strtotime($fechaActual));
                if ($diaSemana >= 6) {
                    $fechaActual = date("Y-m-d", strtotime("+1 day", strtotime($fechaActual)));
                    continue;
                }
            }

            $tipoJornadaBuscada = esFeriadoCR($fechaActual) ? 'feriado' : 'normal';

            $horaEntradaBase = '08:00:00';
            $horaSalidaBase  = '17:00:00';

            $empleados = $conexion->query("
                SELECT idEmpleado
                FROM empleado
                WHERE Estado = 'Activo'
            ");

            if (!$empleados) {
                throw new Exception("Error al obtener empleados: " . $conexion->error);
            }

            while ($emp = $empleados->fetch_assoc()) {

                $idEmpleado = (int)$emp['idEmpleado'];

                $stmtPermiso = $conexion->prepare("
                    SELECT idPermiso
                    FROM permiso
                    WHERE Empleado_idEmpleado = ?
                      AND estado = 1
                      AND ? BETWEEN fecha_inicio AND fecha_fin
                    LIMIT 1
                ");

                if (!$stmtPermiso) {
                    throw new Exception("Error al preparar consulta de permiso: " . $conexion->error);
                }

                $stmtPermiso->bind_param("is", $idEmpleado, $fechaActual);
                $stmtPermiso->execute();
                $permiso = $stmtPermiso->get_result();

                if ($permiso && $permiso->num_rows > 0) {
                    $totalPermisos++;
                    continue;
                }

                $stmtExiste = $conexion->prepare("
                    SELECT idAsignacion
                    FROM empleado_turno
                    WHERE idEmpleado = ?
                      AND fecha = ?
                      AND Estado = 1
                    LIMIT 1
                ");

                if (!$stmtExiste) {
                    throw new Exception("Error al preparar validación de turno: " . $conexion->error);
                }

                $stmtExiste->bind_param("is", $idEmpleado, $fechaActual);
                $stmtExiste->execute();
                $existe = $stmtExiste->get_result();

                if ($existe && $existe->num_rows > 0) {
                    $totalOmitidos++;
                    continue;
                }

                $stmtHorario = $conexion->prepare("
                    SELECT idHorario
                    FROM horario
                    WHERE fecha = ?
                      AND tipo_jornada = ?
                      AND Hora_entrada = ?
                      AND Hora_Salida = ?
                      AND Estado = 1
                    LIMIT 1
                ");

                if (!$stmtHorario) {
                    throw new Exception("Error al preparar búsqueda de horario: " . $conexion->error);
                }

                $stmtHorario->bind_param(
                    "ssss",
                    $fechaActual,
                    $tipoJornadaBuscada,
                    $horaEntradaBase,
                    $horaSalidaBase
                );
                $stmtHorario->execute();
                $horarioData = $stmtHorario->get_result()->fetch_assoc();

                if ($horarioData) {
                    $idHorario = (int)$horarioData['idHorario'];
                } else {
                    $stmtCrearHorario = $conexion->prepare("
                        INSERT INTO horario (
                            fecha,
                            Hora_entrada,
                            Hora_Salida,
                            tipo_jornada,
                            Fecha_creacion,
                            Usuario_creacion,
                            Fecha_modificacion,
                            Usuario_modificacion,
                            Estado
                        ) VALUES (?, ?, ?, ?, NOW(), ?, NOW(), ?, 1)
                    ");

                    if (!$stmtCrearHorario) {
                        throw new Exception("Error al preparar creación de horario: " . $conexion->error);
                    }

                    $stmtCrearHorario->bind_param(
                        "ssssii",
                        $fechaActual,
                        $horaEntradaBase,
                        $horaSalidaBase,
                        $tipoJornadaBuscada,
                        $usuario,
                        $usuario
                    );

                    if (!$stmtCrearHorario->execute()) {
                        throw new Exception("No se pudo crear el horario: " . $stmtCrearHorario->error);
                    }

                    $idHorario = (int)$stmtCrearHorario->insert_id;
                }

                if ($idHorario <= 0) {
                    $totalSinHorario++;
                    continue;
                }

                if ($preview) {
                    $simulados[] = [
                        'idEmpleado' => $idEmpleado,
                        'fecha'      => $fechaActual,
                        'idTurno'    => $idHorario,
                        'tipo'       => $tipoJornadaBuscada
                    ];
                    continue;
                }

                $stmt = $conexion->prepare("
                    INSERT INTO empleado_turno (idEmpleado, idTurno, fecha, Estado)
                    VALUES (?, ?, ?, 1)
                ");

                if (!$stmt) {
                    throw new Exception("Error al preparar inserción de turno: " . $conexion->error);
                }

                $stmt->bind_param("iis", $idEmpleado, $idHorario, $fechaActual);

                if (!$stmt->execute()) {
                    throw new Exception("Error al insertar turno: " . $stmt->error);
                }

                $totalCreados++;
            }

            $fechaActual = date("Y-m-d", strtotime("+1 day", strtotime($fechaActual)));
        }

        if ($preview) {
            $_SESSION['mensaje'] = "PREVIEW: Se generarían " . count($simulados) . " turnos. (No se guardó nada)";
            $_SESSION['tipo'] = "info";
            $_SESSION['preview_turnos'] = $simulados;
        } else {
            $_SESSION['mensaje'] = "Turnos generados. Creados: $totalCreados | Omitidos: $totalOmitidos | Con permiso: $totalPermisos | Sin horario: $totalSinHorario";
            $_SESSION['tipo'] = "success";
            unset($_SESSION['preview_turnos']);
        }

        header("Location: Generar_turno.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['mensaje'] = $e->getMessage();
        $_SESSION['tipo'] = "danger";
        header("Location: Generar_turno.php");
        exit();
    }
}

$previewData = $_SESSION['preview_turnos'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar Turnos</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
<div class="container-fluid mt-4">

<?php if(isset($_SESSION['mensaje'])): ?>
<div class="alert alert-<?= htmlspecialchars($_SESSION['tipo']) ?> alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['mensaje']); unset($_SESSION['mensaje'], $_SESSION['tipo']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">Generador Automático de Turnos</h4>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">

            <div class="col-md-3">
                <label class="form-label">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha Fin</label>
                <input type="date" name="fecha_fin" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Modo</label>
                <select name="modo" class="form-select" required>
                    <option value="L_V">Solo Lunes a Viernes</option>
                    <option value="TODOS">Todos los días</option>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="preview" id="preview" value="1">
                    <label class="form-check-label" for="preview">
                        Previsualizar (no guardar)
                    </label>
                </div>
            </div>

            <div class="col-12 form-actions">
                <button type="submit" name="generar" class="btn btn-success px-4">
                    Generar Turnos
                </button>
                <a href="Listar_turnos.php" class="btn btn-secondary">
                    Ver Turnos Asignados
                </a>
            </div>

        </form>
    </div>
</div>

<?php if(!empty($previewData)): ?>
<div class="card shadow">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Previsualización de Turnos</h4>

        <form class="d-flex gap-2 search-form" onsubmit="return false;">
            <input type="text" id="buscarPreview" class="form-control" placeholder="Buscar...">
        </form>
    </div>

    <div class="card-body table-responsive">
        <table class="table table-striped table-hover align-middle" id="tablaPreview">
            <thead class="table-dark">
                <tr>
                    <th>Empleado ID</th>
                    <th>Fecha</th>
                    <th>Turno(ID Horario)</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($previewData as $p): ?>
                <tr>
                    <td><?= (int)$p['idEmpleado'] ?></td>
                    <td><?= htmlspecialchars($p['fecha']) ?></td>
                    <td><?= (int)$p['idTurno'] ?></td>
                    <td>
                        <?= $p['tipo'] == 'feriado'
                            ? "<span class='badge bg-warning text-dark'>Feriado</span>"
                            : "<span class='badge bg-primary'>Normal</span>" ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const input = document.getElementById("buscarPreview");
if (input){
    input.addEventListener("keyup", function(){
        const filtro = this.value.toLowerCase();
        const filas = document.querySelectorAll("#tablaPreview tbody tr");
        filas.forEach(f => {
            const txt = f.innerText.toLowerCase();
            f.style.display = txt.includes(filtro) ? "" : "none";
        });
    });
}
</script>
</body>
</html>