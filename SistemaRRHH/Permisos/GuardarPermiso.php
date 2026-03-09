<?php
session_start();
require_once("../config/conexion.php");
require_once("../includes/Permisos.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

if (!can('Permisos_ver_empleado')) {
    $_SESSION['mensaje'] = "No tiene permisos para solicitar permisos.";
    $_SESSION['tipo'] = "danger";
    header("Location: MisPermisos.php");
    exit();
}

if (!isset($_POST['guardar'])) {
    header("Location: SolicitarPermiso.php");
    exit();
}

$idUsuario = (int)($_SESSION['idUsuario'] ?? 0);
$tipo      = (int)($_POST['tipo_permiso'] ?? 0);
$inicio    = trim($_POST['fecha_inicio'] ?? '');
$fin       = trim($_POST['fecha_fin'] ?? '');
$motivo    = trim($_POST['motivo'] ?? '');

if ($tipo <= 0 || $inicio === '' || $fin === '' || $motivo === '') {
    $_SESSION['mensaje'] = "Complete todos los campos.";
    $_SESSION['tipo'] = "warning";
    header("Location: SolicitarPermiso.php");
    exit();
}

if ($inicio > $fin) {
    $_SESSION['mensaje'] = "La fecha de inicio no puede ser mayor que la fecha final.";
    $_SESSION['tipo'] = "warning";
    header("Location: SolicitarPermiso.php");
    exit();
}

/* ==========================
   OBTENER EMPLEADO DEL USUARIO
========================== */
$stmtEmp = $conexion->prepare("
    SELECT idEmpleado
    FROM empleado
    WHERE Usuario_idUsuario = ?
    LIMIT 1
");

if (!$stmtEmp) {
    $_SESSION['mensaje'] = "Error al preparar la consulta del empleado: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: MisPermisos.php");
    exit();
}

$stmtEmp->bind_param("i", $idUsuario);
$stmtEmp->execute();
$emp = $stmtEmp->get_result()->fetch_assoc();

if (!$emp) {
    $_SESSION['mensaje'] = "No se encontró el empleado asociado a este usuario.";
    $_SESSION['tipo'] = "danger";
    header("Location: MisPermisos.php");
    exit();
}

$idEmpleado = (int)$emp['idEmpleado'];

/* ==========================
   VALIDAR TIPO DE PERMISO
========================== */
$stmtTipo = $conexion->prepare("
    SELECT idTipo_permiso
    FROM tipo_permiso
    WHERE idTipo_permiso = ?
      AND Estado = 1
    LIMIT 1
");

if (!$stmtTipo) {
    $_SESSION['mensaje'] = "Error al validar el tipo de permiso: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: SolicitarPermiso.php");
    exit();
}

$stmtTipo->bind_param("i", $tipo);
$stmtTipo->execute();
$tipoValido = $stmtTipo->get_result()->fetch_assoc();

if (!$tipoValido) {
    $_SESSION['mensaje'] = "El tipo de permiso seleccionado no es válido.";
    $_SESSION['tipo'] = "warning";
    header("Location: SolicitarPermiso.php");
    exit();
}

/* ==========================
   VALIDAR CHOQUE DE FECHAS
========================== */
$stmtChoque = $conexion->prepare("
    SELECT COUNT(*) AS total
    FROM permiso
    WHERE Empleado_idEmpleado = ?
      AND estado IN (0,2,3)
      AND NOT (fecha_fin < ? OR fecha_inicio > ?)
");

if (!$stmtChoque) {
    $_SESSION['mensaje'] = "Error al validar choque de fechas: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: SolicitarPermiso.php");
    exit();
}

$stmtChoque->bind_param("iss", $idEmpleado, $inicio, $fin);
$stmtChoque->execute();
$rowChoque = $stmtChoque->get_result()->fetch_assoc();

if ((int)($rowChoque['total'] ?? 0) > 0) {
    $_SESSION['mensaje'] = "Ya existe otro permiso en ese rango de fechas.";
    $_SESSION['tipo'] = "warning";
    header("Location: SolicitarPermiso.php");
    exit();
}

/* ==========================
   INSERT 
========================== */
$jefeUsuarioId = 0;
$jefeFecha = '1900-01-01 00:00:00';
$jefeComentario = '';
$rhUsuarioId = 0;
$rhFecha = '1900-01-01 00:00:00';
$rhComentario = '';
$estado = 0;

$stmt = $conexion->prepare("
    INSERT INTO permiso (
        fecha_solicitud,
        fecha_inicio,
        fecha_fin,
        motivo,
        Empleado_idEmpleado,
        fecha_creacion,
        fecha_modificacion,
        usuario_modificacion,
        jefe_usuario_id,
        jefe_fecha,
        jefe_comentario,
        rh_usuario_id,
        rh_fecha,
        rh_comentario,
        estado,
        Tipo_permiso_idTipo_permiso
    ) VALUES (
        CURDATE(),
        ?,
        ?,
        ?,
        ?,
        NOW(),
        NOW(),
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
    $_SESSION['mensaje'] = "Error al preparar inserción: " . $conexion->error;
    $_SESSION['tipo'] = "danger";
    header("Location: SolicitarPermiso.php");
    exit();
}

$stmt->bind_param(
    "sssiiississii",
    $inicio,
    $fin,
    $motivo,
    $idEmpleado,
    $idUsuario,
    $jefeUsuarioId,
    $jefeFecha,
    $jefeComentario,
    $rhUsuarioId,
    $rhFecha,
    $rhComentario,
    $estado,
    $tipo
);

if ($stmt->execute()) {
    $_SESSION['mensaje'] = "Solicitud enviada correctamente a jefatura.";
    $_SESSION['tipo'] = "success";
} else {
    $_SESSION['mensaje'] = "Error al guardar la solicitud: " . $stmt->error;
    $_SESSION['tipo'] = "danger";
}

header("Location: MisPermisos.php");
exit();