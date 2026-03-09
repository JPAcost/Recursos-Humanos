<?php
include("../config/conexion.php");

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}
$usuario = (int)$_SESSION['idUsuario'];

/* ============================
   Helpers
============================ */
function post($k, $default = null) { return $_POST[$k] ?? $default; }
function get($k, $default = null) { return $_GET[$k] ?? $default; }

function diasEntre($inicio, $fin){
    $d = (int)((strtotime($fin) - strtotime($inicio)) / 86400) + 1;
    return max(0, $d);
}

/* ============================
   Mensajes
============================ */
$mensaje = $_SESSION['mensaje'] ?? null;
$tipoMsg = $_SESSION['tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo']);

/* ============================
   Cargar combos
============================ */
$emps = $conexion->query("
    SELECT idEmpleado, nombre, apellidos, salario_hora, salario_mensual
    FROM empleado
    WHERE Estado = 'Activo'
    ORDER BY nombre ASC
");

$tipos = $conexion->query("
    SELECT idTipo_Incapacidad, concepto, porcentaje_salarial
    FROM tipo_incapacidad
    WHERE estado = 1
    ORDER BY concepto ASC
");

/* ============================
   Acciones CRUD
============================ */

// ELIMINAR LÓGICO
if (get('accion') === 'eliminar' && (int)get('id') > 0) {
    $id = (int)get('id');

    $stmt = $conexion->prepare("
        UPDATE incapacidad
        SET Estado = 'Inactivo'
        WHERE idIncapacidad = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Incapacidad desactivada correctamente.";
        $_SESSION['tipo'] = "success";
    } else {
        $_SESSION['mensaje'] = "Error desactivando: " . $stmt->error;
        $_SESSION['tipo'] = "danger";
    }
    header("Location: incapacidad.php");
    exit();
}

// ACTIVAR
if (get('accion') === 'activar' && (int)get('id') > 0) {
    $id = (int)get('id');

    $stmt = $conexion->prepare("
        UPDATE incapacidad
        SET Estado = 'Activo'
        WHERE idIncapacidad = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Incapacidad activada correctamente.";
        $_SESSION['tipo'] = "success";
    } else {
        $_SESSION['mensaje'] = "Error activando: " . $stmt->error;
        $_SESSION['tipo'] = "danger";
    }
    header("Location: incapacidad.php");
    exit();
}

// GUARDAR (crear / editar)
$editar = false;
$datos = [
    'idIncapacidad' => 0,
    'Empleado_idEmpleado' => '',
    'Tipo_Incapacidad_idTipo_Incapacidad' => '',
    'fecha_inicio' => '',
    'fecha_fin' => '',
    'salario_diario' => '',
    'Estado' => 'Activo'
];

// Cargar para editar
if (get('accion') === 'editar' && (int)get('id') > 0) {
    $editar = true;
    $id = (int)get('id');

    $stmt = $conexion->prepare("
        SELECT 
            idIncapacidad,
            Empleado_idEmpleado,
            Tipo_Incapacidad_idTipo_Incapacidad,
            fecha_inicio,
            fecha_fin,
            dias,
            salario,
            Estado
        FROM incapacidad
        WHERE idIncapacidad = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $datos['idIncapacidad'] = (int)$row['idIncapacidad'];
        $datos['Empleado_idEmpleado'] = (int)$row['Empleado_idEmpleado'];
        $datos['Tipo_Incapacidad_idTipo_Incapacidad'] = (int)$row['Tipo_Incapacidad_idTipo_Incapacidad'];
        $datos['fecha_inicio'] = $row['fecha_inicio'];
        $datos['fecha_fin'] = $row['fecha_fin'];
        $datos['salario_diario'] = '';
        $datos['Estado'] = $row['Estado'] ?? 'Activo';
    } else {
        $_SESSION['mensaje'] = "Registro no encontrado.";
        $_SESSION['tipo'] = "danger";
        header("Location: incapacidad.php");
        exit();
    }
}

$resultadoCalculo = null;

if (isset($_POST['guardar'])) {
    $idIncapacidad = (int)post('idIncapacidad', 0);
    $emp = (int)post('empleado', 0);
    $tipoI = (int)post('tipo_incapacidad', 0);
    $inicio = trim((string)post('inicio', ''));
    $fin = trim((string)post('fin', ''));
    $salarioDiario = 0;
    $estado = post('estado', 'Activo');
    if ($estado !== 'Activo' && $estado !== 'Inactivo') $estado = 'Activo';

    if ($emp <= 0 || $tipoI <= 0 || !$inicio || !$fin) {
        $mensaje = "Complete todos los campos. (Empleado, Tipo, Fechas)";
        $tipoMsg = "danger";
    } elseif (strtotime($fin) < strtotime($inicio)) {
        $mensaje = "La fecha fin no puede ser menor que la fecha inicio.";
        $tipoMsg = "danger";
    } else {
        // Obtener salario diario automático desde empleado
        $stmtEmp = $conexion->prepare("
            SELECT salario_hora, salario_mensual
            FROM empleado
            WHERE idEmpleado = ? AND Estado = 'Activo'
            LIMIT 1
        ");
        $stmtEmp->bind_param("i", $emp);
        $stmtEmp->execute();
        $empRow = $stmtEmp->get_result()->fetch_assoc();

        if (!$empRow) {
            $mensaje = "Empleado inválido o inactivo.";
            $tipoMsg = "danger";
        } else {
            $salarioHoraEmp = (float)($empRow['salario_hora'] ?? 0);
            $salarioMensualEmp = (float)($empRow['salario_mensual'] ?? 0);
            $salarioDiario = $salarioMensualEmp > 0 ? ($salarioMensualEmp / 30) : ($salarioHoraEmp * 8);

            if ($salarioDiario <= 0) {
                $mensaje = "El empleado seleccionado no tiene un salario válido.";
                $tipoMsg = "danger";
            } else {
                // Traer porcentaje del tipo
                $stmtTipo = $conexion->prepare("
                    SELECT concepto, porcentaje_salarial
                    FROM tipo_incapacidad
                    WHERE idTipo_Incapacidad = ? AND estado = 1
                    LIMIT 1
                ");
                $stmtTipo->bind_param("i", $tipoI);
                $stmtTipo->execute();
                $tipoRow = $stmtTipo->get_result()->fetch_assoc();

                if (!$tipoRow) {
                    $mensaje = "Tipo de incapacidad inválido o inactivo.";
                    $tipoMsg = "danger";
                } else {
                    $dias = diasEntre($inicio, $fin);
                    $porcentaje = (float)$tipoRow['porcentaje_salarial'];
                    $concepto = $tipoRow['concepto'];

                    $total = ($salarioDiario * $dias) * ($porcentaje / 100);

                    $resultadoCalculo = [
                        'dias' => $dias,
                        'porcentaje' => $porcentaje,
                        'concepto' => $concepto,
                        'total' => $total
                    ];

                    if ($idIncapacidad > 0) {
                        $stmtU = $conexion->prepare("
                            UPDATE incapacidad
                            SET fecha_inicio = ?,
                                fecha_fin = ?,
                                dias = ?,
                                salario = ?,
                                Empleado_idEmpleado = ?,
                                Tipo_Incapacidad_idTipo_Incapacidad = ?,
                                fecha_modificacion = NOW(),
                                Usuario_modificacion = ?,
                                Estado = ?
                            WHERE idIncapacidad = ?
                            LIMIT 1
                        ");
                        $stmtU->bind_param(
                            "ssidiissi",
                            $inicio,
                            $fin,
                            $dias,
                            $total,
                            $emp,
                            $tipoI,
                            $usuario,
                            $estado,
                            $idIncapacidad
                        );

                        if ($stmtU->execute()) {
                            $_SESSION['mensaje'] = "Incapacidad actualizada. Total: ₡" . number_format($total, 0, ',', '.');
                            $_SESSION['tipo'] = "success";
                            header("Location: incapacidad.php");
                            exit();
                        } else {
                            $mensaje = "Error actualizando: " . $stmtU->error;
                            $tipoMsg = "danger";
                        }
                    } else {
                        $stmtI = $conexion->prepare("
                            INSERT INTO incapacidad
                            (fecha_inicio, fecha_fin, dias, salario, Empleado_idEmpleado, Tipo_Incapacidad_idTipo_Incapacidad,
                             fecha_creacion, Usuario_creacion, Estado)
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
                        ");
                        $stmtI->bind_param("ssidiiis", $inicio, $fin, $dias, $total, $emp, $tipoI, $usuario, $estado);

                        if ($stmtI->execute()) {
                            $_SESSION['mensaje'] = "Incapacidad creada. Total: ₡" . number_format($total, 0, ',', '.');
                            $_SESSION['tipo'] = "success";
                            header("Location: incapacidad.php");
                            exit();
                        } else {
                            $mensaje = "Error guardando: " . $stmtI->error;
                            $tipoMsg = "danger";
                        }
                    }
                }
            }
        }
    }

    $datos['idIncapacidad'] = $idIncapacidad;
    $datos['Empleado_idEmpleado'] = $emp;
    $datos['Tipo_Incapacidad_idTipo_Incapacidad'] = $tipoI;
    $datos['fecha_inicio'] = $inicio;
    $datos['fecha_fin'] = $fin;
    $datos['salario_diario'] = $salarioDiario > 0 ? number_format($salarioDiario, 2, '.', '') : '';
    $datos['Estado'] = $estado;
    $editar = ($idIncapacidad > 0);
}

/* ============================
   Listado con búsqueda/paginación
============================ */
$buscar = trim((string)get('buscar', ''));
$ver = get('ver', 'activos');
$pagina = max(1, (int)get('pagina', 1));
$limite = 8;
$offset = ($pagina - 1) * $limite;

$where = [];
$params = [];
$types = "";

if ($ver === 'activos')  { $where[] = "i.Estado = 'Activo'"; }
if ($ver === 'inactivos'){ $where[] = "i.Estado = 'Inactivo'"; }

if ($buscar !== '') {
    $where[] = "(e.nombre LIKE ? OR e.apellidos LIKE ? OR t.concepto LIKE ?)";
    $like = "%$buscar%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

$whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

$sqlCount = "
  SELECT COUNT(*) AS total
  FROM incapacidad i
  INNER JOIN empleado e ON i.Empleado_idEmpleado = e.idEmpleado
  INNER JOIN tipo_incapacidad t ON i.Tipo_Incapacidad_idTipo_Incapacidad = t.idTipo_Incapacidad
  $whereSql
";
$stmtC = $conexion->prepare($sqlCount);
if ($types !== "") $stmtC->bind_param($types, ...$params);
$stmtC->execute();
$totalReg = (int)($stmtC->get_result()->fetch_assoc()['total'] ?? 0);
$totalPag = max(1, (int)ceil($totalReg / $limite));

$sqlList = "
  SELECT
    i.idIncapacidad,
    i.fecha_inicio,
    i.fecha_fin,
    i.dias,
    i.salario,
    i.Estado,
    e.nombre,
    e.apellidos,
    t.concepto,
    t.porcentaje_salarial
  FROM incapacidad i
  INNER JOIN empleado e ON i.Empleado_idEmpleado = e.idEmpleado
  INNER JOIN tipo_incapacidad t ON i.Tipo_Incapacidad_idTipo_Incapacidad = t.idTipo_Incapacidad
  $whereSql
  ORDER BY i.fecha_inicio DESC, i.idIncapacidad DESC
  LIMIT $limite OFFSET $offset
";
$stmtL = $conexion->prepare($sqlList);
if ($types !== "") $stmtL->bind_param($types, ...$params);
$stmtL->execute();
$listado = $stmtL->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Incapacidades - Sistema RRHH</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/dashboard.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="main-content">
  <div class="container-fluid mt-4">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h3 class="mb-0">CRUD Incapacidades</h3>
        <small class="text-muted">Cálculo: (Salario diario × Días) × (Porcentaje del tipo)</small>
      </div>
    </div>

    <?php if ($mensaje): ?>
      <div class="alert alert-<?= htmlspecialchars($tipoMsg) ?> alert-dismissible fade show">
        <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card shadow p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0"><?= $editar ? "Editar incapacidad" : "Nueva incapacidad" ?></h5>
            <span class="badge badge-soft"><?= $editar ? "Edición" : "Registro" ?></span>
          </div>

          <?php if ($resultadoCalculo): ?>
            <div class="alert alert-info">
              <div><strong>Concepto:</strong> <?= htmlspecialchars($resultadoCalculo['concepto']) ?></div>
              <div><strong>Días:</strong> <?= (int)$resultadoCalculo['dias'] ?></div>
              <div><strong>%:</strong> <?= number_format((float)$resultadoCalculo['porcentaje'], 2) ?>%</div>
              <div><strong>Total:</strong> ₡<?= number_format((float)$resultadoCalculo['total'], 0, ',', '.') ?></div>
            </div>
          <?php endif; ?>

          <form method="POST" class="row g-3">
            <input type="hidden" name="idIncapacidad" value="<?= (int)$datos['idIncapacidad'] ?>">

            <div class="col-12">
              <label class="form-label">Empleado</label>
              <select name="empleado" id="empleado" class="form-select" required>
                <option value="">Seleccione...</option>
                <?php
                  $emps2 = $conexion->query("
                      SELECT idEmpleado, nombre, apellidos, salario_hora, salario_mensual
                      FROM empleado
                      WHERE Estado = 'Activo'
                      ORDER BY nombre ASC
                  ");
                  while($e = $emps2->fetch_assoc()):
                    $sel = ((int)$datos['Empleado_idEmpleado'] === (int)$e['idEmpleado']) ? "selected" : "";
                    $salarioHoraEmp = (float)($e['salario_hora'] ?? 0);
                    $salarioMensualEmp = (float)($e['salario_mensual'] ?? 0);
                    $salarioDiarioEmp = $salarioMensualEmp > 0 ? ($salarioMensualEmp / 30) : ($salarioHoraEmp * 8);
                ?>
                  <option value="<?= (int)$e['idEmpleado'] ?>"
                          data-salario-diario="<?= htmlspecialchars(number_format($salarioDiarioEmp, 2, '.', '')) ?>"
                          <?= $sel ?>>
                    <?= htmlspecialchars($e['nombre'] . " " . $e['apellidos']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Tipo de incapacidad</label>
              <select name="tipo_incapacidad" class="form-select" required>
                <option value="">Seleccione...</option>
                <?php
                  $tipos2 = $conexion->query("
                    SELECT idTipo_Incapacidad, concepto, porcentaje_salarial
                    FROM tipo_incapacidad
                    WHERE estado = 1
                    ORDER BY concepto ASC
                  ");
                  while($t = $tipos2->fetch_assoc()):
                    $sel = ((int)$datos['Tipo_Incapacidad_idTipo_Incapacidad'] === (int)$t['idTipo_Incapacidad']) ? "selected" : "";
                ?>
                  <option value="<?= (int)$t['idTipo_Incapacidad'] ?>" <?= $sel ?>>
                    <?= htmlspecialchars($t['concepto']) ?> (<?= number_format((float)$t['porcentaje_salarial'], 2) ?>%)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Fecha inicio</label>
              <input type="date" name="inicio" class="form-control" value="<?= htmlspecialchars($datos['fecha_inicio']) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Fecha fin</label>
              <input type="date" name="fin" class="form-control" value="<?= htmlspecialchars($datos['fecha_fin']) ?>" required>
            </div>

            <div class="col-12">
              <label class="form-label">Salario diario (₡)</label>
              <input type="number" name="salario" id="salario" step="0.01" min="0"
                     class="form-control"
                     value="<?= htmlspecialchars((string)$datos['salario_diario']) ?>"
                     placeholder="Automático" required readonly>
              <div class="form-text">
                Se calcula automáticamente según el empleado seleccionado.
              </div>
            </div>

            <div class="col-12">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-select">
                <option value="Activo" <?= ($datos['Estado'] === 'Activo') ? 'selected' : '' ?>>Activo</option>
                <option value="Inactivo" <?= ($datos['Estado'] === 'Inactivo') ? 'selected' : '' ?>>Inactivo</option>
              </select>
            </div>

            <div class="col-12 form-actions">
              <button class="btn btn-primary btn-lg" name="guardar" type="submit">
                <?= $editar ? "Guardar cambios" : "Crear incapacidad" ?>
              </button>
            </div>

            <?php if ($editar): ?>
              <div class="col-12 form-actions">
                <a class="btn btn-secondary" href="incapacidad.php">Cancelar edición</a>
              </div>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card shadow p-3">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Listado</h5>

            <form class="d-flex gap-2 search-form" method="GET">
              <input class="form-control" name="buscar" value="<?= htmlspecialchars($buscar) ?>" placeholder="Buscar empleado / concepto">
              <select class="form-select" name="ver" style="max-width:160px;">
                <option value="activos" <?= ($ver==='activos')?'selected':'' ?>>Activos</option>
                <option value="inactivos" <?= ($ver==='inactivos')?'selected':'' ?>>Inactivos</option>
                <option value="todos" <?= ($ver==='todos')?'selected':'' ?>>Todos</option>
              </select>
              <button class="btn btn-light" type="submit">Buscar</button>
            </form>
          </div>

          <div class="table-responsive mt-3">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Empleado</th>
                  <th>Tipo</th>
                  <th>Fechas</th>
                  <th>Días</th>
                  <th>Total</th>
                  <th>Estado</th>
                  <th style="width: 170px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($listado && $listado->num_rows > 0): ?>
                  <?php while($r = $listado->fetch_assoc()): ?>
                    <tr>
                      <td><?= htmlspecialchars($r['nombre'] . " " . $r['apellidos']) ?></td>
                      <td>
                        <div class="fw-semibold"><?= htmlspecialchars($r['concepto']) ?></div>
                        <div class="text-muted small"><?= number_format((float)$r['porcentaje_salarial'],2) ?>%</div>
                      </td>
                      <td class="small">
                        <div><strong>Ini:</strong> <?= htmlspecialchars($r['fecha_inicio']) ?></div>
                        <div><strong>Fin:</strong> <?= htmlspecialchars($r['fecha_fin']) ?></div>
                      </td>
                      <td><?= (int)$r['dias'] ?></td>
                      <td><strong>₡<?= number_format((float)$r['salario'], 0, ',', '.') ?></strong></td>
                      <td>
                        <?php if (($r['Estado'] ?? '') === 'Activo'): ?>
                          <span class="badge text-bg-success">Activo</span>
                        <?php else: ?>
                          <span class="badge text-bg-secondary">Inactivo</span>
                        <?php endif; ?>
                      </td>
                      <td class="acciones-btn d-flex gap-2">
                        <a class="btn btn-warning btn-sm"
                           href="incapacidad.php?accion=editar&id=<?= (int)$r['idIncapacidad'] ?>">
                           Editar
                        </a>

                        <?php if (($r['Estado'] ?? '') === 'Activo'): ?>
                          <a class="btn btn-danger btn-sm"
                             onclick="return confirm('¿Desactivar esta incapacidad?');"
                             href="incapacidad.php?accion=eliminar&id=<?= (int)$r['idIncapacidad'] ?>">
                             Desactivar
                          </a>
                        <?php else: ?>
                          <a class="btn btn-success btn-sm"
                             onclick="return confirm('¿Activar esta incapacidad?');"
                             href="incapacidad.php?accion=activar&id=<?= (int)$r['idIncapacidad'] ?>">
                             Activar
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="7" class="text-muted">No hay registros.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <nav class="mt-2">
            <ul class="pagination mb-0">
              <?php
                $q = http_build_query(['buscar'=>$buscar,'ver'=>$ver]);
                $prev = max(1, $pagina-1);
                $next = min($totalPag, $pagina+1);
              ?>
              <li class="page-item <?= ($pagina<=1)?'disabled':'' ?>">
                <a class="page-link" href="incapacidad.php?<?= $q ?>&pagina=<?= $prev ?>">«</a>
              </li>

              <?php
                $start = max(1, $pagina-2);
                $end = min($totalPag, $pagina+2);
                for($p=$start; $p<=$end; $p++):
              ?>
                <li class="page-item <?= ($p===$pagina)?'active':'' ?>">
                  <a class="page-link" href="incapacidad.php?<?= $q ?>&pagina=<?= $p ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>

              <li class="page-item <?= ($pagina>=$totalPag)?'disabled':'' ?>">
                <a class="page-link" href="incapacidad.php?<?= $q ?>&pagina=<?= $next ?>">»</a>
              </li>
            </ul>
          </nav>

          <div class="small text-muted mt-2">
            Total: <?= $totalReg ?> registro(s) | Página <?= $pagina ?> de <?= $totalPag ?>
          </div>

        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const empleado = document.getElementById('empleado');
    const salario = document.getElementById('salario');

    function actualizarSalarioDiario() {
        if (!empleado || !salario) return;

        const option = empleado.options[empleado.selectedIndex];
        if (!option || !option.value) {
            salario.value = '';
            return;
        }

        salario.value = option.getAttribute('data-salario-diario') || '';
    }

    if (empleado) {
        empleado.addEventListener('change', actualizarSalarioDiario);
        actualizarSalarioDiario();
    }
})();
</script>
</body>
</html>