<?php
session_start();
if(!isset($_SESSION['idUsuario'])){
    header("Location: ../Seguridad/Login.php");
    exit();
}

function validarRol($rolPermitido){
    if($_SESSION['rol'] != $rolPermitido){
        die("Acceso restringido");
    }
}
?>