<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

function can(string $permiso): bool {
    return isset($_SESSION[$permiso]) && (int)$_SESSION[$permiso] === 1;
}

function isAdmin(): bool {
    return isset($_SESSION['NombreRol']) && strtolower((string)$_SESSION['NombreRol']) === 'admin';
}

if (!can('Liquidaciones_calcular') && !isAdmin()) {
    die('No tiene permisos para exportar liquidaciones.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Liquidación inválida.');
}

$sql = "
    SELECT 
        l.idLiquidacion,
        l.fecha_salida,
        l.salario_pendiente,
        l.cesantia,
        l.vacaciones_pendientes,
        l.preaviso,
        l.total_liquidacion,
        l.Fecha_creacion,
        e.nombre,
        e.apellidos,
        e.cedula,
        e.puesto,
        e.salario_mensual,
        e.Fecha_de_Ingreso
    FROM liquidacion l
    INNER JOIN empleado e ON e.idEmpleado = l.Empleado_idEmpleado
    WHERE l.idLiquidacion = ?
    LIMIT 1
";

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    die('Error al preparar la consulta: ' . $conexion->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die('No se encontró la liquidación solicitada.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=liquidacion_' . $id . '.csv');

$out = fopen('php://output', 'w');

fputcsv($out, ['Campo', 'Valor']);
fputcsv($out, ['ID Liquidacion', $data['idLiquidacion']]);
fputcsv($out, ['Empleado', trim($data['nombre'] . ' ' . $data['apellidos'])]);
fputcsv($out, ['Cedula', $data['cedula']]);
fputcsv($out, ['Puesto', $data['puesto']]);
fputcsv($out, ['Fecha ingreso', $data['Fecha_de_Ingreso']]);
fputcsv($out, ['Fecha salida', $data['fecha_salida']]);
fputcsv($out, ['Salario mensual', $data['salario_mensual']]);
fputcsv($out, ['Salario proporcional', $data['salario_pendiente']]);
fputcsv($out, ['Cesantia', $data['cesantia']]);
fputcsv($out, ['Vacaciones pendientes', $data['vacaciones_pendientes']]);
fputcsv($out, ['Preaviso', $data['preaviso']]);
fputcsv($out, ['Total liquidacion', $data['total_liquidacion']]);
fputcsv($out, ['Fecha registro', $data['Fecha_creacion']]);

fclose($out);
exit();