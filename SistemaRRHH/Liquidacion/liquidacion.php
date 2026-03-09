<?php
session_start();
include("../config/conexion.php");

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

if (!can('Liquidaciones_calcular') && !isAdmin()) {
    echo "<div style='margin:20px;padding:12px;border-radius:8px;background:#f8d7da;color:#842029;'>No tiene permisos para acceder al módulo de liquidaciones.</div>";
    exit();
}

$mensaje = $_SESSION['mensaje_liquidacion'] ?? '';
$tipoMsg = $_SESSION['tipo_liquidacion'] ?? 'success';
unset($_SESSION['mensaje_liquidacion'], $_SESSION['tipo_liquidacion']);

$sqlEmpleados = "SELECT idEmpleado, nombre, apellidos, Fecha_de_Ingreso, salario_mensual
                 FROM empleado
                 WHERE Estado = 'Activo'
                 ORDER BY nombre, apellidos ASC";

$resultEmpleados = $conexion->query($sqlEmpleados);

if (!$resultEmpleados) {
    die("Error al cargar empleados: " . $conexion->error);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Liquidaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body{
            font-family:Arial,Helvetica,sans-serif;
            background:#f4f6f9;
            margin:0;
            color:#111827;
        }
        .wrap{
            max-width:1100px;
            margin:0 auto;
            padding:24px;
        }
        .card{
            background:#fff;
            border-radius:18px;
            padding:22px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
        }
        .header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:18px;
        }
        .toolbar{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .btn{
            display:inline-block;
            padding:10px 14px;
            border-radius:10px;
            text-decoration:none;
            font-weight:700;
            border:none;
            cursor:pointer;
        }
        .btn-primary{
            background:#2563eb;
            color:#fff;
        }
        .btn-light{
            background:#e5e7eb;
            color:#111827;
        }
        .grid{
            display:grid;
            grid-template-columns:repeat(2, 1fr);
            gap:16px;
        }
        label{
            display:block;
            margin-bottom:6px;
            font-weight:700;
        }
        input, select{
            width:100%;
            padding:11px 12px;
            border:1px solid #d1d5db;
            border-radius:10px;
            box-sizing:border-box;
        }
        .info{
            background:#f9fafb;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:12px;
        }
        .info small{
            display:block;
            color:#6b7280;
            margin-bottom:4px;
        }
        .info strong{
            font-size:15px;
        }
        .alert{
            padding:12px 14px;
            border-radius:10px;
            margin-bottom:16px;
        }
        .alert-success{
            background:#d1fae5;
            color:#065f46;
        }
        .alert-danger{
            background:#fee2e2;
            color:#991b1b;
        }
        @media (max-width: 768px){
            .grid{
                grid-template-columns:1fr;
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div>
            <h1 style="margin:0;">Módulo de Liquidaciones</h1>
            <div style="color:#6b7280;">Calcula y registra la liquidación final del empleado.</div>
        </div>
        <div class="toolbar">
            <a href="historial.php" class="btn btn-light">Ver historial</a>
            <a href="../dashboard.php" class="btn btn-light">Volver al dashboard</a>
        </div>
    </div>

    <div class="card">
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($tipoMsg); ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form action="calcular.php" method="POST">
            <div class="grid">
                <div>
                    <label for="idEmpleado">Empleado</label>
                    <select name="idEmpleado" id="idEmpleado" required>
                        <option value="">Seleccione un empleado</option>
                        <?php while ($emp = $resultEmpleados->fetch_assoc()): ?>
                            <option
                                value="<?php echo (int)$emp['idEmpleado']; ?>"
                                data-fecha="<?php echo htmlspecialchars($emp['Fecha_de_Ingreso']); ?>"
                                data-salario="<?php echo htmlspecialchars($emp['salario_mensual']); ?>">
                                <?php echo htmlspecialchars($emp['nombre'] . ' ' . $emp['apellidos']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label for="fechaSalida">Fecha de salida</label>
                    <input type="date" name="fechaSalida" id="fechaSalida" required>
                </div>

                <div>
                    <label for="diasVacaciones">Días de vacaciones pendientes</label>
                    <input type="number" name="diasVacaciones" id="diasVacaciones" min="0" step="0.01" required>
                </div>

                <div class="info">
                    <small>Fecha de ingreso</small>
                    <strong id="txtFechaIngreso">--</strong>
                </div>

                <div class="info">
                    <small>Salario mensual</small>
                    <strong id="txtSalario">₡0.00</strong>
                </div>

                <div class="info">
                    <small>Vacaciones generadas</small>
                    <strong id="txtVacGeneradas">0</strong>
                </div>

                <div class="info">
                    <small>Vacaciones usadas</small>
                    <strong id="txtVacUsadas">0</strong>
                </div>

                <div class="info">
                    <small>Vacaciones pendientes</small>
                    <strong id="txtVacPendientes">0</strong>
                </div>
            </div>

            <div class="toolbar" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Calcular y guardar</button>
                <a href="historial.php" class="btn btn-light">Historial</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const empleado = document.getElementById('idEmpleado');
    const fechaSalida = document.getElementById('fechaSalida');
    const inputVacaciones = document.getElementById('diasVacaciones');

    const txtFechaIngreso = document.getElementById('txtFechaIngreso');
    const txtSalario = document.getElementById('txtSalario');
    const txtVacGeneradas = document.getElementById('txtVacGeneradas');
    const txtVacUsadas = document.getElementById('txtVacUsadas');
    const txtVacPendientes = document.getElementById('txtVacPendientes');

    async function cargarDatosEmpleado() {
        const idEmpleado = empleado.value;
        const salida = fechaSalida.value || '';

        if (!idEmpleado) {
            txtFechaIngreso.textContent = '--';
            txtSalario.textContent = '₡0.00';
            txtVacGeneradas.textContent = '0';
            txtVacUsadas.textContent = '0';
            txtVacPendientes.textContent = '0';
            inputVacaciones.value = '';
            return;
        }

        try {
            const url = `obtener_datos_empleado.php?idEmpleado=${encodeURIComponent(idEmpleado)}&fechaSalida=${encodeURIComponent(salida)}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const data = await response.json();

            if (!data.ok) {
                console.error('Error del servidor:', data.mensaje || 'Sin mensaje');
                txtFechaIngreso.textContent = '--';
                txtSalario.textContent = '₡0.00';
                txtVacGeneradas.textContent = '0';
                txtVacUsadas.textContent = '0';
                txtVacPendientes.textContent = '0';
                inputVacaciones.value = '';
                return;
            }

            txtFechaIngreso.textContent = data.fechaIngreso ?? '--';

            const salario = parseFloat(data.salario || 0);
            txtSalario.textContent = '₡' + salario.toLocaleString('es-CR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            txtVacGeneradas.textContent = data.diasGenerados ?? 0;
            txtVacUsadas.textContent = data.diasUsados ?? 0;
            txtVacPendientes.textContent = data.diasPendientes ?? 0;

            inputVacaciones.value = data.diasPendientes ?? 0;
        } catch (error) {
            console.error('Error en fetch:', error);
            txtFechaIngreso.textContent = '--';
            txtSalario.textContent = '₡0.00';
            txtVacGeneradas.textContent = '0';
            txtVacUsadas.textContent = '0';
            txtVacPendientes.textContent = '0';
            inputVacaciones.value = '';
        }
    }

    empleado.addEventListener('change', cargarDatosEmpleado);
    fechaSalida.addEventListener('change', cargarDatosEmpleado);
});
</script>
</body>
</html>