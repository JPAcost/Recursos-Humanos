<?php
session_start();
include("../config/conexion.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['idUsuario'])) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Sesión no válida.'
    ]);
    exit();
}

function can($permiso){
    return isset($_SESSION[$permiso]) && (int)$_SESSION[$permiso] === 1;
}

function isAdmin(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'admin';
}

if (!can('Liquidaciones_calcular') && !isAdmin()) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'No tiene permisos.'
    ]);
    exit();
}

$idEmpleado = (int)($_GET['idEmpleado'] ?? 0);
$fechaSalida = trim($_GET['fechaSalida'] ?? '');

if ($idEmpleado <= 0) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Empleado inválido.'
    ]);
    exit();
}

if ($fechaSalida === '') {
    $fechaSalida = date('Y-m-d');
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
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Error al preparar empleado: ' . $conexion->error
    ]);
    exit();
}

$stmtEmp->bind_param("i", $idEmpleado);
$stmtEmp->execute();
$empleado = $stmtEmp->get_result()->fetch_assoc();
$stmtEmp->close();

if (!$empleado) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Empleado no encontrado.'
    ]);
    exit();
}

$fechaIngreso = $empleado['Fecha_de_Ingreso'] ?? '';
$salario = (float)($empleado['salario_mensual'] ?? 0);

if ($fechaIngreso === '') {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'El empleado no tiene fecha de ingreso.'
    ]);
    exit();
}

if (strtotime($fechaSalida) < strtotime($fechaIngreso)) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'La fecha de salida no puede ser menor que la fecha de ingreso.'
    ]);
    exit();
}

/* ============================
   VACACIONES GENERADAS
   REGLA SIMPLE: 1 día por mes trabajado
   AJUSTABLE según tu política
============================ */
$ingreso = new DateTime($fechaIngreso);
$salida  = new DateTime($fechaSalida);
$diff    = $ingreso->diff($salida);

$mesesTrabajados = ($diff->y * 12) + $diff->m;

// si quieres contar fracción del mes actual:
if ((int)$diff->d >= 15) {
    $mesesTrabajados += 1;
}

$diasGenerados = $mesesTrabajados;

/* ============================
   VACACIONES APROBADAS / USADAS
   IMPORTANTE:
   aquí asumimos que estado = 1 significa aprobada/final
   si en tu sistema es otro valor, cámbialo
============================ */
$sqlVac = "SELECT COALESCE(SUM(dias_aprobados), 0) AS dias_usados
           FROM vacaciones
           WHERE Empleado_idEmpleado = ?
             AND estado = 1";

$stmtVac = $conexion->prepare($sqlVac);
if (!$stmtVac) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Error al preparar vacaciones: ' . $conexion->error
    ]);
    exit();
}

$stmtVac->bind_param("i", $idEmpleado);
$stmtVac->execute();
$vacData = $stmtVac->get_result()->fetch_assoc();
$stmtVac->close();

$diasUsados = (float)($vacData['dias_usados'] ?? 0);

/* ============================
   SALDO
============================ */
$diasPendientes = $diasGenerados - $diasUsados;
if ($diasPendientes < 0) {
    $diasPendientes = 0;
}

echo json_encode([
    'ok' => true,
    'fechaIngreso' => $fechaIngreso,
    'salario' => round($salario, 2),
    'diasGenerados' => round($diasGenerados, 2),
    'diasUsados' => round($diasUsados, 2),
    'diasPendientes' => round($diasPendientes, 2)
]);