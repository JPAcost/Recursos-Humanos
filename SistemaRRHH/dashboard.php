<?php
session_start();

// PROTEGER DASHBOARD
if (!isset($_SESSION['idUsuario'])) {
    header("Location: Seguridad/Login.php");
    exit();
}

include("config/conexion.php");

/* ===========================
   HELPERS PERMISOS
=========================== */
function can($permiso){
    return isset($_SESSION[$permiso]) && (int)$_SESSION[$permiso] === 1;
}

function isAdmin(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'admin';
}

function isJefatura(){
    return isset($_SESSION['NombreRol']) && strtolower($_SESSION['NombreRol']) === 'jefatura';
}

$nombreUsuario = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario');
$nombreRol     = htmlspecialchars($_SESSION['NombreRol'] ?? 'Sin rol');

$hora = (int)date("H");
$saludo = "Bienvenido";
if ($hora >= 5 && $hora < 12) {
    $saludo = "Buenos días";
} elseif ($hora >= 12 && $hora < 18) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas tardes";
}

/* ===========================
   ESTADÍSTICAS
=========================== */

// TOTAL EMPLEADOS
$resultEmpleado = $conexion->query("SELECT COUNT(*) as total FROM empleado WHERE Estado='Activo'");
$totalEmpleados = $resultEmpleado ? (int)($resultEmpleado->fetch_assoc()['total'] ?? 0) : 0;

// TOTAL DEPARTAMENTOS
$resultDeptos = $conexion->query("SELECT COUNT(*) as total FROM departamentos WHERE estado=1");
$totalDeptos = $resultDeptos ? (int)($resultDeptos->fetch_assoc()['total'] ?? 0) : 0;

// TOTAL ROLES
$resultRoles = $conexion->query("SELECT COUNT(*) as total FROM roles WHERE Estado=1");
$totalRoles = $resultRoles ? (int)($resultRoles->fetch_assoc()['total'] ?? 0) : 0;

// VACACIONES PENDIENTES
$resultVacaciones = $conexion->query("SELECT COUNT(*) as total FROM vacaciones WHERE estado IN (0,2)");
$totalVacaciones = $resultVacaciones ? (int)($resultVacaciones->fetch_assoc()['total'] ?? 0) : 0;

// HORAS EXTRA DEL MES
$resultHoras = $conexion->query("
    SELECT SUM(cantidad_horas) as total 
    FROM horas_extras 
    WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE())
");
$totalHoras = $resultHoras ? (float)($resultHoras->fetch_assoc()['total'] ?? 0) : 0;

// PLANILLA DEL MES
$resultPlanilla = $conexion->query("
    SELECT SUM(total_a_pagar) as total 
    FROM planillas
    WHERE MONTH(Fecha_planilla)=MONTH(CURDATE())
      AND YEAR(Fecha_planilla)=YEAR(CURDATE())
");
$totalPlanilla = $resultPlanilla ? (float)($resultPlanilla->fetch_assoc()['total'] ?? 0) : 0;

// LIQUIDACIONES ACTIVAS
$resultLiquidaciones = $conexion->query("
    SELECT COUNT(*) as total
    FROM liquidacion
    WHERE estado = 'Activo'
");
$totalLiquidaciones = $resultLiquidaciones ? (int)($resultLiquidaciones->fetch_assoc()['total'] ?? 0) : 0;

/* ===========================
   ACCIONES SUGERIDAS
=========================== */
$acciones = [];

if (isAdmin() || isJefatura()) {
    $acciones[] = [
        'titulo' => 'Gestionar empleados',
        'descripcion' => 'Registrar, editar o consultar colaboradores activos.',
        'link' => 'Empleados/Empleado.php'
    ];
}

if (can('Vacaciones_aprobados')) {
    $acciones[] = [
        'titulo' => 'Revisar vacaciones pendientes',
        'descripcion' => 'Hay solicitudes que podrían requerir aprobación o seguimiento.',
        'link' => 'Vacaciones/vacaciones.php'
    ];
}

if (can('Planilla_generar') || can('Planilla_ver_RH')) {
    $acciones[] = [
        'titulo' => 'Revisar planilla del mes',
        'descripcion' => 'Consulte o genere la planilla correspondiente al periodo actual.',
        'link' => 'Planilla/planillas.php'
    ];
}

if (can('Horas_extras_ver_RH') || can('Horas_extras_registrar_RH')) {
    $acciones[] = [
        'titulo' => 'Controlar horas extra',
        'descripcion' => 'Verifique horas registradas y mantenga el control mensual.',
        'link' => 'horas_extras/horas_extras.php'
    ];
}

if (can('Liquidaciones_calcular') || isAdmin()) {
    $acciones[] = [
        'titulo' => 'Registrar liquidación',
        'descripcion' => 'Cree nuevas liquidaciones o consulte el historial.',
        'link' => 'Liquidacion/liquidacion.php'
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema RRHH</title>
    <link rel="stylesheet" href="css/dashboard.css?v=3">

    <style>
        .quick-actions-modern{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(230px, 1fr));
            gap:18px;
            margin-top:20px;
        }

        .quick-box{
            display:flex;
            align-items:flex-start;
            gap:14px;
            text-decoration:none;
            background:#ffffff;
            border:1px solid #e7ecf3;
            border-radius:18px;
            padding:18px;
            min-height:125px;
            box-shadow:0 6px 18px rgba(15, 23, 42, 0.06);
            transition:all .25s ease;
        }

        .quick-box:hover{
            transform:translateY(-4px);
            box-shadow:0 12px 24px rgba(15, 23, 42, 0.10);
            border-color:#cfd8e3;
        }

        .quick-icon{
            width:52px;
            height:52px;
            min-width:52px;
            border-radius:14px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:24px;
            font-weight:bold;
            color:#1f2937;
        }

        .quick-info{
            flex:1;
        }

        .quick-info h3{
            margin:0 0 8px 0;
            font-size:17px;
            color:#132238;
            font-weight:700;
        }

        .quick-info p{
            margin:0 0 12px 0;
            font-size:13px;
            color:#667085;
            line-height:1.5;
        }

        .quick-info span{
            font-size:13px;
            font-weight:700;
            color:#1d4ed8;
        }

        .quick-empleados .quick-icon{ background:#dbeafe; }
        .quick-asistencia .quick-icon{ background:#dcfce7; }
        .quick-turnos .quick-icon{ background:#ede9fe; }
        .quick-horarios .quick-icon{ background:#fef3c7; }
        .quick-permisos .quick-icon{ background:#fee2e2; }
        .quick-calendario .quick-icon{ background:#cffafe; }
        .quick-aguinaldo .quick-icon{ background:#fef9c3; }
        .quick-liquidacion .quick-icon{ background:#fae8ff; }
        .quick-reportes .quick-icon{ background:#dbeafe; }

        @media (max-width: 768px){
            .quick-actions-modern{
                grid-template-columns:1fr;
            }

            .quick-box{
                padding:16px;
                min-height:auto;
            }

            .quick-info h3{
                font-size:16px;
            }

            .quick-info p{
                font-size:12.5px;
            }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <h2>SISTEMA RRHH</h2>
        <p>Panel administrativo</p>
    </div>

    <ul>
        <li><a href="dashboard.php" class="active">Dashboard</a></li>

        <li class="menu-title">Gestión</li>

        <?php if (isAdmin() || isJefatura()): ?>
            <li><a href="Empleados/Empleado.php">Empleados</a></li>
            <li><a href="Departamentos/Departamentos.php">Departamentos</a></li>
        <?php endif; ?>

        <?php if (can('Seguridad_gestion_roles_RH')): ?>
            <li><a href="Roles/Roles.php">Roles</a></li>
        <?php endif; ?>

        <?php if (can('Seguridad_gestion_usuarios_RH')): ?>
            <li><a href="Seguridad/usuarios.php">Usuarios</a></li>
        <?php endif; ?>

        <li class="menu-title">Control laboral</li>

        <li><a href="Asistencia/marca_asistencia.php">Asistencia</a></li>

        <?php if (isAdmin() || isJefatura()): ?>
            <li><a href="Turnos/Listar_turnos.php">Turnos</a></li>
            <li><a href="Turnos/Asignar_turno.php">Asignar Turno</a></li>
            <li><a href="Turnos/Generar_turno.php">Generar Turnos</a></li>
            <li><a href="Horarios/horarios.php">Programador de Horarios</a></li>
        <?php endif; ?>

        <?php if (can('Permisos_ver_empleado')): ?>
            <li><a href="Permisos/permisos.php">Permisos</a></li>
        <?php endif; ?>

        <?php if (isAdmin() || isJefatura()): ?>
            <li><a href="Calendario/Calendario.php">Calendario</a></li>
        <?php endif; ?>

        <?php if (can('Horas_extras_ver_RH') || can('Horas_extras_registrar_RH')): ?>
            <li><a href="horas_extras/horas_extras.php">Horas Extras</a></li>
        <?php endif; ?>

        <?php if (can('Vacaciones_ver_Empleado') || can('Vacaciones_solicitar_Empleado') || can('Vacaciones_aprobados')): ?>
            <li><a href="Vacaciones/vacaciones.php">Vacaciones</a></li>
        <?php endif; ?>

        <?php if (isAdmin() || isJefatura()): ?>
            <li><a href="incapacidades/incapacidad.php">Incapacidades</a></li>
        <?php endif; ?>

        <li class="menu-title">Nómina</li>

        <?php if (can('Planilla_ver_RH') || can('Planilla_generar')): ?>
            <li><a href="Planilla/planillas.php">Planilla</a></li>
        <?php endif; ?>

        <?php if (can('Aguinaldos_calcular_RH')): ?>
            <li><a href="Aguinaldo/aguinaldo.php">Aguinaldo</a></li>
        <?php endif; ?>

        <?php if (can('Liquidaciones_calcular') || isAdmin()): ?>
            <li><a href="Liquidacion/liquidacion.php">Liquidaciones</a></li>
            <li><a href="Liquidacion/historial.php">Historial Liquidaciones</a></li>
        <?php endif; ?>

        <?php if (can('Reportes_ver_RH')): ?>
            <li><a href="reportes/reportes.php">Reportes</a></li>
        <?php endif; ?>

        <li class="menu-title">Sistema</li>

        <?php if (isAdmin()): ?>
            <li><a href="Bitacora/vista_bitacora.php">Bitácora</a></li>
        <?php endif; ?>

        <li><a href="Seguridad/Logout.php" class="logout">Cerrar sesión</a></li>
    </ul>
</aside>

<main class="main-content">

    <header class="topbar">
        <div>
            <p class="topbar-label">Dashboard principal</p>
            <h1><?php echo $saludo; ?>, <?php echo $nombreUsuario; ?></h1>
            <p class="topbar-subtitle">
                Rol actual: <strong><?php echo $nombreRol; ?></strong>
            </p>
        </div>
    </header>

    <section class="section">
        <div class="section-header">
            <h2>Resumen General</h2>
            <p>Indicadores principales del sistema</p>
        </div>

        <div class="stats-grid">

            <?php if (isAdmin() || isJefatura()): ?>
            <a href="Empleados/Empleado.php" class="card-link">
                <div class="card stat-card empleados">
                    <span class="card-label">Empleados activos</span>
                    <h3><?php echo $totalEmpleados; ?></h3>
                    <small>Colaboradores registrados</small>
                </div>
            </a>
            <?php endif; ?>

            <?php if (isAdmin() || isJefatura()): ?>
            <a href="Departamentos/Departamentos.php" class="card-link">
                <div class="card stat-card departamentos">
                    <span class="card-label">Departamentos</span>
                    <h3><?php echo $totalDeptos; ?></h3>
                    <small>Departamentos activos</small>
                </div>
            </a>
            <?php endif; ?>

            <?php if (can('Seguridad_gestion_roles_RH')): ?>
            <a href="Roles/Roles.php" class="card-link">
                <div class="card stat-card roles">
                    <span class="card-label">Roles</span>
                    <h3><?php echo $totalRoles; ?></h3>
                    <small>Roles habilitados</small>
                </div>
            </a>
            <?php endif; ?>

            <?php if (can('Planilla_ver_RH') || can('Planilla_generar')): ?>
            <a href="Planilla/planillas.php" class="card-link">
                <div class="card stat-card planilla">
                    <span class="card-label">Planilla del mes</span>
                    <h3>₡<?php echo number_format($totalPlanilla, 0, ',', '.'); ?></h3>
                    <small>Total acumulado</small>
                </div>
            </a>
            <?php endif; ?>

            <?php if (can('Horas_extras_ver_RH') || can('Horas_extras_registrar_RH')): ?>
            <a href="horas_extras/horas_extras.php" class="card-link">
                <div class="card stat-card horas">
                    <span class="card-label">Horas extra</span>
                    <h3><?php echo number_format($totalHoras, 0, ',', '.'); ?> hrs</h3>
                    <small>Registradas este mes</small>
                </div>
            </a>
            <?php endif; ?>

            <?php if (can('Liquidaciones_calcular') || isAdmin()): ?>
            <a href="Liquidacion/historial.php" class="card-link">
                <div class="card stat-card liquidaciones">
                    <span class="card-label">Liquidaciones</span>
                    <h3><?php echo $totalLiquidaciones; ?></h3>
                    <small>Liquidaciones activas</small>
                </div>
            </a>
            <?php endif; ?>

        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <h2>Pendientes Importantes</h2>
            <p>Elementos que podrían requerir atención inmediata</p>
        </div>

        <div class="alerts-grid">
            <?php if (can('Vacaciones_aprobados')): ?>
                <a href="Vacaciones/vacaciones.php" class="alert-card warning">
                    <div>
                        <h3>Vacaciones pendientes</h3>
                        <p>Actualmente hay <strong><?php echo $totalVacaciones; ?></strong> solicitudes por revisar.</p>
                    </div>
                    <span class="alert-action">Revisar</span>
                </a>
            <?php endif; ?>

            <?php if (can('Horas_extras_ver_RH') || can('Horas_extras_registrar_RH')): ?>
                <a href="horas_extras/horas_extras.php" class="alert-card info">
                    <div>
                        <h3>Horas extra del mes</h3>
                        <p>Se han acumulado <strong><?php echo number_format($totalHoras, 0, ',', '.'); ?> horas</strong> en el periodo actual.</p>
                    </div>
                    <span class="alert-action">Ver detalle</span>
                </a>
            <?php endif; ?>

            <?php if (can('Planilla_ver_RH') || can('Planilla_generar')): ?>
                <a href="Planilla/planillas.php" class="alert-card success">
                    <div>
                        <h3>Planilla del periodo</h3>
                        <p>El total registrado del mes es de <strong>₡<?php echo number_format($totalPlanilla, 0, ',', '.'); ?></strong>.</p>
                    </div>
                    <span class="alert-action">Gestionar</span>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <h2>¿Qué desea hacer hoy?</h2>
            <p>Acciones sugeridas según sus permisos</p>
        </div>

        <div class="task-grid">
            <?php if (!empty($acciones)): ?>
                <?php foreach ($acciones as $accion): ?>
                    <a href="<?php echo htmlspecialchars($accion['link']); ?>" class="task-card">
                        <h3><?php echo htmlspecialchars($accion['titulo']); ?></h3>
                        <p><?php echo htmlspecialchars($accion['descripcion']); ?></p>
                        <span>Ir al módulo →</span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="task-card no-action">
                    <h3>Sin acciones sugeridas</h3>
                    <p>No hay módulos prioritarios disponibles con sus permisos actuales.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <h2>Accesos Rápidos</h2>
            <p>Operaciones frecuentes del sistema</p>
        </div>

        <div class="quick-actions-modern">

            <?php if (isAdmin() || isJefatura()): ?>
                <a href="Empleados/Empleado.php" class="quick-box quick-empleados">
                    <div class="quick-icon">👤</div>
                    <div class="quick-info">
                        <h3>Nuevo Empleado</h3>
                        <p>Registrar nuevos colaboradores dentro del sistema.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>
            <?php endif; ?>

            <a href="Asistencia/marca_asistencia.php" class="quick-box quick-asistencia">
                <div class="quick-icon">🕒</div>
                <div class="quick-info">
                    <h3>Registrar Asistencia</h3>
                    <p>Controlar entradas y salidas del personal.</p>
                    <span>Abrir módulo →</span>
                </div>
            </a>

            <?php if (isAdmin() || isJefatura()): ?>
                <a href="Turnos/Asignar_turno.php" class="quick-box quick-turnos">
                    <div class="quick-icon">📌</div>
                    <div class="quick-info">
                        <h3>Asignar Turno</h3>
                        <p>Asignar turnos laborales de forma individual.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>

                <a href="Turnos/Generar_turno.php" class="quick-box quick-turnos">
                    <div class="quick-icon">📅</div>
                    <div class="quick-info">
                        <h3>Generar Turnos</h3>
                        <p>Crear turnos automáticamente por periodo.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>

                <a href="Turnos/Listar_turnos.php" class="quick-box quick-turnos">
                    <div class="quick-icon">📋</div>
                    <div class="quick-info">
                        <h3>Ver Turnos</h3>
                        <p>Consultar los turnos ya registrados.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>

                <a href="Horarios/horarios.php" class="quick-box quick-horarios">
                    <div class="quick-icon">⏰</div>
                    <div class="quick-info">
                        <h3>Programar Horarios</h3>
                        <p>Configurar jornadas y horarios de trabajo.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>
            <?php endif; ?>

            <?php if (can('Permisos_ver_empleado')): ?>
                <a href="Permisos/permisos.php" class="quick-box quick-permisos">
                    <div class="quick-icon">🔐</div>
                    <div class="quick-info">
                        <h3>Gestionar Permisos</h3>
                        <p>Administrar permisos y solicitudes del personal.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>
            <?php endif; ?>

            <?php if (isAdmin() || isJefatura()): ?>
                <a href="Calendario/Calendario.php" class="quick-box quick-calendario">
                    <div class="quick-icon">🗓️</div>
                    <div class="quick-info">
                        <h3>Calendario de Ausencias</h3>
                        <p>Visualizar vacaciones, permisos e incapacidades.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>
            <?php endif; ?>

            <?php if (can('Aguinaldos_calcular_RH')): ?>
                <a href="Aguinaldo/aguinaldo.php" class="quick-box quick-aguinaldo">
                    <div class="quick-icon">💰</div>
                    <div class="quick-info">
                        <h3>Calcular Aguinaldo</h3>
                        <p>Procesar el aguinaldo correspondiente al personal.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>
            <?php endif; ?>

            <?php if (can('Liquidaciones_calcular') || isAdmin()): ?>
                <a href="Liquidacion/liquidacion.php" class="quick-box quick-liquidacion">
                    <div class="quick-icon">🧾</div>
                    <div class="quick-info">
                        <h3>Nueva Liquidación</h3>
                        <p>Registrar nuevas liquidaciones laborales.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>

                <a href="Liquidacion/historial.php" class="quick-box quick-liquidacion">
                    <div class="quick-icon">📂</div>
                    <div class="quick-info">
                        <h3>Historial Liquidaciones</h3>
                        <p>Consultar liquidaciones hechas anteriormente.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>
            <?php endif; ?>

            <?php if (can('Reportes_ver_RH')): ?>
                <a href="reportes/reportes.php" class="quick-box quick-reportes">
                    <div class="quick-icon">📊</div>
                    <div class="quick-info">
                        <h3>Ver Reportes</h3>
                        <p>Acceder a estadísticas e informes del sistema.</p>
                        <span>Abrir módulo →</span>
                    </div>
                </a>
            <?php endif; ?>

        </div>
    </section>

</main>

</body>
</html>