<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarSesion(){
    if (!isset($_SESSION['idUsuario'])) {
        header("Location: ../Seguridad/Login.php");
        exit();
    }
}

function verificarPermiso($permiso){
    if (!isset($_SESSION[$permiso]) || $_SESSION[$permiso] != 1) {
        echo "<div class='alert alert-danger m-4'>
                No tiene permisos para acceder a este módulo.
              </div>";
        exit();
    }
}