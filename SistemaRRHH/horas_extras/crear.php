<?php
session_start();
include("../config/conexion.php");

if (isset($_POST['guardar'])) {

    $empleado = $_POST['empleado'];
    $fecha = $_POST['fecha'];
    $horas = $_POST['horas'];
    $tipo = $_POST['tipo'];

    $monto = $horas * 5000; 

    $conexion->query("
        INSERT INTO horas_extras
        (fecha, cantidad_horas, tipo, monto_calculado,
        `Control de Asistencia_idControl de Asistencia`,
        `Control de Asistencia_Fecha`,
        `Control de Asistencia_Empleado_idEmpleado`)
        VALUES
        ('$fecha','$horas','$tipo','$monto',
        1,'$fecha','$empleado')
    ");

    header("Location: horas_extras.php");
}

$empleados = $conexion->query("SELECT * FROM empleado");
?>

<h2>Nueva Hora Extra</h2>

<form method="POST">
    <label>Empleado</label>
    <select name="empleado">
        <?php while($emp = $empleados->fetch_assoc()) { ?>
            <option value="<?= $emp['idEmpleado'] ?>">
                <?= $emp['Nombre'] ?>
            </option>
        <?php } ?>
    </select>

    <br><br>

    <label>Fecha</label>
    <input type="date" name="fecha" required>

    <br><br>

    <label>Horas</label>
    <input type="number" step="0.01" name="horas" required>

    <br><br>

    <label>Tipo</label>
    <select name="tipo">
        <option value="normal">Normal</option>
        <option value="feriado">Feriado</option>
    </select>

    <br><br>

    <button type="submit" name="guardar">Guardar</button>
</form>