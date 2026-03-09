<?php
require('../fpdf/fpdf.php');
include("../config/conexion.php");

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',12);

$pdf->Cell(190,10,'Reporte de Empleados Activos',0,1,'C');

$pdf->SetFont('Arial','',10);

$resultado = $conexion->query("
SELECT nombre, cedula, puesto, salario_mensual 
FROM empleado WHERE Estado='Activo'
");

while($fila = $resultado->fetch_assoc()){
$pdf->Cell(190,8,
$fila['nombre']." - ".$fila['cedula']." - ".$fila['puesto']." - ₡".$fila['salario_mensual'],
0,1);
}

$pdf->Output();
?>