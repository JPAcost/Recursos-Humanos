<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$usuarioSesion = (int)($_SESSION['idUsuario'] ?? 0);

$idEmpleado    = (int)($_POST['idEmpleado'] ?? 0);
$tipoSolicitud = trim($_POST['tipo_solicitud'] ?? '');
$monto         = (float)($_POST['monto'] ?? 0);
$descripcion   = trim($_POST['descripcion'] ?? '');
$idJefatura    = (int)($_POST['idJefatura'] ?? 0);
$idRRHH        = (int)($_POST['idRRHH'] ?? 0);

if (
    $usuarioSesion <= 0 ||
    $idEmpleado <= 0 ||
    $tipoSolicitud === '' ||
    $monto <= 0 ||
    $descripcion === '' ||
    $idJefatura <= 0 ||
    $idRRHH <= 0
) {
    die("Todos los campos son obligatorios y el monto debe ser mayor que 0.");
}

/* ============================
   VALIDAR EMPLEADO
============================ */
$stmtEmp = $conexion->prepare("
    SELECT idEmpleado
    FROM empleado
    WHERE idEmpleado = ?
      AND Estado = 'Activo'
    LIMIT 1
");

if (!$stmtEmp) {
    die("Error validando empleado: " . $conexion->error);
}

$stmtEmp->bind_param("i", $idEmpleado);
$stmtEmp->execute();
$empOk = $stmtEmp->get_result()->fetch_assoc();

if (!$empOk) {
    die("El empleado no existe o no está activo.");
}

/* ============================
   VALIDAR JEFATURA
============================ */
$stmtJ = $conexion->prepare("
    SELECT idUsuario
    FROM usuario
    WHERE idUsuario = ?
      AND Estado = b'1'
      AND idRoles = 1
    LIMIT 1
");

if (!$stmtJ) {
    die("Error validando jefatura: " . $conexion->error);
}

$stmtJ->bind_param("i", $idJefatura);
$stmtJ->execute();
$jOk = $stmtJ->get_result()->fetch_assoc();

if (!$jOk) {
    die("La jefatura seleccionada no es válida.");
}

/* ============================
   VALIDAR RRHH / ADMIN
============================ */
$stmtR = $conexion->prepare("
    SELECT idUsuario
    FROM usuario
    WHERE idUsuario = ?
      AND Estado = b'1'
      AND idRoles = 2
    LIMIT 1
");

if (!$stmtR) {
    die("Error validando RRHH/Admin: " . $conexion->error);
}

$stmtR->bind_param("i", $idRRHH);
$stmtR->execute();
$rOk = $stmtR->get_result()->fetch_assoc();

if (!$rOk) {
    die("El usuario de RRHH/Admin seleccionado no es válido.");
}

/* ============================
   INSERTAR SOLICITUD
============================ */
$stmt = $conexion->prepare("
    INSERT INTO solicitudes_planilla
    (
        idEmpleado,
        tipo_solicitud,
        descripcion,
        estado,
        idJefatura,
        idRRHH,
        fecha_creacion,
        fecha_aprobacion_jefatura,
        fecha_aprobacion_rrhh,
        observacion,
        usuario_creacion,
        usuario_modificacion,
        fecha_modificacion,
        estado_registro,
        monto,
        aplicado_planilla
    )
    VALUES
    (?, ?, ?, 'Pendiente_Jefatura', ?, ?, NOW(), NOW(), NOW(), '', ?, ?, NOW(), b'1', ?, b'0')
");

if (!$stmt) {
    die("Error al preparar la consulta: " . $conexion->error);
}

$stmt->bind_param(
    "issiiiid",
    $idEmpleado,
    $tipoSolicitud,
    $descripcion,
    $idJefatura,
    $idRRHH,
    $usuarioSesion,
    $usuarioSesion,
    $monto
);

if (!$stmt->execute()) {
    die("Error al guardar la solicitud: " . $stmt->error);
}

echo "<script>alert('Solicitud registrada correctamente'); window.location='solicitud_planilla.php';</script>";
exit();
?>