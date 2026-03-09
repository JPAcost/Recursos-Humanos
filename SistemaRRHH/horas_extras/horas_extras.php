<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

if (!isset($_SESSION['idRoles']) || ($_SESSION['idRoles'] != 1 && $_SESSION['idRoles'] != 2)) {
    echo "<div class='alert alert-danger m-4'>No tiene permisos para acceder al módulo de horas extras.</div>";
    exit();
}

$usuarioSesion   = (int)($_SESSION['idUsuario'] ?? 0);
$puedeMantener   = (int)($_SESSION['Mantenimiento_RH'] ?? 0) === 1;
$MULT_NORMAL     = 1.5;
$MULT_FERIADO    = 2.0;

function postv($k, $d = '') { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function getv($k, $d = '')  { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

function esFeriadoCR($fecha){
    $anio = date("Y", strtotime($fecha));
    $feriados = [
        "$anio-01-01", "$anio-04-11", "$anio-05-01",
        "$anio-07-25", "$anio-08-15", "$anio-09-15", "$anio-12-25"
    ];
    return in_array($fecha, $feriados, true);
}

function timeToSeconds($hora){
    $p = explode(':', $hora);
    if (count($p) !== 3) throw new Exception("Hora inválida: $hora");
    return ((int)$p[0] * 3600) + ((int)$p[1] * 60) + (int)$p[2];
}

function secondsToHours($segundos){
    return round($segundos / 3600, 2);
}

function calcularMonto(mysqli $cn, int $idEmpleado, float $horas, string $tipo, float $multNormal, float $multFeriado): float {
    $st = $cn->prepare("SELECT salario_hora FROM empleado WHERE idEmpleado = ?");
    if (!$st) throw new Exception("Error al obtener salario_hora: " . $cn->error);

    $st->bind_param("i", $idEmpleado);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if (!$row || !isset($row['salario_hora'])) {
        throw new Exception("No se encontró salario por hora del empleado.");
    }

    $salarioHora = (float)$row['salario_hora'];
    if ($salarioHora <= 0) throw new Exception("El salario por hora no es válido.");

    $mult = ($tipo === 'feriado') ? $multFeriado : $multNormal;
    return round($salarioHora * $horas * $mult, 2);
}

function obtenerControlReferencia(mysqli $cn, int $idEmpleado, string $fecha): array {
    $st = $cn->prepare("
        SELECT MAX(idControl_de_Asistencia) AS max_id
        FROM control_asistencia
        WHERE Empleado_idEmpleado = ? AND Fecha = ?
    ");
    if (!$st) throw new Exception("Error al obtener referencia de control: " . $cn->error);

    $st->bind_param("is", $idEmpleado, $fecha);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    $idControl = (int)($row['max_id'] ?? 0);
    if ($idControl <= 0) throw new Exception("No existe referencia válida de control de asistencia.");

    return [
        'control_asistencia_id' => $idControl,
        'control_asistencia_fecha' => $fecha
    ];
}

function calcularHorasExtrasAutomaticas(mysqli $cn, int $idEmpleado, string $fecha): array {
    if ($idEmpleado <= 0) throw new Exception("Empleado inválido.");
    if ($fecha === '') throw new Exception("La fecha es obligatoria.");

    $st = $cn->prepare("
        SELECT Hora, Horario_idHorario, Tipo_de_marca_idTipo_de_marca
        FROM control_asistencia
        WHERE Empleado_idEmpleado = ? AND Fecha = ?
        ORDER BY Hora ASC
    ");
    if (!$st) throw new Exception("Error al consultar marcas: " . $cn->error);

    $st->bind_param("is", $idEmpleado, $fecha);
    $st->execute();
    $marcas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!$marcas || count($marcas) < 2) {
        throw new Exception("No hay suficientes marcas para calcular horas extra.");
    }

    $entradaReal = '';
    $salidaReal  = '';
    $horarioId   = 0;

    foreach ($marcas as $m) {
        $tipo = (int)($m['Tipo_de_marca_idTipo_de_marca'] ?? 0);
        $hora = trim((string)($m['Hora'] ?? ''));

        if ($hora === '') continue;

        if ($horarioId <= 0 && (int)($m['Horario_idHorario'] ?? 0) > 0) {
            $horarioId = (int)$m['Horario_idHorario'];
        }

        if ($tipo === 1 && $entradaReal === '') $entradaReal = $hora; // Entrada
        if ($tipo === 4) $salidaReal = $hora; // Salida
    }

    if ($entradaReal === '' || $salidaReal === '') {
        throw new Exception("No se encontró una Entrada y una Salida válidas.");
    }

    if ($horarioId <= 0) {
        $stTurno = $cn->prepare("
            SELECT idTurno
            FROM empleado_turno
            WHERE idEmpleado = ? AND fecha = ? AND Estado = 1
            LIMIT 1
        ");
        if (!$stTurno) throw new Exception("Error al consultar turno: " . $cn->error);

        $stTurno->bind_param("is", $idEmpleado, $fecha);
        $stTurno->execute();
        $turno = $stTurno->get_result()->fetch_assoc();

        if (!$turno) throw new Exception("No se encontró turno asignado para esa fecha.");
        $horarioId = (int)$turno['idTurno'];
    }

    $stHorario = $cn->prepare("
        SELECT Hora_entrada, Hora_Salida, tipo_jornada
        FROM horario
        WHERE idHorario = ? AND Estado = 1
        LIMIT 1
    ");
    if (!$stHorario) throw new Exception("Error al consultar horario: " . $cn->error);

    $stHorario->bind_param("i", $horarioId);
    $stHorario->execute();
    $horario = $stHorario->get_result()->fetch_assoc();

    if (!$horario) throw new Exception("No se encontró el horario pactado.");

    $entradaPactada = trim((string)($horario['Hora_entrada'] ?? ''));
    $salidaPactada  = trim((string)($horario['Hora_Salida'] ?? ''));
    $tipoJornada    = trim((string)($horario['tipo_jornada'] ?? ''));

    if ($entradaPactada === '' || $salidaPactada === '') {
        throw new Exception("El horario pactado está incompleto.");
    }

    $secEntradaReal = timeToSeconds($entradaReal);
    $secSalidaReal  = timeToSeconds($salidaReal);
    if ($secSalidaReal <= $secEntradaReal) {
        throw new Exception("La salida real no puede ser menor o igual a la entrada.");
    }

    $secEntradaPactada = timeToSeconds($entradaPactada);
    $secSalidaPactada  = timeToSeconds($salidaPactada);
    if ($secSalidaPactada <= $secEntradaPactada) {
        throw new Exception("La jornada pactada no es válida.");
    }

    $trabajadoSeg = $secSalidaReal - $secEntradaReal;
    $jornadaSeg   = $secSalidaPactada - $secEntradaPactada;
    $esFeriado    = esFeriadoCR($fecha) || strtolower($tipoJornada) === 'feriado';

    if ($esFeriado) {
        $segundosExtra = $trabajadoSeg;
        $tipo = 'feriado';
    } else {
        $segundosExtra = max(0, $trabajadoSeg - $jornadaSeg);
        $tipo = 'normal';
    }

    $horasExtra = secondsToHours($segundosExtra);
    if ($horasExtra <= 0) {
        throw new Exception("No se detectaron horas extra para esa jornada.");
    }

    $ref = obtenerControlReferencia($cn, $idEmpleado, $fecha);

    return [
        'cantidad_horas' => $horasExtra,
        'tipo' => $tipo,
        'control_asistencia_id' => (int)$ref['control_asistencia_id'],
        'control_asistencia_fecha' => (string)$ref['control_asistencia_fecha']
    ];
}

$msg = getv('msg', '');
$err = getv('err', '');
$editar = false;
$datosEditar = [];

/* EDITAR */
if (isset($_GET['editar'])) {
    $editar = true;
    $id = (int)getv('editar', 0);

    $st = $conexion->prepare("SELECT * FROM horas_extras WHERE idHoras_Extras = ?");
    if ($st) {
        $st->bind_param("i", $id);
        $st->execute();
        $datosEditar = $st->get_result()->fetch_assoc() ?: [];
    }

    if (!$datosEditar) {
        header("Location: horas_extras.php?err=" . urlencode("Registro no encontrado."));
        exit();
    }
}

/* ELIMINAR */
if (isset($_GET['eliminar'])) {
    if (!$puedeMantener) {
        header("Location: horas_extras.php?err=" . urlencode("No tiene permisos de mantenimiento."));
        exit();
    }

    $id = (int)getv('eliminar', 0);
    $st = $conexion->prepare("DELETE FROM horas_extras WHERE idHoras_Extras = ?");
    if (!$st) {
        header("Location: horas_extras.php?err=" . urlencode("Error al preparar eliminación."));
        exit();
    }

    $st->bind_param("i", $id);
    if (!$st->execute()) {
        header("Location: horas_extras.php?err=" . urlencode("Error al eliminar: " . $st->error));
        exit();
    }

    header("Location: horas_extras.php?msg=" . urlencode("Registro eliminado."));
    exit();
}

/* GUARDAR / ACTUALIZAR */
if (isset($_POST['guardar']) || isset($_POST['actualizar'])) {
    if (!$puedeMantener) {
        header("Location: horas_extras.php?err=" . urlencode("No tiene permisos de mantenimiento."));
        exit();
    }

    $empleadoId = (int)postv('empleado_id', '0');
    $fecha      = postv('fecha_jornada');

    if ($empleadoId <= 0 || $fecha === '') {
        header("Location: horas_extras.php?err=" . urlencode("Seleccione una jornada válida."));
        exit();
    }

    try {
        $calc     = calcularHorasExtrasAutomaticas($conexion, $empleadoId, $fecha);
        $cantidad = (float)$calc['cantidad_horas'];
        $tipo     = $calc['tipo'];
        $caId     = (int)$calc['control_asistencia_id'];
        $caFecha  = $calc['control_asistencia_fecha'];
        $monto    = calcularMonto($conexion, $empleadoId, $cantidad, $tipo, $MULT_NORMAL, $MULT_FERIADO);

        if (isset($_POST['guardar'])) {
            $dup = $conexion->prepare("
                SELECT idHoras_Extras
                FROM horas_extras
                WHERE empleado_id = ? AND fecha = ?
                LIMIT 1
            ");
            if (!$dup) throw new Exception("Error al validar duplicado.");

            $dup->bind_param("is", $empleadoId, $fecha);
            $dup->execute();

            if ($dup->get_result()->num_rows > 0) {
                throw new Exception("Ya existe un registro de horas extra para ese empleado en esa fecha.");
            }

            $st = $conexion->prepare("
                INSERT INTO horas_extras
                (fecha, cantidad_horas, tipo, monto_calculado, control_asistencia_id, control_asistencia_fecha, empleado_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$st) throw new Exception("Error al preparar inserción.");

            $st->bind_param("sdsdisi", $fecha, $cantidad, $tipo, $monto, $caId, $caFecha, $empleadoId);

            if (!$st->execute()) throw new Exception("Error al insertar: " . $st->error);

            header("Location: horas_extras.php?msg=" . urlencode("Horas extra guardadas. Horas: {$cantidad} | Tipo: {$tipo} | Monto: ₡" . number_format($monto, 2, ',', '.')));
            exit();
        }

        $idHoras = (int)($_POST['idHoras_Extras'] ?? 0);
        if ($idHoras <= 0) throw new Exception("ID inválido.");

        $st = $conexion->prepare("
            UPDATE horas_extras SET
                fecha = ?, cantidad_horas = ?, tipo = ?, monto_calculado = ?,
                control_asistencia_id = ?, control_asistencia_fecha = ?, empleado_id = ?
            WHERE idHoras_Extras = ?
        ");
        if (!$st) throw new Exception("Error al preparar actualización.");

        $st->bind_param("sdsdisii", $fecha, $cantidad, $tipo, $monto, $caId, $caFecha, $empleadoId, $idHoras);

        if (!$st->execute()) throw new Exception("Error al actualizar: " . $st->error);

        header("Location: horas_extras.php?msg=" . urlencode("Registro actualizado. Horas: {$cantidad} | Tipo: {$tipo} | Monto: ₡" . number_format($monto, 2, ',', '.')));
        exit();

    } catch (Exception $e) {
        header("Location: horas_extras.php?err=" . urlencode($e->getMessage()));
        exit();
    }
}

/* JORNADAS DISPONIBLES */
$jornadas = [];
$rs = $conexion->query("
    SELECT 
        ca.Empleado_idEmpleado AS empleado_id,
        ca.Fecha AS fecha_jornada,
        e.nombre,
        e.apellidos,
        e.cedula,
        COUNT(*) AS total_marcas
    FROM control_asistencia ca
    INNER JOIN empleado e ON e.idEmpleado = ca.Empleado_idEmpleado
    GROUP BY ca.Empleado_idEmpleado, ca.Fecha, e.nombre, e.apellidos, e.cedula
    HAVING COUNT(*) >= 2
    ORDER BY ca.Fecha DESC, e.nombre ASC
    LIMIT 150
");
if ($rs) {
    while ($row = $rs->fetch_assoc()) $jornadas[] = $row;
}

/* LISTADO */
$buscar = getv('buscar', '');
$limite = 8;
$pagina = max(1, (int)getv('pagina', 1));
$inicio = ($pagina - 1) * $limite;
$like   = "%{$buscar}%";

$stTotal = $conexion->prepare("
    SELECT COUNT(*) total
    FROM horas_extras he
    JOIN empleado e ON e.idEmpleado = he.empleado_id
    WHERE e.nombre LIKE ? OR e.apellidos LIKE ? OR e.cedula LIKE ? OR he.tipo LIKE ? OR he.fecha LIKE ?
");
$stTotal->bind_param("sssss", $like, $like, $like, $like, $like);
$stTotal->execute();
$total = (int)($stTotal->get_result()->fetch_assoc()['total'] ?? 0);
$totalPaginas = max(1, (int)ceil($total / $limite));

$stList = $conexion->prepare("
    SELECT he.*, e.nombre, e.apellidos, e.cedula
    FROM horas_extras he
    JOIN empleado e ON e.idEmpleado = he.empleado_id
    WHERE e.nombre LIKE ? OR e.apellidos LIKE ? OR e.cedula LIKE ? OR he.tipo LIKE ? OR he.fecha LIKE ?
    ORDER BY he.idHoras_Extras DESC
    LIMIT ?, ?
");
$stList->bind_param("sssssii", $like, $like, $like, $like, $like, $inicio, $limite);
$stList->execute();
$listado = $stList->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Horas Extras</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <div class="container-fluid mt-4">

        <?php if($msg): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($err): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($err); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><?= $editar ? "Editar Horas Extras" : "Registrar Horas Extras"; ?></h4>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">

                    <?php if($editar): ?>
                        <input type="hidden" name="idHoras_Extras" value="<?= (int)$datosEditar['idHoras_Extras']; ?>">
                    <?php endif; ?>

                    <div class="col-md-12">
                        <label class="form-label">Seleccione jornada</label>
                        <select class="form-select" id="jornada_select" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($jornadas as $j):
                                $val = $j['empleado_id'] . "|" . $j['fecha_jornada'];
                                $sel = "";
                                if ($editar && ((int)$datosEditar['empleado_id'] . "|" . $datosEditar['fecha']) === $val) {
                                    $sel = "selected";
                                }
                            ?>
                                <option value="<?= htmlspecialchars($val); ?>" <?= $sel; ?>>
                                    <?= htmlspecialchars($j['fecha_jornada'] . " - " . $j['nombre'] . " " . $j['apellidos'] . " (" . $j['cedula'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input type="hidden" name="empleado_id" id="empleado_id" value="<?= $editar ? (int)$datosEditar['empleado_id'] : ''; ?>">
                        <input type="hidden" name="fecha_jornada" id="fecha_jornada" value="<?= $editar ? htmlspecialchars($datosEditar['fecha']) : ''; ?>">

                       
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Fecha</label>
                        <input type="text" class="form-control" readonly value="<?= $editar ? htmlspecialchars($datosEditar['fecha']) : 'Automática'; ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Horas extra</label>
                        <input type="text" class="form-control" readonly value="<?= $editar ? htmlspecialchars($datosEditar['cantidad_horas']) : 'Automática'; ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <input type="text" class="form-control" readonly value="<?= $editar ? htmlspecialchars($datosEditar['tipo']) : 'Automático'; ?>">
                        <div class="form-text">Normal x<?= $MULT_NORMAL; ?> / Feriado x<?= $MULT_FERIADO; ?></div>
                    </div>

                    <div class="col-12 form-actions">
                        <?php if($editar): ?>
                            <button type="submit" name="actualizar" class="btn btn-warning" <?= !$puedeMantener ? "disabled" : ""; ?>>Actualizar</button>
                            <a href="horas_extras.php" class="btn btn-secondary">Cancelar</a>
                        <?php else: ?>
                            <button type="submit" name="guardar" class="btn btn-success" <?= !$puedeMantener ? "disabled" : ""; ?>>Guardar</button>
                        <?php endif; ?>

                        <?php if(!$puedeMantener): ?>
                            <div class="text-muted mt-2">No tienes permiso Mantenimiento_RH.</div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Listado de Horas Extras</h4>
                <form method="GET" class="d-flex gap-2 search-form">
                    <input type="text" name="buscar" class="form-control" style="width: 320px" placeholder="Buscar..." value="<?= htmlspecialchars($buscar); ?>">
                    <button class="btn btn-light" type="submit">Buscar</button>
                </form>
            </div>

            <div class="card-body table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Empleado</th>
                            <th>Cédula</th>
                            <th>Fecha</th>
                            <th>Horas</th>
                            <th>Tipo</th>
                            <th>Monto</th>
                            <th>Ref.</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($r = $listado->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$r['idHoras_Extras']; ?></td>
                            <td><?= htmlspecialchars($r['nombre']." ".$r['apellidos']); ?></td>
                            <td><?= htmlspecialchars($r['cedula']); ?></td>
                            <td><?= htmlspecialchars($r['fecha']); ?></td>
                            <td><?= htmlspecialchars($r['cantidad_horas']); ?></td>
                            <td><?= htmlspecialchars($r['tipo']); ?></td>
                            <td>₡<?= number_format((float)$r['monto_calculado'], 2, ',', '.'); ?></td>
                            <td>#<?= (int)$r['control_asistencia_id']; ?></td>
                            <td class="acciones-btn d-flex gap-2">
                                <a class="btn btn-warning btn-sm" href="?editar=<?= (int)$r['idHoras_Extras']; ?>">Editar</a>
                                <a class="btn btn-danger btn-sm <?= !$puedeMantener ? "disabled" : ""; ?>"
                                   href="?eliminar=<?= (int)$r['idHoras_Extras']; ?>"
                                   onclick="return confirm('¿Eliminar este registro?');">
                                    Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

                <nav>
                    <ul class="pagination">
                        <?php
                        $base = "horas_extras.php?buscar=" . urlencode($buscar) . "&pagina=";
                        for($p=1; $p<=$totalPaginas; $p++):
                        ?>
                            <li class="page-item <?= $p === $pagina ? "active" : ""; ?>">
                                <a class="page-link" href="<?= $base . $p; ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const sel = document.getElementById('jornada_select');
    const emp = document.getElementById('empleado_id');
    const fec = document.getElementById('fecha_jornada');

    if (!sel) return;

    sel.addEventListener('change', function(){
        if (!this.value) {
            emp.value = '';
            fec.value = '';
            return;
        }
        const p = this.value.split('|');
        emp.value = p[0] || '';
        fec.value = p[1] || '';
    });

    if (sel.value) sel.dispatchEvent(new Event('change'));
})();
</script>
</body>
</html>