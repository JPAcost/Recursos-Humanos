<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

function isAdmin(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'admin';
}

function isJefatura(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'jefatura';
}

if (!isJefatura() && !isAdmin()) {
    die("No tiene permisos para realizar esta acción.");
}

$idUsuario    = (int)($_SESSION['idUsuario'] ?? 0);
$idSolicitud  = (int)($_POST['idSolicitud'] ?? 0);
$accion       = trim($_POST['accion'] ?? '');
$observacion  = trim($_POST['observacion'] ?? '');

if ($idSolicitud <= 0 || !in_array($accion, ['aprobar', 'rechazar'])) {
    die("Datos inválidos.");
}

/* ============================
   VALIDAR QUE LA SOLICITUD EXISTA
============================ */
$stmtVal = $conexion->prepare("
    SELECT idSolicitud, idJefatura, estado
    FROM solicitudes_planilla
    WHERE idSolicitud = ?
      AND estado_registro = b'1'
    LIMIT 1
");

if (!$stmtVal) {
    die("Error preparando validación: " . $conexion->error);
}

$stmtVal->bind_param("i", $idSolicitud);
$stmtVal->execute();
$sol = $stmtVal->get_result()->fetch_assoc();

if (!$sol) {
    die("La solicitud no existe.");
}

if ((int)$sol['idJefatura'] !== $idUsuario && !isAdmin()) {
    die("No tiene permiso para gestionar esta solicitud.");
}

if ($sol['estado'] !== 'Pendiente_Jefatura') {
    die("La solicitud ya fue procesada o no está pendiente de jefatura.");
}

/* ============================
   DEFINIR NUEVO ESTADO
============================ */
$nuevoEstado = '';
if ($accion === 'aprobar') {
    $nuevoEstado = 'Pendiente_RRHH';
} else {
    $nuevoEstado = 'Rechazado_Jefatura';
}

if ($accion === 'rechazar' && $observacion === '') {
    die("Debe indicar una observación para rechazar la solicitud.");
}

/* ============================
   ACTUALIZAR
============================ */
$stmtUp = $conexion->prepare("
    UPDATE solicitudes_planilla
    SET
        estado = ?,
        observacion = ?,
        fecha_aprobacion_jefatura = NOW(),
        usuario_modificacion = ?,
        fecha_modificacion = NOW()
    WHERE idSolicitud = ?
");

if (!$stmtUp) {
    die("Error preparando actualización: " . $conexion->error);
}

$stmtUp->bind_param("ssii", $nuevoEstado, $observacion, $idUsuario, $idSolicitud);

if (!$stmtUp->execute()) {
    die("Error actualizando solicitud: " . $stmtUp->error);
}

echo "<script>alert('Solicitud procesada correctamente.'); window.location='aprobar_solicitudes_jefatura.php';</script>";
exit();
?>