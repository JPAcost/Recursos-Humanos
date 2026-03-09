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

function isAdmin(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'admin';
}

if (!can('Vacaciones_aprobados_RH') && !isAdmin()) {
    $_SESSION['mensaje'] = "No tiene permisos para gestionar vacaciones en RRHH.";
    $_SESSION['tipo'] = "danger";
    header("Location: Vacaciones.php");
    exit();
}

$idUsuario  = (int)($_SESSION['idUsuario'] ?? 0);
$idVacacion = (int)($_POST['idVacacion'] ?? 0);
$accion     = trim($_POST['accion'] ?? '');
$comentario = trim($_POST['comentario'] ?? '');

if ($idVacacion <= 0 || !in_array($accion, ['aprobar', 'rechazar'], true)) {
    $_SESSION['mensaje'] = "Solicitud inválida.";
    $_SESSION['tipo'] = "warning";
    header("Location: AprobarVacacionesRH.php");
    exit();
}

if ($comentario === '') {
    $comentario = 'Sin comentario';
}

$stmtCheck = $conexion->prepare("
    SELECT idVacaciones, estado, dias_aprobados
    FROM vacaciones
    WHERE idVacaciones = ?
    LIMIT 1
");

if (!$stmtCheck) {
    $_SESSION['mensaje'] = "Error al validar la solicitud: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: AprobarVacacionesRH.php");
    exit();
}

$stmtCheck->bind_param("i", $idVacacion);
$stmtCheck->execute();
$vacacion = $stmtCheck->get_result()->fetch_assoc();

if (!$vacacion) {
    $_SESSION['mensaje'] = "La solicitud no existe.";
    $_SESSION['tipo'] = "warning";
    header("Location: AprobarVacacionesRH.php");
    exit();
}

if ((int)$vacacion['estado'] !== 2) {
    $_SESSION['mensaje'] = "La solicitud no está pendiente de RRHH o ya fue procesada.";
    $_SESSION['tipo'] = "warning";
    header("Location: AprobarVacacionesRH.php");
    exit();
}

$nuevoEstado = ($accion === 'aprobar') ? 3 : 4;
$diasAprobados = ($accion === 'aprobar') ? (int)$vacacion['dias_aprobados'] : 0;

$stmt = $conexion->prepare("
    UPDATE vacaciones
    SET estado = ?,
        dias_aprobados = ?,
        rh_usuario_id = ?,
        rh_fecha = NOW(),
        rh_comentario = ?
    WHERE idVacaciones = ?
");

if (!$stmt) {
    $_SESSION['mensaje'] = "Error al preparar la actualización: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: AprobarVacacionesRH.php");
    exit();
}

$stmt->bind_param(
    "iiisi",
    $nuevoEstado,
    $diasAprobados,
    $idUsuario,
    $comentario,
    $idVacacion
);

if ($stmt->execute()) {
    $_SESSION['mensaje'] = ($accion === 'aprobar')
        ? "Vacación aprobada por RRHH."
        : "Vacación rechazada por RRHH.";
    $_SESSION['tipo'] = "success";
} else {
    $_SESSION['mensaje'] = "Error al procesar la solicitud: " . $stmt->error;
    $_SESSION['tipo'] = "danger";
}

header("Location: AprobarVacacionesRH.php");
exit();