<?php
session_start();
require_once("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

/* ==========================
   VALIDACIÓN DE PERMISOS
========================== */
$usuarioSesion = (int)($_SESSION['idUsuario'] ?? 0);
$puedeMantener = (int)($_SESSION['Mantenimiento_RH'] ?? 0) === 1;

function postv($k, $d = '') { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function getv($k, $d = '')  { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

function calcularSalarioMensual(float $salarioHora): float {
    return round($salarioHora * 8 * 30, 2);
}

$editar = false;
$datosEditar = [];

$msg = getv('msg', '');
$err = getv('err', '');

/* ==========================
   BUSCADOR + PAGINACIÓN
========================== */
$buscar = getv('buscar', '');
$limite = 5;
$pagina = max(1, (int)getv('pagina', 1));
$inicio = ($pagina - 1) * $limite;

$like = "%{$buscar}%";

/* ==========================
   INSERTAR
========================== */
if (isset($_POST['guardar'])) {

    if (!$puedeMantener) {
        header("Location: Empleado.php?err=" . urlencode("No tiene permisos de mantenimiento."));
        exit();
    }

    $nombre            = postv('nombre');
    $apellidos         = postv('apellidos');
    $cedula            = postv('cedula');
    $salarioHora       = (float)postv('salario_hora', '0');
    $salarioMensual    = calcularSalarioMensual($salarioHora);
    $puesto            = postv('puesto');
    $estado            = postv('estado', 'Activo');
    $depto             = (int)postv('departamento', '0');
    $fechaIngreso      = postv('fecha_ingreso');
    $idUsuarioAsociado = (int)postv('Usuario_idUsuario', '0');

    if ($nombre === '' || $apellidos === '' || $cedula === '' || $puesto === '' || $fechaIngreso === '') {
        header("Location: Empleado.php?err=" . urlencode("Complete todos los campos obligatorios."));
        exit();
    }

    if ($salarioHora <= 0) {
        header("Location: Empleado.php?err=" . urlencode("El salario por hora debe ser mayor a cero."));
        exit();
    }

    if ($depto <= 0) {
        header("Location: Empleado.php?err=" . urlencode("Seleccione un departamento."));
        exit();
    }

    if ($idUsuarioAsociado <= 0) {
        header("Location: Empleado.php?err=" . urlencode("Seleccione un usuario válido."));
        exit();
    }

    $dup = $conexion->prepare("SELECT COUNT(*) c FROM empleado WHERE cedula = ?");
    $dup->bind_param("s", $cedula);
    $dup->execute();
    $c = (int)$dup->get_result()->fetch_assoc()['c'];

    if ($c > 0) {
        header("Location: Empleado.php?err=" . urlencode("La cédula ya está registrada."));
        exit();
    }

    $valUser = $conexion->prepare("SELECT COUNT(*) c FROM usuario WHERE idUsuario = ? AND Estado = 1");
    $valUser->bind_param("i", $idUsuarioAsociado);
    $valUser->execute();
    $cUser = (int)$valUser->get_result()->fetch_assoc()['c'];

    if ($cUser <= 0) {
        header("Location: Empleado.php?err=" . urlencode("El usuario seleccionado no existe o está inactivo."));
        exit();
    }

    $valRelacion = $conexion->prepare("SELECT COUNT(*) c FROM empleado WHERE Usuario_idUsuario = ?");
    $valRelacion->bind_param("i", $idUsuarioAsociado);
    $valRelacion->execute();
    $cRelacion = (int)$valRelacion->get_result()->fetch_assoc()['c'];

    if ($cRelacion > 0) {
        header("Location: Empleado.php?err=" . urlencode("Ese usuario ya está asociado a otro empleado."));
        exit();
    }

    $stmt = $conexion->prepare("
        INSERT INTO empleado
        (
            nombre, apellidos, cedula, salario_hora, salario_mensual, puesto, Estado,
            Departamentos_idDepartamentos, Usuario_idUsuario, Fecha_de_Ingreso, Fecha_de_Modificacion,
            usuario_creacion, usuario_modificacion
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)
    ");

    $stmt->bind_param(
        "sssddssiisii",
        $nombre,
        $apellidos,
        $cedula,
        $salarioHora,
        $salarioMensual,
        $puesto,
        $estado,
        $depto,
        $idUsuarioAsociado,
        $fechaIngreso,
        $usuarioSesion,
        $usuarioSesion
    );

    if (!$stmt->execute()) {
        header("Location: Empleado.php?err=" . urlencode("Error al insertar: " . $stmt->error));
        exit();
    }

    header("Location: Empleado.php?msg=" . urlencode("Empleado registrado correctamente"));
    exit();
}

/* ==========================
   EDITAR (cargar datos)
========================== */
if (isset($_GET['editar'])) {
    $editar = true;
    $idEditar = (int)getv('editar', 0);

    $st = $conexion->prepare("
        SELECT e.*, u.Nombre AS usuarioNombre, u.Apellido AS usuarioApellido, u.Email AS usuarioEmail
        FROM empleado e
        LEFT JOIN usuario u ON u.idUsuario = e.Usuario_idUsuario
        WHERE e.idEmpleado = ?
    ");
    $st->bind_param("i", $idEditar);
    $st->execute();
    $datosEditar = $st->get_result()->fetch_assoc();

    if (!$datosEditar) {
        header("Location: Empleado.php?err=" . urlencode("Empleado no encontrado."));
        exit();
    }
}

/* ==========================
   ACTUALIZAR
========================== */
if (isset($_POST['actualizar'])) {

    if (!$puedeMantener) {
        header("Location: Empleado.php?err=" . urlencode("No tiene permisos de mantenimiento."));
        exit();
    }

    $idEmpleado      = (int)($_POST['idEmpleado'] ?? 0);
    $nombre          = postv('nombre');
    $apellidos       = postv('apellidos');
    $cedula          = postv('cedula');
    $salarioHora     = (float)postv('salario_hora', '0');
    $salarioMensual  = calcularSalarioMensual($salarioHora);
    $puesto          = postv('puesto');
    $estado          = postv('estado', 'Activo');
    $depto           = (int)postv('departamento', '0');
    $fechaIngreso    = postv('fecha_ingreso');

    if ($idEmpleado <= 0) {
        header("Location: Empleado.php?err=" . urlencode("ID inválido."));
        exit();
    }

    if ($nombre === '' || $apellidos === '' || $cedula === '' || $puesto === '' || $fechaIngreso === '') {
        header("Location: Empleado.php?err=" . urlencode("Complete todos los campos obligatorios."));
        exit();
    }

    if ($salarioHora <= 0) {
        header("Location: Empleado.php?err=" . urlencode("El salario por hora debe ser mayor a cero."));
        exit();
    }

    if ($depto <= 0) {
        header("Location: Empleado.php?err=" . urlencode("Seleccione un departamento."));
        exit();
    }

    $dup = $conexion->prepare("SELECT COUNT(*) c FROM empleado WHERE cedula = ? AND idEmpleado <> ?");
    $dup->bind_param("si", $cedula, $idEmpleado);
    $dup->execute();
    $c = (int)$dup->get_result()->fetch_assoc()['c'];

    if ($c > 0) {
        header("Location: Empleado.php?err=" . urlencode("La cédula ya existe en otro empleado."));
        exit();
    }

    $stmt = $conexion->prepare("
        UPDATE empleado SET
            nombre = ?,
            apellidos = ?,
            cedula = ?,
            salario_hora = ?,
            salario_mensual = ?,
            puesto = ?,
            Estado = ?,
            Departamentos_idDepartamentos = ?,
            Fecha_de_Ingreso = ?,
            Fecha_de_Modificacion = CURDATE(),
            usuario_modificacion = ?
        WHERE idEmpleado = ?
    ");

    $stmt->bind_param(
        "sssddssisii",
        $nombre,
        $apellidos,
        $cedula,
        $salarioHora,
        $salarioMensual,
        $puesto,
        $estado,
        $depto,
        $fechaIngreso,
        $usuarioSesion,
        $idEmpleado
    );

    if (!$stmt->execute()) {
        header("Location: Empleado.php?err=" . urlencode("Error al actualizar: " . $stmt->error));
        exit();
    }

    header("Location: Empleado.php?msg=" . urlencode("Empleado actualizado correctamente"));
    exit();
}

/* ==========================
   INACTIVAR
========================== */
if (isset($_GET['eliminar'])) {

    if (!$puedeMantener) {
        header("Location: Empleado.php?err=" . urlencode("No tiene permisos de mantenimiento."));
        exit();
    }

    $id = (int)getv('eliminar', 0);

    $stmt = $conexion->prepare("
        UPDATE empleado
        SET Estado = 'Inactivo',
            Fecha_de_Modificacion = CURDATE(),
            usuario_modificacion = ?
        WHERE idEmpleado = ?
    ");
    $stmt->bind_param("ii", $usuarioSesion, $id);

    if (!$stmt->execute()) {
        header("Location: Empleado.php?err=" . urlencode("Error al inactivar: " . $stmt->error));
        exit();
    }

    header("Location: Empleado.php?msg=" . urlencode("Empleado inactivado"));
    exit();
}

/* ==========================
   LISTADO (con buscador + paginación)
========================== */
$stTotal = $conexion->prepare("
    SELECT COUNT(*) total
    FROM empleado e
    WHERE e.Estado = 'Activo'
      AND (e.nombre LIKE ? OR e.apellidos LIKE ? OR e.cedula LIKE ?)
");
$stTotal->bind_param("sss", $like, $like, $like);
$stTotal->execute();
$total = (int)$stTotal->get_result()->fetch_assoc()['total'];
$totalPaginas = max(1, (int)ceil($total / $limite));

$stList = $conexion->prepare("
    SELECT e.*, d.Nombre_departamento, u.Email AS usuarioEmail
    FROM empleado e
    INNER JOIN departamentos d ON e.Departamentos_idDepartamentos = d.idDepartamentos
    LEFT JOIN usuario u ON u.idUsuario = e.Usuario_idUsuario
    WHERE e.Estado = 'Activo'
      AND (e.nombre LIKE ? OR e.apellidos LIKE ? OR e.cedula LIKE ?)
    ORDER BY e.idEmpleado ASC
    LIMIT ?, ?
");
$stList->bind_param("sssii", $like, $like, $like, $inicio, $limite);
$stList->execute();
$resultado = $stList->get_result();

$deptos = $conexion->query("
    SELECT idDepartamentos, Nombre_departamento
    FROM departamentos
    WHERE estado = '1'
    ORDER BY Nombre_departamento ASC
");

$usuariosDisponibles = $conexion->query("
    SELECT u.idUsuario, u.Nombre, u.Apellido, u.Email
    FROM usuario u
    LEFT JOIN empleado e ON e.Usuario_idUsuario = u.idUsuario
    WHERE e.Usuario_idUsuario IS NULL
      AND u.Estado = 1
    ORDER BY u.idUsuario ASC
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Módulo Empleados</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .acciones-btn .btn {
            min-height: auto !important;
            display: inline-block !important;
            padding: .375rem .75rem !important;
            border-radius: .375rem !important;
            text-decoration: none !important;
            font-weight: 500 !important;
            box-shadow: none !important;
        }

        .acciones-btn .btn-warning,
        .acciones-btn .btn-warning:hover,
        .acciones-btn .btn-warning:focus {
            background: #ffc107 !important;
            border-color: #ffc107 !important;
            color: #000 !important;
        }

        .acciones-btn .btn-danger,
        .acciones-btn .btn-danger:hover,
        .acciones-btn .btn-danger:focus {
            background: #dc3545 !important;
            border-color: #dc3545 !important;
            color: #fff !important;
        }

        .form-actions .btn {
            min-height: auto !important;
            display: inline-block !important;
            padding: .375rem .75rem !important;
            border-radius: .375rem !important;
            box-shadow: none !important;
        }

        .form-actions .btn-success,
        .form-actions .btn-success:hover,
        .form-actions .btn-success:focus {
            background: #198754 !important;
            border-color: #198754 !important;
            color: #fff !important;
        }

        .form-actions .btn-warning,
        .form-actions .btn-warning:hover,
        .form-actions .btn-warning:focus {
            background: #ffc107 !important;
            border-color: #ffc107 !important;
            color: #000 !important;
        }

        .form-actions .btn-secondary,
        .form-actions .btn-secondary:hover,
        .form-actions .btn-secondary:focus {
            background: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }

        .search-form .btn-light,
        .search-form .btn-light:hover,
        .search-form .btn-light:focus {
            background: #f8f9fa !important;
            border-color: #ced4da !important;
            color: #212529 !important;
            min-height: auto !important;
            display: inline-block !important;
            padding: .375rem .75rem !important;
            box-shadow: none !important;
        }
    </style>
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <div class="container-fluid mt-4">

        <?php if($msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if($err): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($err); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><?php echo $editar ? "Editar Empleado" : "Registro de Empleados"; ?></h4>
            </div>

            <div class="card-body">
                <form method="POST" class="row g-3">

                    <?php if($editar): ?>
                        <input type="hidden" name="idEmpleado" value="<?php echo (int)$datosEditar['idEmpleado']; ?>">
                    <?php endif; ?>

                    <div class="col-md-6">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control"
                               value="<?php echo $editar ? htmlspecialchars($datosEditar['nombre']) : ''; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Apellidos</label>
                        <input type="text" name="apellidos" class="form-control"
                               value="<?php echo $editar ? htmlspecialchars($datosEditar['apellidos']) : ''; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Cédula</label>
                        <input type="text" name="cedula" class="form-control"
                               value="<?php echo $editar ? htmlspecialchars($datosEditar['cedula']) : ''; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Fecha de Ingreso</label>
                        <input type="date" name="fecha_ingreso" class="form-control"
                               value="<?php echo $editar ? htmlspecialchars($datosEditar['Fecha_de_Ingreso']) : ''; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Salario por Hora</label>
                        <input type="number" step="0.01" min="0" name="salario_hora" id="salario_hora" class="form-control"
                               value="<?php echo $editar ? htmlspecialchars($datosEditar['salario_hora']) : ''; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Salario Mensual</label>
                        <input type="number" step="0.01" min="0" name="salario_mensual" id="salario_mensual" class="form-control"
                               value="<?php echo $editar ? htmlspecialchars($datosEditar['salario_mensual']) : ''; ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Puesto</label>
                        <input type="text" name="puesto" class="form-control"
                               value="<?php echo $editar ? htmlspecialchars($datosEditar['puesto']) : ''; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="Activo" <?php echo ($editar && $datosEditar['Estado'] === "Activo") ? "selected" : ""; ?>>Activo</option>
                            <option value="Inactivo" <?php echo ($editar && $datosEditar['Estado'] === "Inactivo") ? "selected" : ""; ?>>Inactivo</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Departamento</label>
                        <select name="departamento" class="form-select" required>
                            <option value="">Seleccione un departamento</option>
                            <?php while($d = $deptos->fetch_assoc()):
                                $sel = ($editar && (int)$datosEditar['Departamentos_idDepartamentos'] === (int)$d['idDepartamentos']) ? "selected" : "";
                            ?>
                                <option value="<?php echo (int)$d['idDepartamentos']; ?>" <?php echo $sel; ?>>
                                    <?php echo htmlspecialchars($d['Nombre_departamento']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <?php if($editar): ?>
                        <div class="col-md-6">
                            <label class="form-label">Usuario asociado</label>
                            <input type="text" class="form-control"
                                   value="<?php echo htmlspecialchars(
                                       ((int)($datosEditar['Usuario_idUsuario'] ?? 0)) . ' - ' .
                                       trim(($datosEditar['usuarioNombre'] ?? '') . ' ' . ($datosEditar['usuarioApellido'] ?? '')) .
                                       ' (' . ($datosEditar['usuarioEmail'] ?? '') . ')'
                                   ); ?>"
                                   readonly>
                        </div>
                    <?php else: ?>
                        <div class="col-md-6">
                            <label class="form-label">Usuario asociado</label>
                            <select name="Usuario_idUsuario" class="form-select" required>
                                <option value="">Seleccione un usuario</option>
                                <?php while($u = $usuariosDisponibles->fetch_assoc()): ?>
                                    <option value="<?php echo (int)$u['idUsuario']; ?>">
                                        <?php echo htmlspecialchars(
                                            $u['idUsuario'] . ' - ' . $u['Nombre'] . ' ' . $u['Apellido'] . ' (' . $u['Email'] . ')'
                                        ); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="col-12 form-actions">
                        <?php if($editar): ?>
                            <button type="submit" name="actualizar" class="btn btn-warning px-4" <?php echo !$puedeMantener ? "disabled" : ""; ?>>
                                Actualizar
                            </button>
                            <a href="Empleado.php" class="btn btn-secondary">Cancelar</a>
                        <?php else: ?>
                            <button type="submit" name="guardar" class="btn btn-success px-4" <?php echo !$puedeMantener ? "disabled" : ""; ?>>
                                Guardar
                            </button>
                        <?php endif; ?>

                        <?php if(!$puedeMantener): ?>
                            <div class="text-muted mt-2">No tienes permiso Mantenimiento_RH.</div>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Lista de Empleados Activos</h4>

                <form method="GET" class="d-flex gap-2 search-form">
                    <input type="text" name="buscar" class="form-control" style="width: 280px"
                           placeholder="Buscar (nombre, apellidos o cédula)" value="<?php echo htmlspecialchars($buscar); ?>">
                    <button class="btn btn-light" type="submit">Buscar</button>
                </form>
            </div>

            <div class="card-body table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>Cédula</th>
                        <th>Puesto</th>
                        <th>Salario</th>
                        <th>Estado</th>
                        <th>Departamento</th>
                        <th>Usuario</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while($fila = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int)$fila['idEmpleado']; ?></td>
                            <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($fila['apellidos']); ?></td>
                            <td><?php echo htmlspecialchars($fila['cedula']); ?></td>
                            <td><?php echo htmlspecialchars($fila['puesto']); ?></td>
                            <td>₡<?php echo number_format((float)$fila['salario_mensual'], 0, ',', '.'); ?></td>
                            <td><span class="badge bg-success"><?php echo htmlspecialchars($fila['Estado']); ?></span></td>
                            <td><?php echo htmlspecialchars($fila['Nombre_departamento']); ?></td>
                            <td><?php echo htmlspecialchars($fila['usuarioEmail'] ?? ''); ?></td>
                            <td class="acciones-btn d-flex gap-2">
                                <a href="?editar=<?php echo (int)$fila['idEmpleado']; ?>" class="btn btn-warning btn-sm">
                                    Editar
                                </a>
                                <a href="?eliminar=<?php echo (int)$fila['idEmpleado']; ?>"
                                   class="btn btn-danger btn-sm <?php echo !$puedeMantener ? "disabled" : ""; ?>"
                                   onclick="return confirm('¿Está seguro de inactivar este empleado?');">
                                    Inactivar
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

                <nav>
                    <ul class="pagination">
                        <?php
                        $base = "Empleado.php?buscar=" . urlencode($buscar) . "&pagina=";
                        for ($p = 1; $p <= $totalPaginas; $p++):
                            $active = ($p === $pagina) ? "active" : "";
                        ?>
                            <li class="page-item <?php echo $active; ?>">
                                <a class="page-link" href="<?php echo $base . $p; ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const salarioHora = document.getElementById('salario_hora');
    const salarioMensual = document.getElementById('salario_mensual');

    function recalcularSalarioMensual() {
        const hora = parseFloat(salarioHora.value) || 0;
        const mensual = hora * 8 * 30;
        salarioMensual.value = mensual.toFixed(2);
    }

    if (salarioHora && salarioMensual) {
        salarioHora.addEventListener('input', recalcularSalarioMensual);
        recalcularSalarioMensual();
    }
})();
</script>
</body>
</html>