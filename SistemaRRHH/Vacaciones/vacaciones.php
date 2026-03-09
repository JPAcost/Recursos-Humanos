<?php
session_start();

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

function can($permiso){
    return isset($_SESSION[$permiso]) && (int)$_SESSION[$permiso] === 1;
}

function isAdmin(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'admin';
}

function isJefatura(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'jefatura';
}

/* PRIMERO RH / ADMIN */
if (can('Vacaciones_aprobados_RH') || isAdmin()) {
    header("Location: AprobarVacacionesRH.php");
    exit();
}

/* DESPUÉS JEFATURA */
if (can('Vacaciones_aprobados') || isJefatura()) {
    header("Location: AprobarVacacionesJefatura.php");
    exit();
}

/* DESPUÉS EMPLEADO */
if (can('Vacaciones_ver_Empleado') || can('Vacaciones_solicitar_Empleado')) {
    header("Location: MisVacaciones.php");
    exit();
}

echo "<div style='margin:20px; font-family:Segoe UI, Arial'>
        <div style='padding:12px; border-radius:10px; background:#fee2e2; color:#7f1d1d;'>
            No tiene permisos para acceder al módulo de vacaciones.
        </div>
      </div>";