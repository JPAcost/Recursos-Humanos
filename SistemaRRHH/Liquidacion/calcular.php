<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

/* ============================
   VALIDACIÓN DE PERMISO
============================ */
function can($permiso){
    return isset($_SESSION[$permiso]) && (int)$_SESSION[$permiso] === 1;
}

function isAdmin(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'admin';
}

if (!can('Liquidaciones_calcular') && !isAdmin()) {
    $_SESSION['mensaje_liquidacion'] = "No tiene permisos para realizar esta acción.";
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: liquidacion.php");
    exit();
}

/* ============================
   DATOS DEL FORMULARIO
============================ */
$idEmpleado      = (int)($_POST['idEmpleado'] ?? 0);
$fechaSalida     = trim($_POST['fechaSalida'] ?? '');
$diasVacaciones  = (float)($_POST['diasVacaciones'] ?? 0);

$usuarioId       = (int)($_SESSION['idUsuario'] ?? 0);
$usuarioCreacion = (string)$usuarioId;
$usuarioModifica = $usuarioId;
$estado          = 'Activo';

/* ============================
   VALIDACIONES BÁSICAS
============================ */
if ($idEmpleado <= 0) {
    $_SESSION['mensaje_liquidacion'] = "Debe seleccionar un empleado válido.";
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

if (empty($fechaSalida)) {
    $_SESSION['mensaje_liquidacion'] = "Debe ingresar la fecha de salida.";
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

if ($diasVacaciones < 0) {
    $_SESSION['mensaje_liquidacion'] = "Los días de vacaciones pendientes no pueden ser negativos.";
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

/* ============================
   OBTENER EMPLEADO
============================ */
$sqlEmp = "SELECT idEmpleado, nombre, apellidos, Fecha_de_Ingreso, salario_mensual, Estado
           FROM empleado
           WHERE idEmpleado = ?
           LIMIT 1";

$stmtEmp = $conexion->prepare($sqlEmp);
if (!$stmtEmp) {
    $_SESSION['mensaje_liquidacion'] = "Error al preparar consulta del empleado: " . $conexion->error;
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

$stmtEmp->bind_param("i", $idEmpleado);
$stmtEmp->execute();
$resultEmp = $stmtEmp->get_result();
$empleado = $resultEmp->fetch_assoc();
$stmtEmp->close();

if (!$empleado) {
    $_SESSION['mensaje_liquidacion'] = "El empleado seleccionado no existe.";
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

if (($empleado['Estado'] ?? '') !== 'Activo') {
    $_SESSION['mensaje_liquidacion'] = "Solo se pueden liquidar empleados activos.";
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

$fechaIngreso = $empleado['Fecha_de_Ingreso'] ?? '';
$salario      = (float)($empleado['salario_mensual'] ?? 0);

if (empty($fechaIngreso)) {
    $_SESSION['mensaje_liquidacion'] = "El empleado no tiene fecha de ingreso registrada.";
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

if ($salario <= 0) {
    $_SESSION['mensaje_liquidacion'] = "El empleado no tiene salario mensual válido.";
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

if (strtotime($fechaSalida) < strtotime($fechaIngreso)) {
    $_SESSION['mensaje_liquidacion'] = "La fecha de salida no puede ser menor que la fecha de ingreso.";
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

/* ============================
   CÁLCULOS
============================ */
$fechaIngresoObj = new DateTime($fechaIngreso);
$fechaSalidaObj  = new DateTime($fechaSalida);

$diferencia      = $fechaIngresoObj->diff($fechaSalidaObj);
$antiguedadDias  = (int)$diferencia->days;
$aniosCompletos  = floor($antiguedadDias / 365);

/* Vacaciones pendientes */
$vacacionesMonto = ($salario / 30) * $diasVacaciones;

/* Preaviso */
if ($antiguedadDias < 90) {
    $diasPreaviso = 0;
} elseif ($antiguedadDias < 180) {
    $diasPreaviso = 7;
} elseif ($antiguedadDias < 365) {
    $diasPreaviso = 15;
} else {
    $diasPreaviso = 30;
}

$preaviso = ($salario / 30) * $diasPreaviso;

/* Cesantía */
switch ($aniosCompletos) {
    case 0: $diasCesantia = 0; break;
    case 1: $diasCesantia = 20; break;
    case 2: $diasCesantia = 20; break;
    case 3: $diasCesantia = 20; break;
    case 4: $diasCesantia = 22; break;
    case 5: $diasCesantia = 22; break;
    case 6: $diasCesantia = 23; break;
    case 7: $diasCesantia = 24; break;
    case 8: $diasCesantia = 26; break;
    default: $diasCesantia = ($aniosCompletos > 8) ? 26 : 0; break;
}

$cesantia = ($salario / 30) * $diasCesantia;

/* Salario proporcional / pendiente */
$diaSalida = (int)$fechaSalidaObj->format('d');
$salarioPendiente = ($salario / 30) * $diaSalida;

/* Total */
$salarioPendiente = round($salarioPendiente, 2);
$vacacionesMonto  = round($vacacionesMonto, 2);
$preaviso         = round($preaviso, 2);
$cesantia         = round($cesantia, 2);
$totalLiquidacion = round($salarioPendiente + $vacacionesMonto + $preaviso + $cesantia, 2);

/* ============================
   GUARDAR EN BD
============================ */
$sqlInsert = "INSERT INTO liquidacion (
                fecha_salida,
                salario_pendiente,
                vacaciones_pendientes,
                preaviso,
                cesantia,
                total_liquidacion,
                Empleado_idEmpleado,
                Fecha_creacion,
                Usuario_creacion,
                fecha_modificiacion,
                usuario_modificacion,
                estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), ?, ?)";

$stmtInsert = $conexion->prepare($sqlInsert);
if (!$stmtInsert) {
    $_SESSION['mensaje_liquidacion'] = "Error al preparar el insert: " . $conexion->error;
    $_SESSION['tipo_liquidacion'] = "danger";
    header("Location: liquidacion.php");
    exit();
}

$stmtInsert->bind_param(
    "sdddddisis",
    $fechaSalida,
    $salarioPendiente,
    $vacacionesMonto,
    $preaviso,
    $cesantia,
    $totalLiquidacion,
    $idEmpleado,
    $usuarioCreacion,
    $usuarioModifica,
    $estado
);

if ($stmtInsert->execute()) {
    $_SESSION['mensaje_liquidacion'] = "Liquidación registrada correctamente.";
    $_SESSION['tipo_liquidacion'] = "success";
} else {
    $_SESSION['mensaje_liquidacion'] = "Error al guardar la liquidación: " . $stmtInsert->error;
    $_SESSION['tipo_liquidacion'] = "danger";
}

$stmtInsert->close();
header("Location: liquidacion.php");
exit();