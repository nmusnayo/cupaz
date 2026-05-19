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
        $this->Cell(0, 10, "SISTEMA DE CONTROL CATASTRAL", 0, 1, 'C');
        $this->Cell(0, 10, "BY CODEFNATH", 0, 1, 'C');
        $this->Cell(0, 10, "Documentos Adjuntos a Predios", 0, 1, 'C');

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $page = isset($_POST['page']) ? $_POST['page'] : 1;
    $filter = urlencode(trim(isset($_POST['filter']) ? $_POST['filter'] : ''));
}

// Traer predios
$predios_url = HTTP_BASE . "/controller/JugadoresController.php?ope=filterall";
$predios_response = file_get_contents($predios_url);
$predios_data = json_decode($predios_response, true);
$predios = isset($predios_data['DATA']) ? $predios_data['DATA'] : [];

// Traer documentos
$url = HTTP_BASE . "/controller/LigasController.php?ope=" . $ope . "&page=" . $page . "&filter=" . $filter;
$filter = urldecode($filter);
$response = file_get_contents($url);
$responseData = json_decode($response, true);
$records = $responseData['DATA'];
$totalItems = $responseData['LENGTH'];
$total_pages = $totalItems > 0 ? ceil($totalItems / $items_per_page) : 1;

// Crear el PDF en orientación horizontal
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Cabecera de la tabla con color
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(100, 100, 200);
$header = array(
    $pdf->convertxt("ID"),
    $pdf->convertxt("Predio"),
    $pdf->convertxt("Tipo Documento"),
    $pdf->convertxt("Nombre Archivo"),
    $pdf->convertxt("Ruta Archivo"),
    $pdf->convertxt("Fecha Subida")
);
$widths = array(15, 40, 40, 60, 70, 25);

for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($widths[$i], 7, $header[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Cuerpo de la tabla
$pdf->SetFont('Arial', '', 8);
foreach ($records as $row) {
    $pdf->Cell($widths[0], 6, $pdf->convertxt($row['id']), 1);

    // Obtener código o nombre del predio
    $predio_nombre = '';
    foreach ($predios as $predio) {
        if ($predio['id'] == $row['predio_id']) {
            $predio_nombre = htmlspecialchars($predio['codigo_predio'] ?? $predio['direccion']);
            break;
        }
    }
    $pdf->Cell($widths[1], 6, $pdf->convertxt($predio_nombre), 1);

    $pdf->Cell($widths[2], 6, $pdf->convertxt($row['tipo_documento']), 1);
    $pdf->Cell($widths[3], 6, $pdf->convertxt($row['nombre_archivo']), 1);
    $pdf->Cell($widths[4], 6, $pdf->convertxt($row['ruta_archivo']), 1);
    $pdf->Cell($widths[5], 6, $pdf->convertxt($row['fecha_subida']), 1);

    $pdf->Ln();
}

// Salida del PDF
$pdf->Output('Documentos_Predios.pdf', 'I');
?>
