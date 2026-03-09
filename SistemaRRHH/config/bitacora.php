<?php
function registrarBitacora($conexion, $accion, $tabla, $descripcion) {

    if (!isset($_SESSION['idUsuario'])) {
        return;
    }

    $idUsuario = $_SESSION['idUsuario'];
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $conexion->prepare("
        INSERT INTO bitacora 
        (idUsuario, accion, tabla_afectada, descripcion, ip_usuario)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("issss", $idUsuario, $accion, $tabla, $descripcion, $ip);
    $stmt->execute();
}
?>