<?php
session_start();
require("../config/permisos.php");

verificarSesion();
verificarPermiso('Aguinaldos_calcular_RH');

include("../config/conexion.php");

$usuario = (int)($_SESSION['idUsuario'] ?? 0);
$periodo = $_POST['periodo'] ?? null;

if (!$periodo || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodo)) {
  $_SESSION['mensaje'] = "Período inválido.";
  $_SESSION['tipo'] = "danger";
  header("Location: aguinaldo.php");
  exit();
}

$conexion->begin_transaction();

try {
  // Empleados activos
  $resEmp = $conexion->query("
    SELECT idEmpleado
    FROM empleado
    WHERE Estado = 'Activo'
  ");
  if (!$resEmp) {
    throw new Exception("Error consultando empleados: " . $conexion->error);
  }

  // Suma últimos 12 meses tomando como referencia el periodo
  $stmtSum = $conexion->prepare("
    SELECT COALESCE(SUM(p.total_a_pagar),0) AS total_salarios
    FROM planillas p
    WHERE p.Empleado_idEmpleado = ?
      AND (p.Estado = 'Activa' OR p.Estado IS NULL)
      AND p.Fecha_planilla >= DATE_SUB(?, INTERVAL 12 MONTH)
      AND p.Fecha_planilla <= ?
  ");

  // Insert (asumiendo que ya arreglaste AUTO_INCREMENT + UNIQUE por empleado/periodo)
  $stmtIns = $conexion->prepare("
    INSERT INTO aguinaldo
      (periodo, monto_calculado, Empleado_idEmpleado, Fecha_Creacion, Usuario_creacion, Estado)
    VALUES
      (?, ?, ?, NOW(), ?, b'1')
  ");

  $insertados = 0;
  $omitidos   = 0;

  while ($emp = $resEmp->fetch_assoc()) {
    $idEmpleado = (int)$emp['idEmpleado'];

    // calcular
    $stmtSum->bind_param("iss", $idEmpleado, $periodo, $periodo);
    $stmtSum->execute();
    $row = $stmtSum->get_result()->fetch_assoc();

    $totalSalarios = (float)($row['total_salarios'] ?? 0);
    $aguinaldo = $totalSalarios / 12;

    // insertar (si existe UNIQUE uq_aguinaldo_emp_periodo, aquí evita duplicados)
    $stmtIns->bind_param("sdii", $periodo, $aguinaldo, $idEmpleado, $usuario);

    if (!$stmtIns->execute()) {
      // Duplicado por UNIQUE
      if ($conexion->errno == 1062) { // duplicate entry
        $omitidos++;
        continue;
      }
      throw new Exception("Error insertando aguinaldo empleado $idEmpleado: " . $stmtIns->error);
    }

    $insertados++;
  }

  $conexion->commit();

  $_SESSION['mensaje'] = "Aguinaldo generado. Insertados: $insertados | Omitidos: $omitidos";
  $_SESSION['tipo'] = "success";
  header("Location: aguinaldo.php");
  exit();

} catch (Exception $e) {
  $conexion->rollback();
  $_SESSION['mensaje'] = "Error: " . $e->getMessage();
  $_SESSION['tipo'] = "danger";
  header("Location: aguinaldo.php");
  exit();
}