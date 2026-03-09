<?php
session_start();
require_once("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

function can($permiso){
    return isset($_SESSION[$permiso]) && (int)$_SESSION[$permiso] === 1;
}

if (!can('Vacaciones_solicitar_Empleado')) {
    $_SESSION['mensaje'] = "No tiene permisos para solicitar vacaciones.";
    $_SESSION['tipo'] = "danger";
    header("Location: MisVacaciones.php");
    exit();
}

if (!isset($_POST['guardar'])) {
    header("Location: SolicitarVacacion.php");
    exit();
}

$idUsuario    = (int)($_SESSION['idUsuario'] ?? 0);
$fechaInicio  = trim($_POST['fecha_inicio'] ?? '');
$fechaFin     = trim($_POST['fecha_fin'] ?? '');

if ($fechaInicio === '' || $fechaFin === '') {
    $_SESSION['mensaje'] = "Debe completar ambas fechas.";
    $_SESSION['tipo'] = "warning";
    header("Location: SolicitarVacacion.php");
    exit();
}

if ($fechaInicio > $fechaFin) {
    $_SESSION['mensaje'] = "La fecha de inicio no puede ser mayor que la fecha final.";
    $_SESSION['tipo'] = "warning";
    header("Location: SolicitarVacacion.php");
    exit();
}

$stmtEmp = $conexion->prepare("
    SELECT idEmpleado, Fecha_de_Ingreso
    FROM empleado
    WHERE Usuario_idUsuario = ?
    LIMIT 1
");

if (!$stmtEmp) {
    $_SESSION['mensaje'] = "Error al consultar el empleado: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: MisVacaciones.php");
    exit();
}

$stmtEmp->bind_param("i", $idUsuario);
$stmtEmp->execute();
$emp = $stmtEmp->get_result()->fetch_assoc();

if (!$emp) {
    $_SESSION['mensaje'] = "No se encontró el empleado asociado al usuario.";
    $_SESSION['tipo'] = "danger";
    header("Location: MisVacaciones.php");
    exit();
}

$idEmpleado   = (int)$emp['idEmpleado'];
$fechaIngreso = $emp['Fecha_de_Ingreso'] ?? null;

if (empty($fechaIngreso)) {
    $_SESSION['mensaje'] = "El empleado no tiene fecha de ingreso registrada.";
    $_SESSION['tipo'] = "danger";
    header("Location: MisVacaciones.php");
    exit();
}

try {
    $inicio = new DateTime($fechaInicio);
    $fin    = new DateTime($fechaFin);
    $diasSolicitados = $inicio->diff($fin)->days + 1;
} catch (Exception $e) {
    $_SESSION['mensaje'] = "Las fechas ingresadas no son válidas.";
    $_SESSION['tipo'] = "warning";
    header("Location: SolicitarVacacion.php");
    exit();
}

if ($diasSolicitados <= 0) {
    $_SESSION['mensaje'] = "La cantidad de días solicitados no es válida.";
    $_SESSION['tipo'] = "warning";
    header("Location: SolicitarVacacion.php");
    exit();
}

$stmtSaldo = $conexion->prepare("
    SELECT
        FLOOR(TIMESTAMPDIFF(WEEK, Fecha_de_Ingreso, CURDATE()) / 50) * 12 AS dias_acumulados,
        COALESCE((
            SELECT SUM(v.dias_aprobados)
            FROM vacaciones v
            WHERE v.Empleado_idEmpleado = ?
              AND v.estado = 3
        ), 0) AS dias_tomados
    FROM empleado
    WHERE idEmpleado = ?
    LIMIT 1
");

if (!$stmtSaldo) {
    $_SESSION['mensaje'] = "Error al calcular saldo: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: SolicitarVacacion.php");
    exit();
}

$stmtSaldo->bind_param("ii", $idEmpleado, $idEmpleado);
$stmtSaldo->execute();
$saldo = $stmtSaldo->get_result()->fetch_assoc();

$diasAcumulados  = (int)($saldo['dias_acumulados'] ?? 0);
$diasTomados     = (int)($saldo['dias_tomados'] ?? 0);
$diasDisponibles = $diasAcumulados - $diasTomados;

if ($diasDisponibles < 0) {
    $diasDisponibles = 0;
}

if ($diasSolicitados > $diasDisponibles) {
    $_SESSION['mensaje'] = "No tiene suficientes días disponibles. Tiene " . $diasDisponibles . " día(s).";
    $_SESSION['tipo'] = "warning";
    header("Location: SolicitarVacacion.php");
    exit();
}

$stmtChoque = $conexion->prepare("
    SELECT COUNT(*) AS c
    FROM vacaciones
    WHERE Empleado_idEmpleado = ?
      AND estado IN (0, 2, 3)
      AND NOT (fecha_fin < ? OR fecha_inicio > ?)
");

if (!$stmtChoque) {
    $_SESSION['mensaje'] = "Error al validar fechas: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: SolicitarVacacion.php");
    exit();
}

$stmtChoque->bind_param("iss", $idEmpleado, $fechaInicio, $fechaFin);
$stmtChoque->execute();
$choque = $stmtChoque->get_result()->fetch_assoc();

if ((int)($choque['c'] ?? 0) > 0) {
    $_SESSION['mensaje'] = "Ya tiene una solicitud o vacaciones en ese rango de fechas.";
    $_SESSION['tipo'] = "warning";
    header("Location: SolicitarVacacion.php");
    exit();
}

$estado = 0;
$diasAprobados = 0;
$jefeUsuarioId = 0;
$jefeFecha = '1900-01-01 00:00:00';
$jefeComentario = '';
$rhUsuarioId = 0;
$rhFecha = '1900-01-01 00:00:00';
$rhComentario = '';

$stmt = $conexion->prepare("
    INSERT INTO vacaciones (
        fecha_solicitud,
        fecha_inicio,
        fecha_fin,
        dias_solicitados,
        dias_aprobados,
        estado,
        jefe_usuario_id,
        jefe_fecha,
        jefe_comentario,
        rh_usuario_id,
        rh_fecha,
        rh_comentario,
        Empleado_idEmpleado
    ) VALUES (
        CURDATE(),
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )
");

if (!$stmt) {
    $_SESSION['mensaje'] = "Error al preparar el guardado: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: SolicitarVacacion.php");
    exit();
}

$stmt->bind_param(
    "iiiiississsi",
    $fechaInicio,
    $fechaFin,
    $diasSolicitados,
    $diasAprobados,
    $estado,
    $jefeUsuarioId,
    $jefeFecha,
    $jefeComentario,
    $rhUsuarioId,
    $rhFecha,
    $rhComentario,
    $idEmpleado
);

if ($stmt->execute()) {
    $_SESSION['mensaje'] = "Solicitud de vacaciones enviada a jefatura.";
    $_SESSION['tipo'] = "success";
} else {
    $_SESSION['mensaje'] = "Error al guardar la solicitud: " . $stmt->error;
    $_SESSION['tipo'] = "danger";
}

header("Location: MisVacaciones.php");
exit();