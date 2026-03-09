<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$id = $_GET['id'] ?? null;

if ($id) {

    $sql = "UPDATE empleado_turno
            SET Estado = 0
            WHERE idAsignacion = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $_SESSION['mensaje'] = "Turno anulado correctamente.";
    $_SESSION['tipo'] = "success";
}

header("Location: listar_turnos.php");
exit();