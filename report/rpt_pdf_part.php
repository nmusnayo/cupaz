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
        $this->Cell(0, 10, "SISTEMA DE CONTROL 'LIGA DE FUTBOL'", 0, 1, 'C');
        $this->Cell(0, 10, "BY CODEFNATH", 0, 1, 'C');
        $this->Cell(0, 10, "Resultados de Partidos", 0, 1, 'C');

        // Fecha y hora
        $this->SetFont('Arial', '', 10);
        $fechaHora = date('d/m/Y H:i:s');
        $this->Cell(0, 10, "Fecha y hora de reporte: " . $this->convertxt($fechaHora), 0, 1, 'C');

        // Espacio adicional
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

$equipos_url = HTTP_BASE . "/controller/EquiposController.php?ope=filterall";
$equipos_response = file_get_contents($equipos_url);
$equipos_data = json_decode($equipos_response, true);
$equipos = isset($equipos_data['DATA']) ? $equipos_data['DATA'] : [];

$url = HTTP_BASE . "/controller/PartidosController.php?ope=" . $ope . "&page=" . $page . "&filter=" . $filter;
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

// Crear el PDF en orientación horizontal
$pdf = new PDF('L', 'mm', 'A4'); // 'L' para landscape
$pdf->AliasNbPages();
$pdf->AddPage();

// Cabecera de la tabla con color
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(100, 100, 200); // Color de fondo (gris claro)
$header = array(
    $pdf->convertxt("Id"),
    $pdf->convertxt("Fecha"),
    $pdf->convertxt("Hora"),
    $pdf->convertxt("Equipo Local"),
    $pdf->convertxt("Equipo Visitante"),
    $pdf->convertxt("Goles Local"),
    $pdf->convertxt("Goles Visitante"),
    $pdf->convertxt("Resultado"),
    $pdf->convertxt("Estado")
);
$widths = array(15, 25, 25, 40, 40, 30, 35, 40, 25);

for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($widths[$i], 7, $header[$i], 1, 0, 'C', true); // true para aplicar el color de relleno
}
$pdf->Ln();

// Cuerpo de la tabla
$pdf->SetFont('Arial', '', 8);
foreach ($records as $row) {
    $equipo_local = '';
    $equipo_visitante = '';

    foreach ($equipos as $equipo) {
        if ($equipo['id'] == $row['equipo_local_id']) {
            $equipo_local = htmlspecialchars($equipo['nombre']);
        }
        if ($equipo['id'] == $row['equipo_visitante_id']) {
            $equipo_visitante = htmlspecialchars($equipo['nombre']);
        }
    }

    $resultado = '';
    if ($row['goles_local'] > $row['goles_visitante']) {
        $resultado = "Ganó Equipo Local";
    } elseif ($row['goles_local'] < $row['goles_visitante']) {
        $resultado = "Ganó Equipo Visitante";
    } else {
        $resultado = "Empate";
    }

    $pdf->Cell($widths[0], 6, $pdf->convertxt($row['id']), 1); // Opciones, se puede dejar vacío o agregar texto/íconos si se desea
    $pdf->Cell($widths[1], 6, $pdf->convertxt($row['fecha']), 1);
    $pdf->Cell($widths[2], 6, $pdf->convertxt($row['hora']), 1);
    $pdf->Cell($widths[3], 6, $pdf->convertxt($equipo_local), 1);
    $pdf->Cell($widths[4], 6, $pdf->convertxt($equipo_visitante), 1);
    $pdf->Cell($widths[5], 6, $pdf->convertxt($row['goles_local']), 1);
    $pdf->Cell($widths[6], 6, $pdf->convertxt($row['goles_visitante']), 1);
    $pdf->Cell($widths[7], 6, $pdf->convertxt($resultado), 1);
    $pdf->Cell($widths[8], 6, $pdf->convertxt($row['estado']), 1);
    $pdf->Ln();
}

// Salida del PDF
$pdf->Output('Resultados_de_Partidos.pdf', 'I');
?>
