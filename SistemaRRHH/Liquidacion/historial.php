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
    echo "<div style='margin:20px;padding:12px;border-radius:8px;background:#f8d7da;color:#842029;'>No tiene permisos para acceder al historial de liquidaciones.</div>";
    exit();
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$buscar = trim((string)($_GET['buscar'] ?? ''));
$where = "WHERE l.estado = 'Activo'";
$params = [];
$types = '';

if ($buscar !== '') {
    $where .= " AND (e.nombre LIKE ? OR e.apellidos LIKE ? OR e.cedula LIKE ?)";
    $like = "%{$buscar}%";
    $params = [$like, $like, $like];
    $types = 'sss';
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
        e.idEmpleado,
        e.nombre,
        e.apellidos,
        e.cedula,
        e.puesto
    FROM liquidacion l
    INNER JOIN empleado e ON e.idEmpleado = l.Empleado_idEmpleado
    {$where}
    ORDER BY l.Fecha_creacion DESC, l.idLiquidacion DESC
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . $conexion->error);
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de liquidaciones</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f4f6f9;margin:0;color:#111827}
        .wrap{max-width:1180px;margin:0 auto;padding:24px}
        .header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px}
        .card{background:#fff;border-radius:18px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,.08)}
        h1{margin:0 0 6px}
        .muted{color:#6b7280}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
        .btn{display:inline-block;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:700;border:none;cursor:pointer}
        .btn-primary{background:#2563eb;color:#fff}
        .btn-light{background:#e5e7eb;color:#111827}
        input[type=text]{padding:11px 12px;border:1px solid #d1d5db;border-radius:10px;min-width:280px}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px;vertical-align:top}
        th{background:#f9fafb}
        .actions{display:flex;gap:8px;flex-wrap:wrap}
        .badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:700;margin:2px 4px 2px 0}
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div>
            <h1>Historial de liquidaciones</h1>
            <div class="muted">Consulta las liquidaciones registradas y genera sus comprobantes.</div>
        </div>
        <div class="toolbar">
            <a href="liquidacion.php" class="btn btn-primary">Nueva liquidación</a>
            <a href="../dashboard.php" class="btn btn-light">Volver al dashboard</a>
        </div>
    </div>

    <div class="card">
        <form method="GET" class="toolbar">
            <input type="text" name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar por nombre, apellidos o cédula">
            <button class="btn btn-primary" type="submit">Buscar</button>
            <a href="historial.php" class="btn btn-light">Limpiar</a>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Detalle</th>
                    <th>Fecha salida</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['nombre'] . ' ' . $row['apellidos']) ?></strong><br>
                            <span class="muted"><?= e($row['cedula']) ?></span>
                        </td>
                        <td>
                            <div><?= e($row['puesto']) ?></div>
                            <div class="badge">Salario pendiente: ₡<?= number_format((float)$row['salario_pendiente'], 2, ',', '.') ?></div>
                            <div class="badge">Cesantía: ₡<?= number_format((float)$row['cesantia'], 2, ',', '.') ?></div>
                            <div class="badge">Preaviso: ₡<?= number_format((float)$row['preaviso'], 2, ',', '.') ?></div>
                            <div class="badge">Vacaciones: ₡<?= number_format((float)$row['vacaciones_pendientes'], 2, ',', '.') ?></div>
                        </td>
                        <td><?= e($row['fecha_salida']) ?></td>
                        <td><strong>₡<?= number_format((float)$row['total_liquidacion'], 2, ',', '.') ?></strong></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-light" target="_blank" href="imprimir_liquidacion.php?id=<?= (int)$row['idLiquidacion'] ?>">Imprimir</a>
                                <a class="btn btn-light" href="exportar_liquidacion.php?id=<?= (int)$row['idLiquidacion'] ?>">CSV</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No se encontraron liquidaciones registradas.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>