<?php
session_start();
require_once("../config/conexion.php");
require_once("../includes/permisos.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

/* ============================
   VALIDACIÓN DE PERMISO (ADMIN)
============================ */
if (!isset($_SESSION['idRoles']) || (int)$_SESSION['idRoles'] !== 2) {
    echo "<div class='alert alert-danger m-4'>
            No tiene permisos para acceder al módulo de usuarios.
          </div>";
    exit();
}

$usuarioSesion   = (int)($_SESSION['idUsuario'] ?? 0);
$puedeMantener   = isset($_SESSION['Mantenimiento_RH']) && (int)$_SESSION['Mantenimiento_RH'] === 1;
$editar          = false;
$datosEditar     = [];

/* ============================
   BUSCADOR
============================ */
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

/* ============================
   PAGINACIÓN
============================ */
$limite = 5;
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$inicio = ($pagina - 1) * $limite;

/* ============================
   INACTIVAR
============================ */
if (isset($_GET['eliminar']) && $puedeMantener) {

    $id = (int)$_GET['eliminar'];

    $stmt = $conexion->prepare("
        UPDATE usuario
        SET Estado = b'0',
            Fecha_modificacion = CURDATE()
        WHERE idUsuario = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: usuarios.php?msg=" . urlencode("Usuario inactivado correctamente"));
    exit();
}

/* ============================
   EDITAR
============================ */
if (isset($_GET['editar'])) {
    $editar = true;
    $idEditar = (int)$_GET['editar'];

    $stmtEditar = $conexion->prepare("
        SELECT u.*, e.idEmpleado, e.nombre AS nombreEmpleado, e.apellidos AS apellidosEmpleado
        FROM usuario u
        LEFT JOIN empleado e ON e.Usuario_idUsuario = u.idUsuario
        WHERE u.idUsuario = ?
        LIMIT 1
    ");
    $stmtEditar->bind_param("i", $idEditar);
    $stmtEditar->execute();
    $datosEditar = $stmtEditar->get_result()->fetch_assoc();

    if (!$datosEditar) {
        header("Location: usuarios.php?msg=" . urlencode("Usuario no encontrado"));
        exit();
    }
}

/* ============================
   ACTUALIZAR
============================ */
if (isset($_POST['actualizar']) && $puedeMantener) {

    $idUsuario = (int)($_POST['idUsuario'] ?? 0);
    $email     = trim($_POST['Email'] ?? '');
    $idRoles   = (int)($_POST['idRoles'] ?? 0);
    $estado    = (int)($_POST['Estado'] ?? 1);
    $passPlano = trim($_POST['Password'] ?? '');

    if ($idUsuario <= 0 || $idRoles <= 0) {
        header("Location: usuarios.php?msg=" . urlencode("Datos inválidos"));
        exit();
    }

    if ($email === '') {
        header("Location: usuarios.php?msg=" . urlencode("El correo es obligatorio"));
        exit();
    }

    // Evitar correo duplicado en otro usuario
    $ver = $conexion->prepare("SELECT 1 FROM usuario WHERE Email = ? AND idUsuario <> ? LIMIT 1");
    $ver->bind_param("si", $email, $idUsuario);
    $ver->execute();

    if ($ver->get_result()->num_rows > 0) {
        header("Location: usuarios.php?msg=" . urlencode("El correo ya está registrado por otro usuario"));
        exit();
    }

    if ($passPlano !== '') {
        $passwordHash = password_hash($passPlano, PASSWORD_BCRYPT);

        $stmt = $conexion->prepare("
            UPDATE usuario SET
                Email = ?,
                Password = ?,
                Fecha_modificacion = CURDATE(),
                Estado = ?,
                idRoles = ?
            WHERE idUsuario = ?
        ");

        $stmt->bind_param(
            "ssiii",
            $email,
            $passwordHash,
            $estado,
            $idRoles,
            $idUsuario
        );
    } else {
        $stmt = $conexion->prepare("
            UPDATE usuario SET
                Email = ?,
                Fecha_modificacion = CURDATE(),
                Estado = ?,
                idRoles = ?
            WHERE idUsuario = ?
        ");

        $stmt->bind_param(
            "siii",
            $email,
            $estado,
            $idRoles,
            $idUsuario
        );
    }

    $stmt->execute();

    header("Location: usuarios.php?msg=" . urlencode("Usuario actualizado correctamente"));
    exit();
}

/* ============================
   LISTA + BUSCADOR + PAGINACIÓN
============================ */
$sql = "
SELECT 
    u.*,
    r.NombreRol AS Rol,
    e.idEmpleado,
    e.nombre AS nombreEmpleado,
    e.apellidos AS apellidosEmpleado
FROM usuario u
INNER JOIN roles r ON u.idRoles = r.idRoles
LEFT JOIN empleado e ON e.Usuario_idUsuario = u.idUsuario
WHERE u.Estado = b'1'
  AND (
        u.Nombre LIKE ?
        OR u.Apellido LIKE ?
        OR u.Email LIKE ?
        OR e.nombre LIKE ?
        OR e.apellidos LIKE ?
      )
ORDER BY u.idUsuario ASC
LIMIT ?, ?
";

$stmt = $conexion->prepare($sql);
$like = "%$buscar%";
$stmt->bind_param("sssssii", $like, $like, $like, $like, $like, $inicio, $limite);
$stmt->execute();
$resultado = $stmt->get_result();

/* TOTAL */
$totalConsulta = $conexion->prepare("
SELECT COUNT(*) AS total
FROM usuario u
LEFT JOIN empleado e ON e.Usuario_idUsuario = u.idUsuario
WHERE u.Estado = b'1'
  AND (
        u.Nombre LIKE ?
        OR u.Apellido LIKE ?
        OR u.Email LIKE ?
        OR e.nombre LIKE ?
        OR e.apellidos LIKE ?
      )
");
$totalConsulta->bind_param("sssss", $like, $like, $like, $like, $like);
$totalConsulta->execute();
$total = (int)($totalConsulta->get_result()->fetch_assoc()['total'] ?? 0);
$totalPaginas = max(1, (int)ceil($total / $limite));

/* ROLES */
$roles = $conexion->query("
    SELECT idRoles, NombreRol
    FROM roles
    WHERE Estado = b'1'
    ORDER BY idRoles ASC
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Módulo Usuarios</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <div class="container-fluid mt-4">

        <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Edición de Usuarios</h4>
            </div>

            <div class="card-body">
                <?php if(!$puedeMantener): ?>
                    <div class="alert alert-warning">
                        Usted puede ver usuarios, pero no tiene permisos para editar o inactivar.
                    </div>
                <?php endif; ?>

                <?php if($editar): ?>
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="idUsuario" value="<?php echo (int)$datosEditar['idUsuario']; ?>">

                        <div class="col-md-6">
                            <label class="form-label">Empleado asociado</label>
                            <input type="text" class="form-control"
                                   value="<?php echo htmlspecialchars(trim(($datosEditar['nombreEmpleado'] ?? '') . ' ' . ($datosEditar['apellidosEmpleado'] ?? ''))); ?>"
                                   readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="Email" class="form-control"
                                   value="<?php echo htmlspecialchars($datosEditar['Email']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Password (dejar vacío para no cambiar)</label>
                            <input type="password" name="Password" class="form-control" autocomplete="new-password">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Rol</label>
                            <select name="idRoles" class="form-select" required>
                                <option value="">-- Seleccione un rol --</option>
                                <?php while($r = $roles->fetch_assoc()):
                                    $selected = ((int)$datosEditar['idRoles'] === (int)$r['idRoles']) ? "selected" : "";
                                ?>
                                    <option value="<?php echo (int)$r['idRoles']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($r['NombreRol']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <?php $estadoActual = (int)$datosEditar['Estado']; ?>
                            <select name="Estado" class="form-select">
                                <option value="1" <?php echo ($estadoActual === 1) ? "selected" : ""; ?>>Activo</option>
                                <option value="0" <?php echo ($estadoActual === 0) ? "selected" : ""; ?>>Inactivo</option>
                            </select>
                        </div>

                        <div class="col-12 form-actions">
                            <button type="submit" name="actualizar" class="btn btn-warning px-4" <?php echo !$puedeMantener ? "disabled" : ""; ?>>
                                Actualizar
                            </button>
                            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        Seleccione un usuario de la tabla para editarlo.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Lista de Usuarios Activos</h4>

                <form method="GET" class="d-flex gap-2 search-form">
                    <input type="text" name="buscar" class="form-control" style="width: 280px"
                           placeholder="Buscar usuario, empleado o correo"
                           value="<?php echo htmlspecialchars($buscar); ?>">
                    <button class="btn btn-light" type="submit">Buscar</button>
                </form>
            </div>

            <div class="card-body table-responsive">
                <table class="table table-striped table-hover align-middle" id="tablaUsuarios">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Empleado</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Creación</th>
                            <th>Modificación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($fila = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int)$fila['idUsuario']; ?></td>
                            <td>
                                <?php
                                $nombreMostrar = trim(($fila['nombreEmpleado'] ?? '') . ' ' . ($fila['apellidosEmpleado'] ?? ''));
                                echo htmlspecialchars($nombreMostrar !== '' ? $nombreMostrar : ($fila['Nombre'] . ' ' . $fila['Apellido']));
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($fila['Email']); ?></td>
                            <td><?php echo htmlspecialchars($fila['Rol']); ?></td>
                            <td><?php echo htmlspecialchars($fila['Fecha_de_creacion']); ?></td>
                            <td><?php echo htmlspecialchars($fila['Fecha_modificacion']); ?></td>
                            <td class="acciones-btn d-flex gap-2">
                                <a href="?editar=<?php echo (int)$fila['idUsuario']; ?>&buscar=<?php echo urlencode($buscar); ?>&pagina=<?php echo $pagina; ?>"
                                   class="btn btn-warning btn-sm">
                                    Editar
                                </a>
                                <a href="?eliminar=<?php echo (int)$fila['idUsuario']; ?>"
                                   class="btn btn-danger btn-sm <?php echo !$puedeMantener ? "disabled" : ""; ?>"
                                   onclick="return confirm('¿Está seguro de inactivar este usuario?');">
                                   Inactivar
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for($i = 1; $i <= $totalPaginas; $i++):
                            $active = ($i === $pagina) ? "active" : "";
                            $url = "?pagina=$i&buscar=" . urlencode($buscar);
                        ?>
                            <li class="page-item <?php echo $active; ?>">
                                <a class="page-link" href="<?php echo $url; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>