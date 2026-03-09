<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

date_default_timezone_set("America/Costa_Rica");

$usuario = (int)$_SESSION['idUsuario'];

$fecha    = date("Y-m-d");
$modoHora = $_POST['modo_hora'] ?? 'real';
$horaDemo = $_POST['hora_demo'] ?? '';

if ($modoHora === 'demo' && preg_match('/^\d{2}:\d{2}$/', $horaDemo)) {
    $hora = $horaDemo . ':00';
} else {
    $hora = date("H:i:s");
}

// SIEMPRE inicializar
$horasOrdinarias = 0.00;

/* ==========================================================
   0) OBTENER EMPLEADO DEL USUARIO LOGUEADO
========================================================== */
$stmtEmpUser = $conexion->prepare("
    SELECT idEmpleado, Estado
    FROM empleado
    WHERE Usuario_idUsuario = ?
    LIMIT 1
");

if (!$stmtEmpUser) {
    $_SESSION['mensaje'] = "Error al preparar la consulta del empleado: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

$stmtEmpUser->bind_param("i", $usuario);
$stmtEmpUser->execute();
$empUser = $stmtEmpUser->get_result()->fetch_assoc();

if (!$empUser) {
    $_SESSION['mensaje'] = "No existe un empleado asociado al usuario actual.";
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

$idEmpleado = (int)$empUser['idEmpleado'];

if ($empUser['Estado'] !== 'Activo') {
    $_SESSION['mensaje'] = "El empleado asociado a este usuario no está activo.";
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

/* ==========================================================
   1) OBTENER HORARIO DEL EMPLEADO PARA HOY
========================================================== */
$horarioId = 0;

$stmtAsign = $conexion->prepare("
    SELECT idTurno
    FROM empleado_turno
    WHERE idEmpleado = ?
      AND fecha = ?
      AND Estado = 1
    ORDER BY idAsignacion DESC
    LIMIT 1
");

if (!$stmtAsign) {
    $_SESSION['mensaje'] = "Error al preparar la consulta de asignación: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

$stmtAsign->bind_param("is", $idEmpleado, $fecha);
$stmtAsign->execute();
$asig = $stmtAsign->get_result()->fetch_assoc();

if ($asig) {
    $horarioId = (int)$asig['idTurno'];
}

if ($horarioId <= 0) {
    $_SESSION['mensaje'] = "No hay asignación de turno para hoy.";
    $_SESSION['tipo'] = "warning";
    header("Location: marca_asistencia.php");
    exit();
}

$stmtHor = $conexion->prepare("
    SELECT idHorario, tipo_jornada, Hora_entrada, Hora_Salida
    FROM horario
    WHERE idHorario = ?
      AND fecha = ?
      AND Estado = 1
    LIMIT 1
");

if (!$stmtHor) {
    $_SESSION['mensaje'] = "Error al preparar la consulta de horario: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

$stmtHor->bind_param("is", $horarioId, $fecha);
$stmtHor->execute();
$hor = $stmtHor->get_result()->fetch_assoc();

if (!$hor) {
    $_SESSION['mensaje'] = "El horario asignado no existe para hoy o no está activo.";
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

if ($hor['tipo_jornada'] === 'descanso') {
    $_SESSION['mensaje'] = "Hoy es día de descanso según el horario. No se permiten marcas.";
    $_SESSION['tipo'] = "info";
    header("Location: marca_asistencia.php");
    exit();
}

/* ==========================================================
   2) ANTI-DUPLICADO
========================================================== */
$dupWindowSeconds = 60;

$stmtDup = $conexion->prepare("
    SELECT idControl_de_Asistencia, Hora
    FROM control_asistencia
    WHERE Empleado_idEmpleado = ?
      AND Fecha = ?
      AND ABS(TIME_TO_SEC(TIMEDIFF(Hora, ?))) <= ?
    ORDER BY idControl_de_Asistencia DESC
    LIMIT 1
");

if (!$stmtDup) {
    $_SESSION['mensaje'] = "Error al preparar validación de duplicado: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

$stmtDup->bind_param("issi", $idEmpleado, $fecha, $hora, $dupWindowSeconds);
$stmtDup->execute();
$dup = $stmtDup->get_result()->fetch_assoc();

if ($dup) {
    $_SESSION['mensaje'] = "Marca duplicada detectada. Intenta de nuevo en unos segundos.";
    $_SESSION['tipo'] = "warning";
    header("Location: marca_asistencia.php");
    exit();
}

/* ==========================================================
   3) TIPOS DE MARCA
   SOLO ENTRADA Y SALIDA
========================================================== */
$stmtTipos = $conexion->prepare("
    SELECT idTipo_de_marca, Tipo
    FROM tipo_de_marca
    WHERE Estado = 1
");

if (!$stmtTipos) {
    $_SESSION['mensaje'] = "Error al preparar consulta de tipos de marca: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

$stmtTipos->execute();
$resTipos = $stmtTipos->get_result();

$tipos = [];
while ($t = $resTipos->fetch_assoc()) {
    $tipos[$t['Tipo']] = (int)$t['idTipo_de_marca'];
}

$needed = ['Entrada', 'Salida'];
foreach ($needed as $n) {
    if (!isset($tipos[$n])) {
        $_SESSION['mensaje'] = "Faltan tipos en tipo_de_marca: {$n}.";
        $_SESSION['tipo'] = "danger";
        header("Location: marca_asistencia.php");
        exit();
    }
}

/* ==========================================================
   4) MARCAS DEL DÍA
   SOLO SE PERMITEN 2:
   1ra = Entrada
   2da = Salida
========================================================== */
$stmtHoy = $conexion->prepare("
    SELECT Hora, Tipo_de_marca_idTipo_de_marca
    FROM control_asistencia
    WHERE Empleado_idEmpleado = ?
      AND Fecha = ?
    ORDER BY Hora ASC
");

if (!$stmtHoy) {
    $_SESSION['mensaje'] = "Error al preparar consulta de marcas del día: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

$stmtHoy->bind_param("is", $idEmpleado, $fecha);
$stmtHoy->execute();
$marcas = $stmtHoy->get_result()->fetch_all(MYSQLI_ASSOC);

$cant = count($marcas);

function timeToSeconds($hhmmss) {
    [$h, $m, $s] = array_map('intval', explode(':', $hhmmss));
    return $h * 3600 + $m * 60 + $s;
}

if ($cant === 0) {
    $tipoMarcaId = $tipos['Entrada'];
} elseif ($cant === 1) {
    $tipoAnterior = (int)$marcas[0]['Tipo_de_marca_idTipo_de_marca'];

    if ($tipoAnterior !== $tipos['Entrada']) {
        $_SESSION['mensaje'] = "La primera marca del día debe ser una entrada.";
        $_SESSION['tipo'] = "warning";
        header("Location: marca_asistencia.php");
        exit();
    }

    $tipoMarcaId = $tipos['Salida'];
} else {
    $_SESSION['mensaje'] = "Ya registraste entrada y salida hoy.";
    $_SESSION['tipo'] = "warning";
    header("Location: marca_asistencia.php");
    exit();
}

/* ==========================================================
   5) CALCULAR HORAS ORDINARIAS SOLO SI ES SALIDA
========================================================== */
if ($tipoMarcaId === $tipos['Salida'] && $cant >= 1) {
    $horaEntrada = $marcas[0]['Hora'];

    $secEntrada = timeToSeconds($horaEntrada);
    $secSalida  = timeToSeconds($hora);

    $total = max(0, $secSalida - $secEntrada);

    // Si quieres descontar 1 hora de almuerzo fija cuando pasa de cierto tiempo,
    // aquí se podría hacer. De momento NO se descuenta almuerzo.
    $horasOrdinarias = round($total / 3600, 2);
}

/* ==========================================================
   6) INSERTAR MARCA
========================================================== */
$sqlInsert = "
    INSERT INTO control_asistencia
    (
        Fecha,
        Horas_ordinarias,
        Hora,
        Empleado_idEmpleado,
        Horario_idHorario,
        Tipo_de_marca_idTipo_de_marca,
        fecha_creacion,
        Usuario_creacion
    )
    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
";

$stmtIns = $conexion->prepare($sqlInsert);

if (!$stmtIns) {
    $_SESSION['mensaje'] = "Error al preparar inserción: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

$stmtIns->bind_param(
    "sdsiiii",
    $fecha,
    $horasOrdinarias,
    $hora,
    $idEmpleado,
    $horarioId,
    $tipoMarcaId,
    $usuario
);

if (!$stmtIns->execute()) {
    $_SESSION['mensaje'] = "Error al registrar marca: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: marca_asistencia.php");
    exit();
}

$_SESSION['mensaje'] = "Marca registrada correctamente.";
$_SESSION['tipo'] = "success";
header("Location: marca_asistencia.php");
exit();
?>