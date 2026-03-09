<?php
session_start();
include("../config/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: ../Seguridad/Login.php");
    exit();
}

$mesActual  = (int)date("m");
$anioActual = (int)date("Y");
$fecha      = date("Y-m-d");
$usuario    = (int)($_SESSION['idUsuario'] ?? 0);

if ($usuario <= 0) {
    die("Sesión inválida.");
}

/* ===========================
   1) Verificar cabecera del mes
=========================== */
$stmtV = $conexion->prepare("
    SELECT idPlanilla
    FROM planilla
    WHERE Mes = ? AND Anio = ?
    LIMIT 1
");

if (!$stmtV) {
    die("Error preparando verificación de planilla: " . $conexion->error);
}

$stmtV->bind_param("ii", $mesActual, $anioActual);
$stmtV->execute();
$cab = $stmtV->get_result()->fetch_assoc();

if ($cab) {
    echo "<script>alert('Ya se generó la planilla este mes'); window.location='planillas.php';</script>";
    exit();
}

/* ===========================
   2) Crédito fiscal activo
=========================== */
$resCF = $conexion->query("
    SELECT idCredito_Fiscal, valor
    FROM credito_fiscal
    WHERE Estado = b'1'
    LIMIT 1
");

if (!$resCF) {
    die("Error consultando crédito fiscal: " . $conexion->error);
}

$cfRow = $resCF->fetch_assoc();
$idCF  = (int)($cfRow['idCredito_Fiscal'] ?? 0);
$creditoFiscalValor = (float)($cfRow['valor'] ?? 0);

if ($idCF <= 0) {
    die("Error: No existe crédito fiscal activo en credito_fiscal.");
}

/* ===========================
   3) Empleados activos
=========================== */
$empleados = $conexion->query("
    SELECT idEmpleado, salario_mensual
    FROM empleado
    WHERE Estado = 'Activo'
");

if (!$empleados) {
    die("Error consultando empleados: " . $conexion->error);
}

if ($empleados->num_rows === 0) {
    die("No hay empleados activos para generar la planilla.");
}

/* ===========================
   4) Preparar consultas
=========================== */

// Horas extra del mes
$stmtHoras = $conexion->prepare("
    SELECT
        COALESCE(SUM(cantidad_horas), 0) AS total_horas,
        COALESCE(SUM(monto_calculado), 0) AS total_monto
    FROM horas_extras
    WHERE empleado_id = ?
      AND MONTH(fecha) = ?
      AND YEAR(fecha) = ?
");

if (!$stmtHoras) {
    die("Error preparando consulta de horas extra: " . $conexion->error);
}

// Solicitudes validadas por RRHH y no aplicadas
$stmtMov = $conexion->prepare("
    SELECT
        idSolicitud,
        tipo_solicitud,
        monto,
        descripcion
    FROM solicitudes_planilla
    WHERE idEmpleado = ?
      AND estado = 'Validado_RRHH'
      AND estado_registro = b'1'
      AND aplicado_planilla = b'0'
");

if (!$stmtMov) {
    die("Error preparando consulta de solicitudes de planilla: " . $conexion->error);
}

// Marcar solicitud como aplicada
$stmtAplicada = $conexion->prepare("
    UPDATE solicitudes_planilla
    SET
        aplicado_planilla = b'1',
        usuario_modificacion = ?,
        fecha_modificacion = NOW(),
        estado = 'Aplicado_Planilla'
    WHERE idSolicitud = ?
");

if (!$stmtAplicada) {
    die("Error preparando actualización de solicitud aplicada: " . $conexion->error);
}

// Tramo de renta
$stmtTramo = $conexion->prepare("
    SELECT idRenta_Tramo, monto_min, monto_max, tasa_pct
    FROM renta_tramo
    WHERE estado = 1
      AND ? >= monto_min
      AND (? <= monto_max OR monto_max IS NULL OR monto_max = 0)
    ORDER BY monto_min DESC
    LIMIT 1
");

if (!$stmtTramo) {
    die("Error preparando consulta de renta: " . $conexion->error);
}

// Insert detalle planilla
$stmtDet = $conexion->prepare("
    INSERT INTO planillas
    (
        Fecha_planilla,
        salario_base,
        total_horas_extra,
        total_deducciones,
        total_a_pagar,
        Planilla_idPlanilla,
        Empleado_idEmpleado,
        Credito_Fiscal_idCredito_Fiscal,
        Renta_Tramo_idRenta_Tramo,
        Fecha_creacion,
        Usuario_creacion,
        fecha_modificacion,
        Usuario_modificacion,
        Estado
    )
    VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), ?, 'Activa')
");

if (!$stmtDet) {
    die("Error preparando inserción de detalle: " . $conexion->error);
}

// Insert cabecera
$stmtCab = $conexion->prepare("
    INSERT INTO planilla
    (
        Fecha_planilla,
        Fecha_creacion,
        Usuario_creacion,
        Estado,
        Mes,
        Anio
    )
    VALUES
    (?, NOW(), ?, 'Activa', ?, ?)
");

if (!$stmtCab) {
    die("Error preparando inserción de cabecera: " . $conexion->error);
}

/* ===========================
   5) Generar planilla
=========================== */
$conexion->begin_transaction();

try {
    // Crear cabecera
    $stmtCab->bind_param("siii", $fecha, $usuario, $mesActual, $anioActual);

    if (!$stmtCab->execute()) {
        throw new Exception("No se pudo crear la cabecera de planilla: " . $stmtCab->error);
    }

    $idPlanilla = (int)$conexion->insert_id;

    if ($idPlanilla <= 0) {
        throw new Exception("No se pudo obtener el id de la cabecera de planilla.");
    }

    // Insertar detalle por empleado
    while ($emp = $empleados->fetch_assoc()) {

        $idEmpleado  = (int)$emp['idEmpleado'];
        $salarioBase = (float)$emp['salario_mensual'];

        /* ===========================
           HORAS EXTRA
        =========================== */
        $stmtHoras->bind_param("iii", $idEmpleado, $mesActual, $anioActual);

        if (!$stmtHoras->execute()) {
            throw new Exception("Error consultando horas extra del empleado {$idEmpleado}: " . $stmtHoras->error);
        }

        $hx = $stmtHoras->get_result()->fetch_assoc();
        $montoHoras = (float)($hx['total_monto'] ?? 0);

        /* ===========================
           MOVIMIENTOS DE SOLICITUDES
        =========================== */
        $bonificaciones     = 0.00;
        $deduccionesExtra   = 0.00;
        $ajustesSalariales  = 0.00;
        $idsSolicitudesAplicadas = [];

        $stmtMov->bind_param("i", $idEmpleado);

        if (!$stmtMov->execute()) {
            throw new Exception("Error consultando solicitudes del empleado {$idEmpleado}: " . $stmtMov->error);
        }

        $movs = $stmtMov->get_result();

        while ($mov = $movs->fetch_assoc()) {
            $idSolicitud = (int)$mov['idSolicitud'];
            $tipo        = trim((string)$mov['tipo_solicitud']);
            $monto       = (float)($mov['monto'] ?? 0);

            if ($monto < 0) {
                $monto = abs($monto);
            }

            switch ($tipo) {
                case 'Bonificacion':
                    $bonificaciones += $monto;
                    $idsSolicitudesAplicadas[] = $idSolicitud;
                    break;

                case 'Deduccion':
                    $deduccionesExtra += $monto;
                    $idsSolicitudesAplicadas[] = $idSolicitud;
                    break;

                case 'Ajuste Salarial':
                    $ajustesSalariales += $monto;
                    $idsSolicitudesAplicadas[] = $idSolicitud;
                    break;

                case 'Horas Extra':
                    // Ya se toman desde la tabla horas_extras, aquí solo marcamos aplicada
                    $idsSolicitudesAplicadas[] = $idSolicitud;
                    break;
            }
        }

        /* ===========================
           CÁLCULOS
        =========================== */
        $salarioBruto = $salarioBase + $montoHoras + $bonificaciones + $ajustesSalariales;

        // CCSS empleado
        $ccss = $salarioBruto * 0.0934;

        // Tramo renta
        $stmtTramo->bind_param("dd", $salarioBruto, $salarioBruto);

        if (!$stmtTramo->execute()) {
            throw new Exception("Error consultando tramo de renta para empleado {$idEmpleado}: " . $stmtTramo->error);
        }

        $tramo = $stmtTramo->get_result()->fetch_assoc();

        if (!$tramo) {
            throw new Exception(
                "No hay tramo de renta para salario bruto ₡" . number_format($salarioBruto, 2) .
                ". Revisa renta_tramo."
            );
        }

        $idTramo  = (int)$tramo['idRenta_Tramo'];
        $tasaPct  = (float)$tramo['tasa_pct'];
        $montoMin = (float)$tramo['monto_min'];

        // Renta
        $renta = 0.00;
        if ($tasaPct > 0) {
            $renta = max(0, ($salarioBruto - $montoMin) * ($tasaPct / 100));
        }

        // Aplicar crédito fiscal
        if ($creditoFiscalValor > 0) {
            $renta = max(0, $renta - $creditoFiscalValor);
        }

        $deducciones = $ccss + $renta + $deduccionesExtra;
        $totalPagar  = $salarioBruto - $deducciones;

        /* ===========================
           INSERTAR DETALLE
        =========================== */
        $stmtDet->bind_param(
            "sddddiiiii",
            $fecha,
            $salarioBase,
            $montoHoras,
            $deducciones,
            $totalPagar,
            $idPlanilla,
            $idEmpleado,
            $idCF,
            $idTramo,
            $usuario,
            $usuario
        );

        if (!$stmtDet->execute()) {
            throw new Exception("Error insertando detalle empleado {$idEmpleado}: " . $stmtDet->error);
        }

        /* ===========================
           MARCAR SOLICITUDES APLICADAS
        =========================== */
        foreach ($idsSolicitudesAplicadas as $idSolicitudAplicada) {
            $stmtAplicada->bind_param("ii", $usuario, $idSolicitudAplicada);

            if (!$stmtAplicada->execute()) {
                throw new Exception("Error marcando solicitud {$idSolicitudAplicada} como aplicada: " . $stmtAplicada->error);
            }
        }
    }

    $conexion->commit();
    echo "<script>alert('Planilla generada correctamente'); window.location='planillas.php';</script>";
    exit();

} catch (Exception $e) {
    $conexion->rollback();
    die("Error generando planilla: " . $e->getMessage());
}
?>