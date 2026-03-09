<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$eventos = [];

/* ==========================
   PERMISOS APROBADOS
========================== */
$resultPermisos = $conexion->query("
SELECT 
e.nombre,
e.apellidos,
p.fecha_inicio,
p.fecha_fin
FROM permiso p
JOIN empleado e ON e.idEmpleado = p.Empleado_idEmpleado
WHERE p.estado = 3
");

if ($resultPermisos) {
    while($row = $resultPermisos->fetch_assoc()){
        $eventos[] = [
            "title" => $row['nombre']." ".$row['apellidos']." - Permiso",
            "start" => $row['fecha_inicio'],
            "end" => date('Y-m-d', strtotime($row['fecha_fin'].' +1 day')),
            "color" => "#1abc9c"
        ];
    }
}

/* ==========================
   INCAPACIDADES
========================== */
$resultIncap = $conexion->query("
SELECT 
e.nombre,
e.apellidos,
i.fecha_inicio,
i.fecha_fin
FROM incapacidad i
JOIN empleado e ON e.idEmpleado = i.Empleado_idEmpleado
");

if ($resultIncap) {
    while($row = $resultIncap->fetch_assoc()){
        $eventos[] = [
            "title" => $row['nombre']." ".$row['apellidos']." - Incapacidad",
            "start" => $row['fecha_inicio'],
            "end" => date('Y-m-d', strtotime($row['fecha_fin'].' +1 day')),
            "color" => "#e74c3c"
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Calendario de Ausencias</title>

<link rel="stylesheet" href="../css/dashboard.css">

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<style>
#calendar{
background:white;
padding:20px;
border-radius:12px;
box-shadow:0 4px 10px rgba(0,0,0,0.08);
}
</style>

</head>

<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">

<h1>Calendario de Ausencias</h1>

<div id="calendar"></div>

</div>

<script>

document.addEventListener('DOMContentLoaded', function(){

var calendarEl = document.getElementById('calendar');

var calendar = new FullCalendar.Calendar(calendarEl,{

initialView: 'dayGridMonth',
locale: 'es',
height: 'auto',

events: <?php echo json_encode($eventos); ?>

});

calendar.render();

});

</script>

</body>
</html>