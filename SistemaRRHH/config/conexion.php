<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "recursoshumanos";

$conexion = new mysqli($host, $user, $pass, $db, 3307);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

?>