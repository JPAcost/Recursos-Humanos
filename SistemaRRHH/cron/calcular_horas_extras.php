<?php
include(__DIR__ . "/../config/conexion.php");

/* ======================================================
   FUNCION FERIADOS COSTA RICA
====================================================== */
function esFeriadoCR($fecha){
    $anio = date("Y", strtotime($fecha));
    $feriados = [
        "$anio-01-01",
        "$anio-04-11",
        "$anio-05-01",
        "$anio-07-25",
        "$anio-08-15",
        "$anio-09-15",
        "$anio-12-25"
    ];
    return in_array($fecha,$feriados);
}

/* ======================================================
   FECHA A PROCESAR (AYER)
====================================================== */
$fecha = date("Y-m-d", strtotime("-1 day"));

/* ======================================================
   OBTENER EMPLEADOS ACTIVOS
====================================================== */
$empleados = $conexion->query("
    SELECT idEmpleado, salario_hora
    FROM empleado
    WHERE Estado='Activo'
");

while($emp = $empleados->fetch_assoc()){

    $idEmpleado = $emp['idEmpleado'];
    $salarioHora = $emp['salario_hora'];

    /* ==========================================
       VALIDAR SI YA EXISTE REGISTRO
    ========================================== */
    $val = $conexion->prepare("
        SELECT idHoras_Extras
        FROM horas_extras
        WHERE `Control de Asistencia_Empleado_idEmpleado`=?
        AND fecha=? AND Estado=1
    ");
    $val->bind_param("is",$idEmpleado,$fecha);
    $val->execute();
    if($val->get_result()->num_rows>0){
        continue;
    }

    /* ==========================================
       BUSCAR MARCAS
    ========================================== */
    $entrada = $conexion->prepare("
        SELECT Hora
        FROM control_asistencia
        WHERE Empleado_idEmpleado=? 
        AND Fecha=? 
        AND Tipo_de_marca_idTipo_de_marca=1
    ");
    $entrada->bind_param("is",$idEmpleado,$fecha);
    $entrada->execute();
    $resEntrada = $entrada->get_result()->fetch_assoc();

    $salida = $conexion->prepare("
        SELECT Hora
        FROM control_asistencia
        WHERE Empleado_idEmpleado=? 
        AND Fecha=? 
        AND Tipo_de_marca_idTipo_de_marca=2
    ");
    $salida->bind_param("is",$idEmpleado,$fecha);
    $salida->execute();
    $resSalida = $salida->get_result()->fetch_assoc();

    if(!$resEntrada || !$resSalida){
        continue;
    }

    $horaEntrada = $resEntrada['Hora'];
    $horaSalida  = $resSalida['Hora'];

    /* ==========================================
       BUSCAR TURNO PROGRAMADO
    ========================================== */
    $turno = $conexion->prepare("
        SELECT hora_entrada, hora_salida
        FROM empleado_turno
        WHERE idEmpleado=? AND fecha=? AND Estado=1
    ");
    $turno->bind_param("is",$idEmpleado,$fecha);
    $turno->execute();
    $datosTurno = $turno->get_result()->fetch_assoc();

    if(!$datosTurno){
        continue;
    }

    $horaEntradaTurno = $datosTurno['hora_entrada'];
    $horaSalidaTurno  = $datosTurno['hora_salida'];

    $horasProgramadas = (strtotime($horaSalidaTurno) - strtotime($horaEntradaTurno)) / 3600;
    $horasTrabajadas = (strtotime($horaSalida) - strtotime($horaEntrada)) / 3600;

    /* Validación legal */
    if($horasTrabajadas > 12){
        $horasTrabajadas = 12;
    }

    $horasExtra = max(0, $horasTrabajadas - $horasProgramadas);

    if($horasExtra <= 0){
        continue;
    }

    /* ==========================================
       FACTOR LEGAL
    ========================================== */
    $factor = esFeriadoCR($fecha) ? 2.0 : 1.5;
    $tipo   = esFeriadoCR($fecha) ? "feriado" : "normal";

    $monto = $horasExtra * $salarioHora * $factor;

    /* ==========================================
       INSERTAR REGISTRO
    ========================================== */
    $stmt = $conexion->prepare("
        INSERT INTO horas_extras
        (fecha,hora_entrada,hora_salida,cantidad_horas,tipo,
        salario_hora,monto_calculado,Fecha_creacion,
        Usuario_creacion,Estado,
        `Control de Asistencia_Empleado_idEmpleado`)
        VALUES (?,?,?,?,?,?,?,NOW(),1,1,?)
    ");

    $stmt->bind_param(
        "sssdsdii",
        $fecha,$horaEntrada,$horaSalida,$horasExtra,
        $tipo,$salarioHora,$monto,
        $idEmpleado
    );

    $stmt->execute();
}

echo "Proceso completado para $fecha";