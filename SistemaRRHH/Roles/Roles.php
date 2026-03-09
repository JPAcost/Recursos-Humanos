<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

/* ============================
   VALIDACIÓN DE PERMISO
============================ */
if (!isset($_SESSION['Seguridad_gestion_roles_RH']) || $_SESSION['Seguridad_gestion_roles_RH'] != 1) {
    echo "<div class='alert alert-danger m-4'>
            No tiene permisos para acceder al módulo de roles.
          </div>";
    exit();
}

$editar = false;
$datosEditar = [];
$usuarioSesion = (int)($_SESSION['idUsuario'] ?? 0);

/* ============================
   FUNCIÓN PARA CHECKBOX
============================ */
function permiso($campo){
    return isset($_POST[$campo]) ? 1 : 0;
}

/* ============================
   INSERTAR
============================ */
if (isset($_POST['guardar'])) {

    $v1  = permiso("Vacaciones_ver_Empleado");
    $v2  = permiso("Vacaciones_solicitar_Empleado");
    $v3  = permiso("Permisos_ver_empleado");
    $v4  = permiso("Vacaciones_aprobados");
    $v5  = permiso("Permisos_aprobados_RH");
    $v6  = permiso("asistencia_ver_RH");
    $v7  = permiso("Planilla_ver_RH");
    $v8  = permiso("Planilla_generar");
    $v9  = permiso("Horas_extras_ver_RH");
    $v10 = permiso("Horas_extras_registrar_RH");
    $v11 = permiso("Liquidaciones_calcular");
    $v12 = permiso("Aguinaldos_calcular_RH");
    $v13 = permiso("Seguridad_gestion_usuarios_RH");
    $v14 = permiso("Seguridad_gestion_roles_RH");
    $v15 = permiso("Mantenimiento_RH");
    $v16 = permiso("Reportes_ver_RH");

    $stmt = $conexion->prepare("
        INSERT INTO roles (
            Vacaciones_ver_Empleado,
            Vacaciones_solicitar_Empleado,
            Permisos_ver_empleado,
            Vacaciones_aprobados,
            Permisos_aprobados_RH,
            asistencia_ver_RH,
            Planilla_ver_RH,
            Planilla_generar,
            Horas_extras_ver_RH,
            Horas_extras_registrar_RH,
            Liquidaciones_calcular,
            Aguinaldos_calcular_RH,
            Seguridad_gestion_usuarios_RH,
            Seguridad_gestion_roles_RH,
            Mantenimiento_RH,
            Reportes_ver_RH,
            Fecha_creacion,
            Usuario_creacion,
            Fecha_modificacion,
            Estado
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),1)
    ");

    $stmt->bind_param(
        "iiiiiiiiiiiiiiiii",
        $v1,$v2,$v3,$v4,$v5,$v6,$v7,$v8,
        $v9,$v10,$v11,$v12,$v13,$v14,$v15,$v16,
        $usuarioSesion
    );

    $stmt->execute();

    header("Location: Roles.php");
    exit();
}

/* ============================
   EDITAR
============================ */
if (isset($_GET['editar'])) {
    $editar = true;
    $idEditar = (int)$_GET['editar'];

    $stmtEditar = $conexion->prepare("SELECT * FROM roles WHERE idRoles = ?");
    $stmtEditar->bind_param("i", $idEditar);
    $stmtEditar->execute();
    $datosEditar = $stmtEditar->get_result()->fetch_assoc();
}

/* ============================
   ACTUALIZAR
============================ */
if (isset($_POST['actualizar'])) {

    $idRol = (int)($_POST['idRoles'] ?? 0);

    $v1  = permiso("Vacaciones_ver_Empleado");
    $v2  = permiso("Vacaciones_solicitar_Empleado");
    $v3  = permiso("Permisos_ver_empleado");
    $v4  = permiso("Vacaciones_aprobados");
    $v5  = permiso("Permisos_aprobados_RH");
    $v6  = permiso("asistencia_ver_RH");
    $v7  = permiso("Planilla_ver_RH");
    $v8  = permiso("Planilla_generar");
    $v9  = permiso("Horas_extras_ver_RH");
    $v10 = permiso("Horas_extras_registrar_RH");
    $v11 = permiso("Liquidaciones_calcular");
    $v12 = permiso("Aguinaldos_calcular_RH");
    $v13 = permiso("Seguridad_gestion_usuarios_RH");
    $v14 = permiso("Seguridad_gestion_roles_RH");
    $v15 = permiso("Mantenimiento_RH");
    $v16 = permiso("Reportes_ver_RH");

    $stmt = $conexion->prepare("
        UPDATE roles SET
            Vacaciones_ver_Empleado = ?,
            Vacaciones_solicitar_Empleado = ?,
            Permisos_ver_empleado = ?,
            Vacaciones_aprobados = ?,
            Permisos_aprobados_RH = ?,
            asistencia_ver_RH = ?,
            Planilla_ver_RH = ?,
            Planilla_generar = ?,
            Horas_extras_ver_RH = ?,
            Horas_extras_registrar_RH = ?,
            Liquidaciones_calcular = ?,
            Aguinaldos_calcular_RH = ?,
            Seguridad_gestion_usuarios_RH = ?,
            Seguridad_gestion_roles_RH = ?,
            Mantenimiento_RH = ?,
            Reportes_ver_RH = ?,
            Fecha_modificacion = NOW()
        WHERE idRoles = ?
    ");

    $stmt->bind_param(
        "iiiiiiiiiiiiiiiii",
        $v1,$v2,$v3,$v4,$v5,$v6,$v7,$v8,
        $v9,$v10,$v11,$v12,$v13,$v14,$v15,$v16,
        $idRol
    );

    $stmt->execute();

    header("Location: Roles.php");
    exit();
}

/* ============================
   INACTIVAR
============================ */
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];

    $stmtEliminar = $conexion->prepare("UPDATE roles SET Estado = 0, Fecha_modificacion = NOW() WHERE idRoles = ?");
    $stmtEliminar->bind_param("i", $id);
    $stmtEliminar->execute();

    header("Location: Roles.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Módulo Roles</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <div class="container-fluid mt-4">

        <div class="card shadow p-4">

            <h3 class="mb-4">
                <?php echo $editar ? "Editar Rol" : "Crear Nuevo Rol"; ?>
            </h3>

            <form method="POST" class="row g-3">

                <?php if($editar): ?>
                    <input type="hidden" name="idRoles" value="<?php echo htmlspecialchars($datosEditar['idRoles']); ?>">
                <?php endif; ?>

                <?php
                $permisos = [
                    "Vacaciones_ver_Empleado"        => "Acceso a vacaciones (ver)",
                    "Vacaciones_solicitar_Empleado"  => "Solicitar vacaciones",
                    "Permisos_ver_empleado"          => "Acceso a permisos (ver)",
                    "Vacaciones_aprobados"           => "Aprobar vacaciones",
                    "Permisos_aprobados_RH"          => "Aprobar permisos (RRHH)",
                    "asistencia_ver_RH"              => "Ver asistencia (RRHH)",
                    "Planilla_ver_RH"                => "Ver planilla (RRHH)",
                    "Planilla_generar"               => "Generar planilla",
                    "Horas_extras_ver_RH"            => "Ver horas extra (RRHH)",
                    "Horas_extras_registrar_RH"      => "Registrar horas extra (RRHH)",
                    "Liquidaciones_calcular"         => "Calcular liquidaciones",
                    "Aguinaldos_calcular_RH"         => "Calcular aguinaldos (RRHH)",
                    "Seguridad_gestion_usuarios_RH"  => "Gestionar usuarios (seguridad)",
                    "Seguridad_gestion_roles_RH"     => "Gestionar roles (seguridad)",
                    "Mantenimiento_RH"               => "Mantenimiento (RRHH)",
                    "Reportes_ver_RH"                => "Ver reportes (RRHH)"
                ];

                foreach ($permisos as $campo => $texto) {
                    $checked = ($editar && isset($datosEditar[$campo]) && (int)$datosEditar[$campo] === 1) ? "checked" : "";
                    echo "
                    <div class='col-md-4'>
                        <div class='form-check'>
                            <input class='form-check-input' type='checkbox' name='".htmlspecialchars($campo)."' id='".htmlspecialchars($campo)."' $checked>
                            <label class='form-check-label' for='".htmlspecialchars($campo)."'>".htmlspecialchars($texto)."</label>
                        </div>
                    </div>";
                }
                ?>

                <div class="col-12 mt-3 form-actions">
                    <?php if($editar): ?>
                        <button type="submit" name="actualizar" class="btn btn-warning">Actualizar</button>
                        <a href="Roles.php" class="btn btn-secondary">Cancelar</a>
                    <?php else: ?>
                        <button type="submit" name="guardar" class="btn btn-success">Guardar</button>
                    <?php endif; ?>
                </div>

            </form>

            <hr class="my-4">

            <h4>Lista de Roles</h4>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Estado</th>
                            <th width="200">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $roles = $conexion->query("SELECT idRoles, Estado FROM roles WHERE Estado = 1");

                        if ($roles && $roles->num_rows > 0) {
                            while ($r = $roles->fetch_assoc()) {
                                echo "<tr>
                                    <td>".htmlspecialchars($r['idRoles'])."</td>
                                    <td>".((int)$r['Estado'] === 1
                                        ? '<span class=\"badge bg-success\">Activo</span>'
                                        : '<span class=\"badge bg-secondary\">Inactivo</span>')."</td>
                                    <td class='acciones-btn d-flex gap-2'>
                                        <a href='?editar={$r['idRoles']}' class='btn btn-warning btn-sm me-2'>Editar</a>
                                        <a href='?eliminar={$r['idRoles']}'
                                           class='btn btn-danger btn-sm'
                                           onclick=\"return confirm('¿Está seguro de inactivar este rol?');\">
                                            Inactivar
                                        </a>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' class='text-center'>No hay roles registrados</td></tr>";
                        }
                        ?>
                    </tbody>

                </table>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>