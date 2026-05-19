<?php
require_once(dirname(__DIR__) . "/config/global.php");
include (ROOT_CORE . "/fpdf/fpdf.php");

class PDF extends FPDF
{
    function convertxt($p_txt)
    {
        return iconv('UTF-8', 'iso-8859-1', $p_txt);
    }

    function Header()
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, "SISTEMA DE CONTROL 'PROPIETARIOS'", 0, 1, 'C');
        $this->Cell(0, 10, "BY CODEFNATH", 0, 1, 'C');
        $this->Cell(0, 10, "Administración de Propietarios", 0, 1, 'C');

        $this->SetFont('Arial', '', 10);
        $fechaHora = date('d/m/Y H:i:s');
        $this->Cell(0, 10, "Fecha y hora de reporte: " . $this->convertxt($fechaHora), 0, 1, 'C');

        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, $this->convertxt("Página ") . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Cargar datos
$page = 1;
$ope = 'filterSearch';
$filter = '';
$items_per_page = 10;
$total_pages = 1;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $page = isset($_POST['page']) ? $_POST['page'] : 1;
    $filter = urlencode(trim(isset($_POST['filter']) ? $_POST['filter'] : ''));
}

$url = HTTP_BASE . "/controller/EquiposController.php?ope=" . $ope . "&page=" . $page . "&filter=" . $filter;
$filter = urldecode($filter);
$response = file_get_contents($url);
$responseData = json_decode($response, true);
$records = $responseData['DATA'];
$totalItems = $responseData['LENGTH'];
try {
    $total_pages = ceil($totalItems / $items_per_page);
} catch (Exception $e) {
    $total_pages = 1;
}

// Crear PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Cabecera de la tabla con color
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(100, 100, 200);
$header = array(
    $pdf->convertxt("ID"),
    $pdf->convertxt("Nombre"),
    $pdf->convertxt("Tipo Persona"),
    $pdf->convertxt("CI/RUC"),
    $pdf->convertxt("Teléfono"),
    $pdf->convertxt("Correo")
);
$widths = array(15, 50, 30, 30, 30, 50);

for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($widths[$i], 7, $header[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Cuerpo de la tabla
$pdf->SetFont('Arial', '', 10);
foreach ($records as $row) {
    $pdf->Cell($widths[0], 6, $pdf->convertxt($row['id']), 1);
    $pdf->Cell($widths[1], 6, $pdf->convertxt($row['nombre']), 1);
    $pdf->Cell($widths[2], 6, $pdf->convertxt($row['tipo_persona']), 1);
    $pdf->Cell($widths[3], 6, $pdf->convertxt($row['ci_ruc']), 1);
    $pdf->Cell($widths[4], 6, $pdf->convertxt($row['telefono']), 1);
    $pdf->Cell($widths[5], 6, $pdf->convertxt($row['correo']), 1);
    $pdf->Ln();
}

// Salida del PDF
$pdf->Output('Administracion_de_Propietarios.pdf', 'I');
?>
