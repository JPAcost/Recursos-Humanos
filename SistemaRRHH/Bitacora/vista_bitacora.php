<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: login.php");
    exit();
}

$idUsuario = $_SESSION['idUsuario'];

$consultaRol = $conexion->query("
    SELECT r.Seguridad_gestion_roles_RH
    FROM usuario u
    INNER JOIN roles r ON u.idRoles = r.idRoles
    WHERE u.idUsuario = $idUsuario
");

$permiso = $consultaRol->fetch_assoc();

if ($permiso['Seguridad_gestion_roles_RH'] != 1) {
    echo "<h3 style='color:red;text-align:center;'>Acceso denegado</h3>";
    exit();
}

$where = "WHERE 1=1";

if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
    $desde = $_GET['desde'];
    $hasta = $_GET['hasta'];
    $where .= " AND DATE(b.fecha) BETWEEN '$desde' AND '$hasta'";
}

if (!empty($_GET['usuario'])) {
    $usuario = $_GET['usuario'];
    $where .= " AND u.Nombre LIKE '%$usuario%'";
}

$query = "
    SELECT b.*, u.Nombre
    FROM bitacora b
    INNER JOIN usuario u ON b.idUsuario = u.idUsuario
    $where
    ORDER BY b.fecha DESC
";

$result = $conexion->query($query);
$total = $result->num_rows;
?>

<!DOCTYPE html>
<html>
<head>
<title>Bitácora del Sistema</title>
<style>
body{font-family:Arial;background:#f4f6f9;padding:20px;}
h2{text-align:center;}
.filtro{text-align:center;margin-bottom:20px;}
input,button{padding:6px;margin:5px;}
button{background:#2c3e50;color:white;border:none;cursor:pointer;}
button:hover{background:#34495e;}
table{width:100%;border-collapse:collapse;background:white;}
th{background:#2c3e50;color:white;}
th,td{padding:8px;border:1px solid #ddd;text-align:center;}
tr:hover{background:#f2f2f2;}
.contador{text-align:center;font-weight:bold;margin-bottom:10px;}
.export{float:right;margin-bottom:10px;}
</style>
</head>
<body>

<h2>Bitácora del Sistema</h2>

<div class="contador">
Total de registros: <?= $total ?>
</div>

<div class="filtro">
<form method="GET">
    Desde: <input type="date" name="desde">
    Hasta: <input type="date" name="hasta">
    Usuario: <input type="text" name="usuario" placeholder="Buscar usuario">
    <button type="submit">Filtrar</button>
</form>
</div>

<div class="export">
    <a href="bitacora_pdf.php" target="_blank">
        <button>Exportar a PDF</button>
    </a>
</div>

<table>
<tr>
    <th>Usuario</th>
    <th>Acción</th>
    <th>Tabla</th>
    <th>Descripción</th>
    <th>Fecha</th>
    <th>IP</th>
</tr>

<?php while($row = $result->fetch_assoc()) { ?>
<tr>
    <td><?= $row['Nombre'] ?></td>
    <td><?= $row['accion'] ?></td>
    <td><?= $row['tabla_afectada'] ?></td>
    <td><?= $row['descripcion'] ?></td>
    <td><?= $row['fecha'] ?></td>
    <td><?= $row['ip_usuario'] ?></td>
</tr>
<?php } ?>

</table>

</body>
</html>