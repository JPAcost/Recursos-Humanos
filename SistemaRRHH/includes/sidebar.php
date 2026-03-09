<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/Permisos.php');
?>

<div class="sidebar bg-dark text-white p-3" style="min-height:100vh; width:260px;">
    <h4 class="text-center mb-4">Sistema RRHH</h4>

    <ul class="nav flex-column">

        <li class="nav-item mb-2">
            <a href="/SistemaRRHH/dashboard.php" class="nav-link text-white">
                Inicio
            </a>
        </li>

        <?php if (isAdmin() || isJefatura() || can('Empleados_ver')): ?>
            <li class="nav-item mb-2">
                <a href="/SistemaRRHH/Empleados/Empleado.php" class="nav-link text-white">
                    Empleados
                </a>
            </li>
        <?php endif; ?>

        <?php if (isAdmin() || can('Seguridad_gestion_roles_RH')): ?>
            <li class="nav-item mb-2">
                <a href="/SistemaRRHH/Roles/Roles.php" class="nav-link text-white">
                    Gestión de Roles
                </a>
            </li>
        <?php endif; ?>

        <?php if (isAdmin() || can('Seguridad_gestion_usuarios_RH')): ?>
            <li class="nav-item mb-2">
                <a href="/SistemaRRHH/Seguridad/usuarios.php" class="nav-link text-white">
                    Usuarios
                </a>
            </li>
        <?php endif; ?>

        <?php if (
            isAdmin() ||
            isJefatura() ||
            can('Permisos_ver_empleado') ||
            can('Permisos_aprobados_Jefatura') ||
            can('Permisos_aprobados_RH')
        ): ?>
            <li class="nav-item mb-2">
                <a href="/SistemaRRHH/Permisos/Permisos.php" class="nav-link text-white">
                    Permisos
                </a>
            </li>
        <?php endif; ?>

        <?php if (
            isAdmin() ||
            isJefatura() ||
            can('Vacaciones_ver_Empleado') ||
            can('Vacaciones_solicitar_Empleado') ||
            can('Vacaciones_aprobados') ||
            can('Vacaciones_aprobados_RH')
        ): ?>
            <li class="nav-item mb-2">
                <a href="/SistemaRRHH/Vacaciones/Vacaciones.php" class="nav-link text-white">
                    Vacaciones
                </a>
            </li>
        <?php endif; ?>

        <?php if (isAdmin() || can('Planilla_ver_RH')): ?>
            <li class="nav-item mb-2">
                <a href="/SistemaRRHH/Planilla/planillas.php" class="nav-link text-white">
                    Planilla
                </a>
            </li>
        <?php endif; ?>

        <?php if (isAdmin() || can('Asistencia_ver')): ?>
            <li class="nav-item mb-2">
                <a href="/SistemaRRHH/Asistencia/marca_asistencia.php" class="nav-link text-white">
                    Asistencia
                </a>
            </li>
        <?php endif; ?>

        <?php if (isAdmin() || can('Incapacidades_ver')): ?>
            <li class="nav-item mb-2">
                <a href="/SistemaRRHH/incapacidades/incapacidad.php" class="nav-link text-white">
                    Incapacidades
                </a>
            </li>
        <?php endif; ?>

        <li class="nav-item mt-4">
            <a href="/SistemaRRHH/Seguridad/logout.php" class="nav-link text-danger">
                Cerrar sesión
            </a>
        </li>
    </ul>
</div>