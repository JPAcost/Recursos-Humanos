<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('can')) {
    function can($permiso) {
        return isset($_SESSION[$permiso]) && (int)$_SESSION[$permiso] === 1;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['NombreRol']) && strtolower(trim($_SESSION['NombreRol'])) === 'admin';
    }
}

if (!function_exists('isJefatura')) {
    function isJefatura() {
        return isset($_SESSION['NombreRol']) && strtolower(trim($_SESSION['NombreRol'])) === 'jefatura';
    }
}

if (!function_exists('isUsuario')) {
    function isUsuario() {
        return isset($_SESSION['NombreRol']) && strtolower(trim($_SESSION['NombreRol'])) === 'usuario';
    }
}