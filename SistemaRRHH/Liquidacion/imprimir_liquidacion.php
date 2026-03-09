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
    echo "No tiene permisos para visualizar esta liquidación.";
    exit();
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
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
    die("Error al preparar la consulta: " . $conexion->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die('No se encontró la liquidación solicitada.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de liquidación</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#fff;color:#111827;margin:0;padding:30px}
        .sheet{max-width:900px;margin:0 auto}
        .head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;border-bottom:3px solid #111827;padding-bottom:16px;margin-bottom:20px}
        h1{margin:0 0 6px}
        .muted{color:#6b7280}
        .box{border:1px solid #d1d5db;border-radius:12px;padding:16px;margin-bottom:18px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left}
        th{background:#f9fafb}
        .total{font-size:28px;font-weight:800;margin-top:12px}
        .sign{margin-top:38px;display:grid;grid-template-columns:1fr 1fr;gap:28px}
        .line{border-top:1px solid #111827;padding-top:8px;text-align:center}
        .btn{
            display:inline-block;
            padding:10px 14px;
            border-radius:10px;
            text-decoration:none;
            font-weight:700;
            border:none;
            cursor:pointer;
            background:#e5e7eb;
            color:#111827;
        }
        @media print {
            .no-print{display:none}
            body{padding:0}
            .sheet{max-width:none}
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="head">
        <div>
            <h1>Comprobante de Liquidación</h1>
            <div class="muted">Sistema RRHH</div>
        </div>
        <div>
            <strong>Número:</strong> #<?= (int)$data['idLiquidacion'] ?><br>
            <strong>Fecha registro:</strong> <?= e($data['Fecha_creacion']) ?>
        </div>
    </div>

    <div class="box">
        <div class="grid">
            <div><strong>Empleado:</strong> <?= e($data['nombre'] . ' ' . $data['apellidos']) ?></div>
            <div><strong>Cédula:</strong> <?= e($data['cedula']) ?></div>
            <div><strong>Puesto:</strong> <?= e($data['puesto']) ?></div>
            <div><strong>Salario mensual:</strong> ₡<?= number_format((float)$data['salario_mensual'], 2, ',', '.') ?></div>
            <div><strong>Fecha ingreso:</strong> <?= e($data['Fecha_de_Ingreso']) ?></div>
            <div><strong>Fecha salida:</strong> <?= e($data['fecha_salida']) ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Rubro</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Salario proporcional</td>
                <td>₡<?= number_format((float)$data['salario_pendiente'], 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Cesantía</td>
                <td>₡<?= number_format((float)$data['cesantia'], 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Vacaciones pendientes</td>
                <td>₡<?= number_format((float)$data['vacaciones_pendientes'], 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Preaviso</td>
                <td>₡<?= number_format((float)$data['preaviso'], 2, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>

    <div class="total">Total liquidación: ₡<?= number_format((float)$data['total_liquidacion'], 2, ',', '.') ?></div>

    <div class="sign">
        <div class="line">Firma RRHH</div>
        <div class="line">Firma empleado</div>
    </div>

    <div class="no-print" style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn" onclick="window.print()">Imprimir</button>
        <a class="btn" href="historial.php">Volver al historial</a>
    </div>
</div>
</body>
</html>