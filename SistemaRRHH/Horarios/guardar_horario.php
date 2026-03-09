<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

if (!isset($_POST['guardar'])) {
    header("Location: horarios.php");
    exit();
}

$usuario = $_SESSION['idUsuario'];

$inicio = $_POST['fecha_inicio'];
$fin    = $_POST['fecha_fin'];

$horaEntrada = $_POST['Hora_entrada'];
$horaSalida  = $_POST['Hora_Salida'];
$tipoJornada = $_POST['tipo_jornada'];
$modo        = $_POST['modo']; // L_V o TODOS

if (!$inicio || !$fin || !$horaEntrada || !$horaSalida || !$tipoJornada) {
    $_SESSION['mensaje'] = "Todos los campos son obligatorios.";
    $_SESSION['tipo'] = "danger";
    header("Location: horarios.php");
    exit();
}

if ($inicio > $fin) {
    $_SESSION['mensaje'] = "La fecha inicio no puede ser mayor que la fecha fin.";
    $_SESSION['tipo'] = "danger";
    header("Location: horarios.php");
    exit();
}

/* Insert masivo */
$fechaActual = $inicio;
$creados = 0;
$omitidos = 0;

while ($fechaActual <= $fin) {

    // Modo L-V
    if ($modo === "L_V") {
        $diaSemana = date('N', strtotime($fechaActual)); // 1..7
        if ($diaSemana >= 6) {
            $fechaActual = date("Y-m-d", strtotime("+1 day", strtotime($fechaActual)));
            continue;
        }
    }

    // Evitar duplicado por fecha (1 horario por día)
    $check = $conexion->prepare("SELECT idHorario FROM horario WHERE fecha = ? LIMIT 1");
    $check->bind_param("s", $fechaActual);
    $check->execute();
    $existe = $check->get_result();

    if ($existe->num_rows > 0) {
        $omitidos++;
        $fechaActual = date("Y-m-d", strtotime("+1 day", strtotime($fechaActual)));
        continue;
    }

    $stmt = $conexion->prepare("
        INSERT INTO horario
        (fecha, Hora_entrada, Hora_Salida, tipo_jornada,
         Fecha_creacion, Usuario_creacion, Fecha_modificacion, Usuario_modificacion, Estado)
        VALUES (?, ?, ?, ?, NOW(), ?, NOW(), ?, 1)
    ");

    $stmt->bind_param("ssssii", $fechaActual, $horaEntrada, $horaSalida, $tipoJornada, $usuario, $usuario);
    $stmt->execute();

    $creados++;

    $fechaActual = date("Y-m-d", strtotime("+1 day", strtotime($fechaActual)));
}

$_SESSION['mensaje'] = "Plantilla aplicada. Creados: $creados | Omitidos (ya existían): $omitidos";
$_SESSION['tipo'] = "success";

header("Location: horarios.php");
exit();