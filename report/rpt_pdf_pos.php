<?php
require_once(dirname(__DIR__) . "/config/global.php");
include (ROOT_CORE . "/fpdf/fpdf.php");

// Desactivar warnings para FPDF
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

class PDF extends FPDF
{
    function convertxt($p_txt)
    {
        return iconv('UTF-8', 'iso-8859-1', $p_txt);
    }

    function Header()
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, "SISTEMA DE CONTROL CATASTRAL", 0, 1, 'C');
        $this->Cell(0, 10, "BY CODEFNATH", 0, 1, 'C');
        $this->Cell(0, 10, "Mapas de Predios", 0, 1, 'C');

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

// Variables iniciales
$page = 1;
$ope = 'filterSearch';
$filter = '';
$items_per_page = 10;

// Filtrado si se envía POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $page = isset($_POST['page']) ? $_POST['page'] : 1;
    $filter = urlencode(trim(isset($_POST['filter']) ? $_POST['filter'] : ''));
}

// Traer predios
$predios_url = HTTP_BASE . "/controller/JugadoresController.php?ope=filterall";
$predios_response = file_get_contents($predios_url);
$predios_data = json_decode($predios_response, true);
$predios = [];
if (is_array($predios_data) && isset($predios_data['DATA'])) {
    $predios = $predios_data['DATA'];
}

// Traer mapas
$mapas_url = HTTP_BASE . "/controller/PosicionesController.php?ope=" . $ope . "&page=" . $page . "&filter=" . $filter;
$mapas_response = file_get_contents($mapas_url);
$mapas_data = json_decode($mapas_response, true);
$records = [];
if (is_array($mapas_data) && isset($mapas_data['DATA'])) {
    $records = $mapas_data['DATA'];
}

// Crear PDF en orientación horizontal
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Cabecera de la tabla
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(100, 100, 200);
$header = array(
    $pdf->convertxt("ID"),
    $pdf->convertxt("Predio"),
    $pdf->convertxt("Coordenadas"),
    $pdf->convertxt("URL Mapa")
);
$widths = array(15, 50, 80, 100);

for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($widths[$i], 7, $header[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Cuerpo de la tabla
$pdf->SetFont('Arial', '', 8);
foreach ($records as $row) {
    // Obtener nombre del predio
    $predio_nombre = '';
    foreach ($predios as $predio) {
        if ($predio['id'] == $row['predio_id']) {
            $predio_nombre = htmlspecialchars($predio['codigo_predio']);
            break;
        }
    }

    $pdf->Cell($widths[0], 6, $pdf->convertxt($row['id']), 1);
    $pdf->Cell($widths[1], 6, $pdf->convertxt($predio_nombre), 1);
    $pdf->Cell($widths[2], 6, $pdf->convertxt($row['coordenadas']), 1);
    $pdf->Cell($widths[3], 6, $pdf->convertxt($row['url_mapa']), 1);
    $pdf->Ln();
}

// Salida del PDF
$pdf->Output('Mapas_Predios.pdf', 'I');
?>
