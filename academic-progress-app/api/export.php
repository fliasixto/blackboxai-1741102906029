<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';
require_once '../vendor/autoload.php'; // You'll need to run composer to install dependencies

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;

class ExportAPI {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Export student grades report
    public function exportGradesReport($format, $filters = []) {
        try {
            // Get grades data
            $sql = "SELECT 
                    s.student_code,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    sub.name as subject_name,
                    g.grade,
                    g.evaluation_type,
                    g.evaluation_date,
                    g.comments
                   FROM grades g
                   JOIN students s ON g.student_id = s.id
                   JOIN subjects sub ON g.subject_id = sub.id
                   WHERE 1=1";
            
            $params = [];

            if (!empty($filters['student_id'])) {
                $sql .= " AND g.student_id = :student_id";
                $params[':student_id'] = $filters['student_id'];
            }

            if (!empty($filters['subject_id'])) {
                $sql .= " AND g.subject_id = :subject_id";
                $params[':subject_id'] = $filters['subject_id'];
            }

            $sql .= " ORDER BY s.last_name, sub.name, g.evaluation_date";

            $grades = $this->db->getAll($sql, $params);

            if ($format === 'pdf') {
                return $this->generatePDFReport($grades, 'grades');
            } else {
                return $this->generateExcelReport($grades, 'grades');
            }
        } catch (Exception $e) {
            throw new Exception("Error al exportar reporte de calificaciones: " . $e->getMessage());
        }
    }

    // Export attendance report
    public function exportAttendanceReport($format, $filters = []) {
        try {
            // Get attendance data
            $sql = "SELECT 
                    s.student_code,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    sub.name as subject_name,
                    a.date,
                    a.status,
                    a.justification
                   FROM attendance a
                   JOIN students s ON a.student_id = s.id
                   JOIN subjects sub ON a.subject_id = sub.id
                   WHERE 1=1";
            
            $params = [];

            if (!empty($filters['student_id'])) {
                $sql .= " AND a.student_id = :student_id";
                $params[':student_id'] = $filters['student_id'];
            }

            if (!empty($filters['subject_id'])) {
                $sql .= " AND a.subject_id = :subject_id";
                $params[':subject_id'] = $filters['subject_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND a.date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND a.date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            $sql .= " ORDER BY s.last_name, sub.name, a.date";

            $attendance = $this->db->getAll($sql, $params);

            if ($format === 'pdf') {
                return $this->generatePDFReport($attendance, 'attendance');
            } else {
                return $this->generateExcelReport($attendance, 'attendance');
            }
        } catch (Exception $e) {
            throw new Exception("Error al exportar reporte de asistencia: " . $e->getMessage());
        }
    }

    // Generate PDF report
    private function generatePDFReport($data, $type) {
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Sistema de Gestión Académica');
        $pdf->SetAuthor('Sistema de Gestión Académica');
        $pdf->SetTitle(ucfirst($type) . ' Report');

        // Set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'Sistema de Gestión Académica', ucfirst($type) . ' Report - ' . date('Y-m-d'));

        // Set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // Add a page
        $pdf->AddPage();

        // Create the table content
        $html = '<table border="1" cellpadding="4">';
        
        // Add headers
        $html .= '<tr style="background-color: #f1f5f9;">';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . ucwords(str_replace('_', ' ', $header)) . '</th>';
        }
        $html .= '</tr>';

        // Add data rows
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        // Print the table
        $pdf->writeHTML($html, true, false, true, false, '');

        // Close and output PDF document
        $pdf->Output('report_' . $type . '_' . date('Y-m-d') . '.pdf', 'D');
        
        return true;
    }

    // Generate Excel report
    private function generateExcelReport($data, $type) {
        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $column = 'A';
        foreach (array_keys($data[0]) as $header) {
            $sheet->setCellValue($column . '1', ucwords(str_replace('_', ' ', $header)));
            $column++;
        }

        // Add data
        $row = 2;
        foreach ($data as $record) {
            $column = 'A';
            foreach ($record as $value) {
                $sheet->setCellValue($column . $row, $value);
                $column++;
            }
            $row++;
        }

        // Auto-size columns
        foreach (range('A', $spreadsheet->getActiveSheet()->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Style the header row
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

        // Create the Excel file
        $writer = new Xlsx($spreadsheet);
        
        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="report_' . $type . '_' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');

        // Save file to output
        $writer->save('php://output');
        
        return true;
    }
}

// Handle API requests
try {
    $api = new ExportAPI();
    $type = $_GET['type'] ?? '';
    $format = $_GET['format'] ?? 'pdf';
    $filters = [
        'student_id' => $_GET['student_id'] ?? null,
        'subject_id' => $_GET['subject_id'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null
    ];

    switch ($type) {
        case 'grades':
            $api->exportGradesReport($format, $filters);
            break;

        case 'attendance':
            $api->exportAttendanceReport($format, $filters);
            break;

        default:
            throw new Exception("Tipo de reporte no válido");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>
