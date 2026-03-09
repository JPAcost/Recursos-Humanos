<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

if (!isset($_POST['guardar'])) {
    header("Location: asignar_turno.php");
    exit();
}

$idEmpleado   = (int)($_POST['idEmpleado'] ?? 0);
$fecha        = $_POST['fecha'] ?? null;
$horaEntrada  = $_POST['hora_entrada'] ?? null;
$horaSalida   = $_POST['hora_salida'] ?? null;
$usuario      = (int)$_SESSION['idUsuario'];

try {
    if (!$idEmpleado || !$fecha || !$horaEntrada || !$horaSalida) {
        throw new Exception("Todos los campos son obligatorios.");
    }

    // 1) Validar empleado activo
    $emp = $conexion->prepare("SELECT Estado FROM empleado WHERE idEmpleado=?");
    $emp->bind_param("i", $idEmpleado);
    $emp->execute();
    $empData = $emp->get_result()->fetch_assoc();

    if (!$empData || $empData['Estado'] !== 'Activo') {
        throw new Exception("Empleado inválido o inactivo.");
    }

    // 2) Validar permiso en esa fecha (si aplica)
    $perm = $conexion->prepare("
        SELECT idPermiso
        FROM permiso
        WHERE Empleado_idEmpleado = ?
        AND estado = 1
        AND ? BETWEEN fecha_inicio AND fecha_fin
        LIMIT 1
    ");
    $perm->bind_param("is", $idEmpleado, $fecha);
    $perm->execute();
    if ($perm->get_result()->num_rows > 0) {
        throw new Exception("El empleado tiene un permiso en esa fecha. No se puede asignar turno.");
    }

    // 3) Validar que no exista turno ya asignado ese día
    $val = $conexion->prepare("
        SELECT idAsignacion
        FROM empleado_turno
        WHERE idEmpleado = ?
        AND fecha = ?
        AND Estado = 1
        LIMIT 1
    ");
    $val->bind_param("is", $idEmpleado, $fecha);
    $val->execute();
    if ($val->get_result()->num_rows > 0) {
        throw new Exception("Ese empleado ya tiene un turno asignado para esa fecha.");
    }

    // 4) Reusar horario si ya existe uno igual (misma fecha + entrada + salida + normal + activo)
    $checkHorario = $conexion->prepare("
        SELECT idHorario
        FROM horario
        WHERE fecha = ?
        AND Hora_entrada = ?
        AND Hora_Salida = ?
        AND tipo_jornada = 'normal'
        AND Estado = 1
        LIMIT 1
    ");
    $checkHorario->bind_param("sss", $fecha, $horaEntrada, $horaSalida);
    $checkHorario->execute();
    $hRes = $checkHorario->get_result()->fetch_assoc();

    if ($hRes) {
        $idHorario = (int)$hRes['idHorario'];
    } else {
        // 5) Insertar en horario (solo si no existía)
        $stmtHorario = $conexion->prepare("
            INSERT INTO horario
            (fecha, Hora_entrada, Hora_Salida, tipo_jornada,
             Fecha_creacion, Usuario_creacion, Fecha_modificacion, Usuario_modificacion, Estado)
            VALUES (?, ?, ?, 'normal', NOW(), ?, NOW(), ?, 1)
        ");
        $stmtHorario->bind_param("sssii", $fecha, $horaEntrada, $horaSalida, $usuario, $usuario);
        $stmtHorario->execute();
        $idHorario = (int)$stmtHorario->insert_id;
    }

    // 6) Insertar en empleado_turno (asignación)
    $stmtAsignacion = $conexion->prepare("
        INSERT INTO empleado_turno (idEmpleado, idTurno, fecha, Estado)
        VALUES (?, ?, ?, 1)
    ");
    $stmtAsignacion->bind_param("iis", $idEmpleado, $idHorario, $fecha);
    $stmtAsignacion->execute();

    $_SESSION['mensaje'] = "Turno asignado correctamente.";
    $_SESSION['tipo'] = "success";
    header("Location: Listar_turnos.php");
    exit();

} catch (Exception $e) {
    $_SESSION['mensaje'] = $e->getMessage();
    $_SESSION['tipo'] = "danger";
    header("Location: asignar_turno.php");
    exit();
}