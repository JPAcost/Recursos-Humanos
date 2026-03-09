<?php
session_start();
require('../librerias/fpdf/fpdf.php');
include("../config/conexion.php");

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',12);

$pdf->Cell(190,10,'Bitacora del Sistema',0,1,'C');
$pdf->Ln(5);

$pdf->SetFont('Arial','',8);

$result = $conexion->query("
    SELECT b.*, u.Nombre
    FROM bitacora b
    INNER JOIN usuario u ON b.idUsuario = u.idUsuario
    ORDER BY b.fecha DESC
");

while($row = $result->fetch_assoc()) {

    $texto = $row['Nombre']." | ".
             $row['accion']." | ".
             $row['tabla_afectada']." | ".
             $row['fecha'];

    $pdf->MultiCell(0,5,$texto,1);
}

$pdf->Output();
?>