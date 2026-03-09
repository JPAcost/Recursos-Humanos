<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$editar = false;
$datosEditar = [];

/* INSERTAR */
if (isset($_POST['guardar'])) {

    $stmt = $conexion->prepare("
        INSERT INTO departamentos
        (
            Nombre_departamento, Descripcion, Fecha_creacion,
            Usuario_creacion, fecha_modificacion,
            Usuario_modificacion, estado
        )
        VALUES (?, ?, NOW(), ?, NOW(), ?, 1)
    ");

    $stmt->bind_param(
        "ssii",
        $_POST['nombre'],
        $_POST['descripcion'],
        $_SESSION['idUsuario'],
        $_SESSION['idUsuario']
    );

    $stmt->execute();
    header("Location: Departamentos.php");
    exit();
}

/* EDITAR */
if (isset($_GET['editar'])) {
    $editar = true;

    $idEditar = (int)$_GET['editar'];

    $stmt = $conexion->prepare("
        SELECT * 
        FROM departamentos 
        WHERE idDepartamentos = ?
    ");

    $stmt->bind_param("i", $idEditar);
    $stmt->execute();
    $datosEditar = $stmt->get_result()->fetch_assoc();

    if (!$datosEditar) {
        header("Location: Departamentos.php");
        exit();
    }
}

/* ACTUALIZAR */
if (isset($_POST['actualizar'])) {

    $stmt = $conexion->prepare("
        UPDATE departamentos SET
            Nombre_departamento = ?,
            Descripcion = ?,
            fecha_modificacion = NOW(),
            Usuario_modificacion = ?
        WHERE idDepartamentos = ?
    ");

    $stmt->bind_param(
        "ssii",
        $_POST['nombre'],
        $_POST['descripcion'],
        $_SESSION['idUsuario'],
        $_POST['id']
    );

    $stmt->execute();
    header("Location: Departamentos.php");
    exit();
}

/* INACTIVAR */
if (isset($_GET['eliminar'])) {

    $idEliminar = (int)$_GET['eliminar'];

    $stmt = $conexion->prepare("
        UPDATE departamentos SET
            estado = 0,
            fecha_modificacion = NOW(),
            Usuario_modificacion = ?
        WHERE idDepartamentos = ?
    ");

    $stmt->bind_param("ii", $_SESSION['idUsuario'], $idEliminar);
    $stmt->execute();

    header("Location: Departamentos.php");
    exit();
}

/* LISTAR */
$resultado = $conexion->query("
    SELECT * 
    FROM departamentos 
    WHERE estado = 1
    ORDER BY idDepartamentos ASC
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Módulo Departamentos</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
    <div class="container-fluid mt-4">

        <div class="card shadow p-4">

            <h3 class="mb-4">
                <?php echo $editar ? "Editar Departamento" : "Crear Nuevo Departamento"; ?>
            </h3>

            <form method="POST" class="row g-3">

                <?php if($editar): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($datosEditar['idDepartamentos']); ?>">
                <?php endif; ?>

                <div class="col-md-6">
                    <label class="form-label">Nombre del Departamento</label>
                    <input
                        type="text"
                        name="nombre"
                        class="form-control"
                        value="<?php echo $editar ? htmlspecialchars($datosEditar['Nombre_departamento']) : ''; ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">Descripción</label>
                    <input
                        type="text"
                        name="descripcion"
                        class="form-control"
                        value="<?php echo $editar ? htmlspecialchars($datosEditar['Descripcion']) : ''; ?>"
                        required
                    >
                </div>

                <div class="col-12 mt-3">
                    <?php if($editar): ?>
                        <button type="submit" name="actualizar" class="btn btn-warning">Actualizar</button>
                        <a href="Departamentos.php" class="btn btn-secondary">Cancelar</a>
                    <?php else: ?>
                        <button type="submit" name="guardar" class="btn btn-success">Guardar</button>
                    <?php endif; ?>
                </div>

            </form>

            <hr class="my-4">

            <h4>Lista de Departamentos</h4>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th width="200">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if($resultado && $resultado->num_rows > 0): ?>
                            <?php while($row = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['idDepartamentos']); ?></td>
                                    <td><?php echo htmlspecialchars($row['Nombre_departamento']); ?></td>
                                    <td><?php echo htmlspecialchars($row['Descripcion']); ?></td>
                                    <td>
                                        <?php if ((int)$row['estado'] === 1): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?editar=<?php echo (int)$row['idDepartamentos']; ?>" class="btn btn-warning btn-sm me-2">
                                            Editar
                                        </a>
                                        <a href="?eliminar=<?php echo (int)$row['idDepartamentos']; ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('¿Está seguro de inactivar este departamento?');">
                                            Inactivar
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay departamentos registrados</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>