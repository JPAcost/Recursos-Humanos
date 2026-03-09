<?php
session_start();
require_once("../includes/Permisos.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$idRol = (int)($_SESSION['idRoles'] ?? 0);

if ($idRol === 1 || isJefatura()) {
    header("Location: AprobarPermisosJefatura.php");
    exit();
}

if ($idRol === 2 || isAdmin()) {
    header("Location: AprobarPermisosRH.php");
    exit();
}

if ($idRol === 4 || isUsuario() || can('Permisos_ver_empleado')) {
    header("Location: MisPermisos.php");
    exit();
}

echo "<div style='margin:20px; font-family:Segoe UI, Arial'>
        <div style='padding:12px; border-radius:10px; background:#fee2e2; color:#7f1d1d;'>
            No tiene permisos para acceder al módulo de permisos.
        </div>
      </div>";