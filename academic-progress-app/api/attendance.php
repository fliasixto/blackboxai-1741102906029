<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

class AttendanceAPI {
    private $db;
    private $table = "attendance";

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get attendance records with optional filters
    public function getAttendance($filters = []) {
        try {
            $sql = "SELECT a.*,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    s.student_code,
                    sub.name as subject_name,
                    sub.subject_code
                   FROM {$this->table} a
                   JOIN students s ON a.student_id = s.id
                   JOIN subjects sub ON a.subject_id = sub.id
                   WHERE 1=1";
            
            $params = [];

            // Apply filters
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

            if (!empty($filters['status'])) {
                $sql .= " AND a.status = :status";
                $params[':status'] = $filters['status'];
            }

            $sql .= " ORDER BY a.date DESC, s.last_name ASC";

            $attendance = $this->db->getAll($sql, $params);

            return [
                'error' => false,
                'message' => 'Registros de asistencia recuperados exitosamente',
                'data' => $attendance
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar registros de asistencia: " . $e->getMessage());
        }
    }

    // Get attendance record by ID
    public function getAttendanceById($id) {
        try {
            $sql = "SELECT a.*,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    s.student_code,
                    sub.name as subject_name,
                    sub.subject_code
                   FROM {$this->table} a
                   JOIN students s ON a.student_id = s.id
                   JOIN subjects sub ON a.subject_id = sub.id
                   WHERE a.id = :id";

            $attendance = $this->db->getOne($sql, [':id' => $id]);

            if (!$attendance) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Registro de asistencia no encontrado'
                ];
            }

            return [
                'error' => false,
                'message' => 'Registro de asistencia recuperado exitosamente',
                'data' => $attendance
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar registro de asistencia: " . $e->getMessage());
        }
    }

    // Create new attendance records (bulk)
    public function createAttendance($data) {
        try {
            // Validate required fields
            if (empty($data['subject_id']) || empty($data['date']) || empty($data['records'])) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'Faltan campos requeridos'
                ];
            }

            // Validate subject exists
            if (!$this->db->exists("SELECT 1 FROM subjects WHERE id = :id", [':id' => $data['subject_id']])) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'La asignatura especificada no existe'
                ];
            }

            // Start transaction
            $this->db->beginTransaction();

            $insertedIds = [];
            $date = Database::formatDate($data['date']);

            foreach ($data['records'] as $record) {
                // Validate student exists
                if (!$this->db->exists("SELECT 1 FROM students WHERE id = :id", [':id' => $record['student_id']])) {
                    $this->db->rollback();
                    http_response_code(400);
                    return [
                        'error' => true,
                        'message' => "El estudiante con ID {$record['student_id']} no existe"
                    ];
                }

                // Check if attendance already exists for this student, subject and date
                if ($this->db->exists(
                    "SELECT 1 FROM {$this->table} 
                     WHERE student_id = :student_id 
                     AND subject_id = :subject_id 
                     AND date = :date",
                    [
                        ':student_id' => $record['student_id'],
                        ':subject_id' => $data['subject_id'],
                        ':date' => $date
                    ]
                )) {
                    continue; // Skip if already exists
                }

                $sql = "INSERT INTO {$this->table} (
                    student_id, subject_id, date, status, justification
                ) VALUES (
                    :student_id, :subject_id, :date, :status, :justification
                )";

                $params = [
                    ':student_id' => $record['student_id'],
                    ':subject_id' => $data['subject_id'],
                    ':date' => $date,
                    ':status' => $record['status'],
                    ':justification' => $record['justification'] ?? null
                ];

                $id = $this->db->insert($sql, $params);
                $insertedIds[] = $id;

                // Create notification for absent students
                if ($record['status'] === 'absent') {
                    $this->createAbsenceNotification($record['student_id'], $data['subject_id'], $date);
                }
            }

            // Commit transaction
            $this->db->commit();

            return [
                'error' => false,
                'message' => 'Registros de asistencia creados exitosamente',
                'data' => ['ids' => $insertedIds]
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Error al crear registros de asistencia: " . $e->getMessage());
        }
    }

    // Update attendance record
    public function updateAttendance($id, $data) {
        try {
            // Check if attendance record exists
            if (!$this->db->exists("SELECT 1 FROM {$this->table} WHERE id = :id", [':id' => $id])) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Registro de asistencia no encontrado'
                ];
            }

            // Sanitize input data
            $sanitizedData = Database::sanitize($data);

            $updateFields = [];
            $params = [':id' => $id];

            // Build dynamic update query
            foreach ($sanitizedData as $key => $value) {
                if ($key !== 'id' && $value !== null) {
                    if ($key === 'date') {
                        $value = Database::formatDate($value);
                    }
                    $updateFields[] = "{$key} = :{$key}";
                    $params[":{$key}"] = $value;
                }
            }

            if (empty($updateFields)) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'No hay campos para actualizar'
                ];
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $updateFields) . " WHERE id = :id";
            
            $this->db->update($sql, $params);

            return [
                'error' => false,
                'message' => 'Registro de asistencia actualizado exitosamente'
            ];
        } catch (Exception $e) {
            throw new Exception("Error al actualizar registro de asistencia: " . $e->getMessage());
        }
    }

    // Delete attendance record
    public function deleteAttendance($id) {
        try {
            // Check if attendance record exists
            if (!$this->db->exists("SELECT 1 FROM {$this->table} WHERE id = :id", [':id' => $id])) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Registro de asistencia no encontrado'
                ];
            }

            $sql = "DELETE FROM {$this->table} WHERE id = :id";
            $this->db->delete($sql, [':id' => $id]);

            return [
                'error' => false,
                'message' => 'Registro de asistencia eliminado exitosamente'
            ];
        } catch (Exception $e) {
            throw new Exception("Error al eliminar registro de asistencia: " . $e->getMessage());
        }
    }

    // Get attendance statistics
    public function getAttendanceStats($filters = []) {
        try {
            // Base statistics query
            $sql = "SELECT 
                    COUNT(*) as total_sessions,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
                    SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count,
                    COUNT(DISTINCT student_id) as total_students,
                    COUNT(DISTINCT date) as total_days
                   FROM {$this->table}
                   WHERE 1=1";
            
            $params = [];

            // Apply filters
            if (!empty($filters['subject_id'])) {
                $sql .= " AND subject_id = :subject_id";
                $params[':subject_id'] = $filters['subject_id'];
            }

            if (!empty($filters['student_id'])) {
                $sql .= " AND student_id = :student_id";
                $params[':student_id'] = $filters['student_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            $stats = $this->db->getOne($sql, $params);

            // Calculate percentages
            $total = $stats['total_sessions'] ?: 1; // Avoid division by zero
            $stats['present_percentage'] = round(($stats['present_count'] / $total) * 100, 2);
            $stats['absent_percentage'] = round(($stats['absent_count'] / $total) * 100, 2);
            $stats['late_percentage'] = round(($stats['late_count'] / $total) * 100, 2);
            $stats['excused_percentage'] = round(($stats['excused_count'] / $total) * 100, 2);

            // Get monthly trends
            $trendSQL = "SELECT 
                        DATE_FORMAT(date, '%Y-%m') as month,
                        COUNT(*) as total_sessions,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
                       FROM {$this->table}
                       WHERE 1=1";

            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    $trendSQL .= " AND " . substr($key, 1) . " = " . $key;
                }
            }

            $trendSQL .= " GROUP BY DATE_FORMAT(date, '%Y-%m')
                          ORDER BY month ASC";

            $trends = $this->db->getAll($trendSQL, $params);

            return [
                'error' => false,
                'message' => 'Estadísticas recuperadas exitosamente',
                'data' => [
                    'summary' => $stats,
                    'trends' => $trends
                ]
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar estadísticas: " . $e->getMessage());
        }
    }

    // Create absence notification
    private function createAbsenceNotification($studentId, $subjectId, $date) {
        try {
            // Get subject name
            $subject = $this->db->getOne(
                "SELECT name FROM subjects WHERE id = :id",
                [':id' => $subjectId]
            );

            // Format date for display
            $formattedDate = date('d/m/Y', strtotime($date));

            // Create notification
            $sql = "INSERT INTO notifications (
                recipient_type, recipient_id, title, message, type
            ) VALUES (
                'student', :student_id, :title, :message, 'attendance'
            )";

            $params = [
                ':student_id' => $studentId,
                ':title' => "Ausencia Registrada",
                ':message' => "Se ha registrado una ausencia en la asignatura {$subject['name']} el día {$formattedDate}"
            ];

            $this->db->insert($sql, $params);
        } catch (Exception $e) {
            // Log error but don't stop execution
            error_log("Error creating absence notification: " . $e->getMessage());
        }
    }
}

// Handle API requests
try {
    $api = new AttendanceAPI();
    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $data = json_decode(file_get_contents('php://input'), true);

    switch ($method) {
        case 'GET':
            if ($id) {
                $response = $api->getAttendanceById($id);
            } else if (isset($_GET['stats'])) {
                $filters = [
                    'subject_id' => $_GET['subject_id'] ?? null,
                    'student_id' => $_GET['student_id'] ?? null,
                    'date_from' => $_GET['date_from'] ?? null,
                    'date_to' => $_GET['date_to'] ?? null
                ];
                $response = $api->getAttendanceStats($filters);
            } else {
                $filters = [
                    'student_id' => $_GET['student_id'] ?? null,
                    'subject_id' => $_GET['subject_id'] ?? null,
                    'date_from' => $_GET['date_from'] ?? null,
                    'date_to' => $_GET['date_to'] ?? null,
                    'status' => $_GET['status'] ?? null
                ];
                $response = $api->getAttendance($filters);
            }
            break;

        case 'POST':
            $response = $api->createAttendance($data);
            http_response_code(201);
            break;

        case 'PUT':
            if (!$id) {
                throw new Exception("ID de asistencia no proporcionado");
            }
            $response = $api->updateAttendance($id, $data);
            break;

        case 'DELETE':
            if (!$id) {
                throw new Exception("ID de asistencia no proporcionado");
            }
            $response = $api->deleteAttendance($id);
            break;

        default:
            http_response_code(405);
            $response = [
                'error' => true,
                'message' => 'Método no permitido'
            ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    handleError($e);
}
?>
