<?php
include("../config/conexion.php");

$id = $_GET['id'];

$conexion->query("DELETE FROM horas_extras WHERE idHoras_Extras=$id");

header("Location: horas_extras.php");