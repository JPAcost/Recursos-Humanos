<?php
session_start();
require_once("../config/conexion.php");
require_once("../includes/Permisos.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

if (!can('Permisos_aprobados_Jefatura') && !isJefatura() && !isAdmin()) {
    $_SESSION['mensaje'] = "No tiene permisos para gestionar permisos en jefatura.";
    $_SESSION['tipo'] = "danger";
    header("Location: AprobarPermisosJefatura.php");
    exit();
}

$idUsuario  = (int)($_SESSION['idUsuario'] ?? 0);
$idPermiso  = (int)($_POST['idPermiso'] ?? 0);
$accion     = trim($_POST['accion'] ?? '');
$comentario = trim($_POST['comentario'] ?? '');

if ($idPermiso <= 0 || !in_array($accion, ['aprobar', 'rechazar'], true)) {
    $_SESSION['mensaje'] = "Solicitud inválida.";
    $_SESSION['tipo'] = "warning";
    header("Location: AprobarPermisosJefatura.php");
    exit();
}

if ($comentario === '') {
    $comentario = 'Sin comentario';
}

$stmtCheck = $conexion->prepare("
    SELECT idPermiso, estado
    FROM permiso
    WHERE idPermiso = ?
    LIMIT 1
");

if (!$stmtCheck) {
    $_SESSION['mensaje'] = "Error al validar el permiso: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: AprobarPermisosJefatura.php");
    exit();
}

$stmtCheck->bind_param("i", $idPermiso);
$stmtCheck->execute();
$permiso = $stmtCheck->get_result()->fetch_assoc();

if (!$permiso) {
    $_SESSION['mensaje'] = "El permiso no existe.";
    $_SESSION['tipo'] = "warning";
    header("Location: AprobarPermisosJefatura.php");
    exit();
}

if ((int)$permiso['estado'] !== 0) {
    $_SESSION['mensaje'] = "Este permiso ya fue procesado por jefatura.";
    $_SESSION['tipo'] = "warning";
    header("Location: AprobarPermisosJefatura.php");
    exit();
}

/*
    Estados sugeridos:
    0 = Pendiente Jefatura
    1 = Rechazado por Jefatura
    2 = Pendiente RH
    3 = Aprobado por RH
    4 = Rechazado por RH
*/
$nuevoEstado = ($accion === 'aprobar') ? 2 : 1;

$stmt = $conexion->prepare("
    UPDATE permiso
    SET estado = ?,
        jefe_usuario_id = ?,
        jefe_fecha = NOW(),
        jefe_comentario = ?,
        fecha_modificacion = NOW(),
        usuario_modificacion = ?
    WHERE idPermiso = ?
");

if (!$stmt) {
    $_SESSION['mensaje'] = "Error al preparar actualización: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: AprobarPermisosJefatura.php");
    exit();
}

$stmt->bind_param("iisii", $nuevoEstado, $idUsuario, $comentario, $idUsuario, $idPermiso);

if ($stmt->execute()) {
    $_SESSION['mensaje'] = ($accion === 'aprobar')
        ? "Permiso aprobado por jefatura y enviado a RRHH."
        : "Permiso rechazado por jefatura.";
    $_SESSION['tipo'] = "success";
} else {
    $_SESSION['mensaje'] = "Error al procesar la solicitud: " . $stmt->error;
    $_SESSION['tipo'] = "danger";
}

header("Location: AprobarPermisosJefatura.php");
exit();