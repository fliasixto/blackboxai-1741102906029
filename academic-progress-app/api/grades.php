<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

class GradeAPI {
    private $db;
    private $table = "grades";

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get grades with optional filters
    public function getGrades($filters = []) {
        try {
            $sql = "SELECT g.*,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    s.student_code,
                    sub.name as subject_name,
                    sub.subject_code
                   FROM {$this->table} g
                   JOIN students s ON g.student_id = s.id
                   JOIN subjects sub ON g.subject_id = sub.id
                   WHERE 1=1";
            
            $params = [];

            // Apply filters
            if (!empty($filters['student_id'])) {
                $sql .= " AND g.student_id = :student_id";
                $params[':student_id'] = $filters['student_id'];
            }

            if (!empty($filters['subject_id'])) {
                $sql .= " AND g.subject_id = :subject_id";
                $params[':subject_id'] = $filters['subject_id'];
            }

            if (!empty($filters['evaluation_type'])) {
                $sql .= " AND g.evaluation_type = :evaluation_type";
                $params[':evaluation_type'] = $filters['evaluation_type'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND g.evaluation_date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND g.evaluation_date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            $sql .= " ORDER BY g.evaluation_date DESC";

            $grades = $this->db->getAll($sql, $params);

            return [
                'error' => false,
                'message' => 'Calificaciones recuperadas exitosamente',
                'data' => $grades
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar calificaciones: " . $e->getMessage());
        }
    }

    // Get a single grade
    public function getGrade($id) {
        try {
            $sql = "SELECT g.*,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    s.student_code,
                    sub.name as subject_name,
                    sub.subject_code
                   FROM {$this->table} g
                   JOIN students s ON g.student_id = s.id
                   JOIN subjects sub ON g.subject_id = sub.id
                   WHERE g.id = :id";

            $grade = $this->db->getOne($sql, [':id' => $id]);

            if (!$grade) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Calificación no encontrada'
                ];
            }

            return [
                'error' => false,
                'message' => 'Calificación recuperada exitosamente',
                'data' => $grade
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar calificación: " . $e->getMessage());
        }
    }

    // Create a new grade
    public function createGrade($data) {
        try {
            // Validate required fields
            $requiredFields = ['student_id', 'subject_id', 'grade', 'evaluation_date', 'evaluation_type'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    http_response_code(400);
                    return [
                        'error' => true,
                        'message' => "El campo {$field} es requerido"
                    ];
                }
            }

            // Validate grade range (0-10)
            if ($data['grade'] < 0 || $data['grade'] > 10) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'La calificación debe estar entre 0 y 10'
                ];
            }

            // Validate student exists
            if (!$this->db->exists("SELECT 1 FROM students WHERE id = :id", [':id' => $data['student_id']])) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'El estudiante especificado no existe'
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

            // Sanitize input data
            $sanitizedData = Database::sanitize($data);

            $sql = "INSERT INTO {$this->table} (
                student_id, subject_id, grade, evaluation_date,
                evaluation_type, comments
            ) VALUES (
                :student_id, :subject_id, :grade, :evaluation_date,
                :evaluation_type, :comments
            )";

            $params = [
                ':student_id' => $sanitizedData['student_id'],
                ':subject_id' => $sanitizedData['subject_id'],
                ':grade' => $sanitizedData['grade'],
                ':evaluation_date' => Database::formatDate($sanitizedData['evaluation_date']),
                ':evaluation_type' => $sanitizedData['evaluation_type'],
                ':comments' => $sanitizedData['comments'] ?? null
            ];

            $id = $this->db->insert($sql, $params);

            // Create notification for the student
            $this->createGradeNotification($sanitizedData['student_id'], $sanitizedData['subject_id'], $sanitizedData['grade']);

            return [
                'error' => false,
                'message' => 'Calificación registrada exitosamente',
                'data' => ['id' => $id]
            ];
        } catch (Exception $e) {
            throw new Exception("Error al crear calificación: " . $e->getMessage());
        }
    }

    // Update an existing grade
    public function updateGrade($id, $data) {
        try {
            // Check if grade exists
            if (!$this->db->exists("SELECT 1 FROM {$this->table} WHERE id = :id", [':id' => $id])) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Calificación no encontrada'
                ];
            }

            // Validate grade range if provided
            if (isset($data['grade']) && ($data['grade'] < 0 || $data['grade'] > 10)) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'La calificación debe estar entre 0 y 10'
                ];
            }

            // Sanitize input data
            $sanitizedData = Database::sanitize($data);

            $updateFields = [];
            $params = [':id' => $id];

            // Build dynamic update query
            foreach ($sanitizedData as $key => $value) {
                if ($key !== 'id' && $value !== null) {
                    if ($key === 'evaluation_date') {
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
                'message' => 'Calificación actualizada exitosamente'
            ];
        } catch (Exception $e) {
            throw new Exception("Error al actualizar calificación: " . $e->getMessage());
        }
    }

    // Delete a grade
    public function deleteGrade($id) {
        try {
            // Check if grade exists
            if (!$this->db->exists("SELECT 1 FROM {$this->table} WHERE id = :id", [':id' => $id])) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Calificación no encontrada'
                ];
            }

            $sql = "DELETE FROM {$this->table} WHERE id = :id";
            $this->db->delete($sql, [':id' => $id]);

            return [
                'error' => false,
                'message' => 'Calificación eliminada exitosamente'
            ];
        } catch (Exception $e) {
            throw new Exception("Error al eliminar calificación: " . $e->getMessage());
        }
    }

    // Get grade statistics
    public function getGradeStats($filters = []) {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_grades,
                    AVG(grade) as average_grade,
                    MIN(grade) as lowest_grade,
                    MAX(grade) as highest_grade,
                    evaluation_type,
                    COUNT(DISTINCT student_id) as total_students
                   FROM {$this->table}
                   WHERE 1=1";
            
            $params = [];

            if (!empty($filters['subject_id'])) {
                $sql .= " AND subject_id = :subject_id";
                $params[':subject_id'] = $filters['subject_id'];
            }

            if (!empty($filters['student_id'])) {
                $sql .= " AND student_id = :student_id";
                $params[':student_id'] = $filters['student_id'];
            }

            $sql .= " GROUP BY evaluation_type";

            $stats = $this->db->getAll($sql, $params);

            // Get grade distribution
            $distributionSQL = "SELECT 
                CASE 
                    WHEN grade >= 9 THEN 'Excelente (9-10)'
                    WHEN grade >= 7 THEN 'Bueno (7-8.9)'
                    WHEN grade >= 5 THEN 'Regular (5-6.9)'
                    ELSE 'Insuficiente (0-4.9)'
                END as range,
                COUNT(*) as count
                FROM {$this->table}
                WHERE 1=1";

            if (!empty($filters['subject_id'])) {
                $distributionSQL .= " AND subject_id = :subject_id";
            }
            if (!empty($filters['student_id'])) {
                $distributionSQL .= " AND student_id = :student_id";
            }

            $distributionSQL .= " GROUP BY range";

            $distribution = $this->db->getAll($distributionSQL, $params);

            return [
                'error' => false,
                'message' => 'Estadísticas recuperadas exitosamente',
                'data' => [
                    'by_type' => $stats,
                    'distribution' => $distribution
                ]
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar estadísticas: " . $e->getMessage());
        }
    }

    // Create grade notification
    private function createGradeNotification($studentId, $subjectId, $grade) {
        try {
            // Get subject name
            $subject = $this->db->getOne(
                "SELECT name FROM subjects WHERE id = :id",
                [':id' => $subjectId]
            );

            // Create notification
            $sql = "INSERT INTO notifications (
                recipient_type, recipient_id, title, message, type
            ) VALUES (
                'student', :student_id, :title, :message, 'grade'
            )";

            $params = [
                ':student_id' => $studentId,
                ':title' => "Nueva calificación registrada",
                ':message' => "Se ha registrado una calificación de {$grade} en la asignatura {$subject['name']}"
            ];

            $this->db->insert($sql, $params);
        } catch (Exception $e) {
            // Log error but don't stop execution
            error_log("Error creating grade notification: " . $e->getMessage());
        }
    }
}

// Handle API requests
try {
    $api = new GradeAPI();
    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $data = json_decode(file_get_contents('php://input'), true);

    switch ($method) {
        case 'GET':
            if ($id) {
                $response = $api->getGrade($id);
            } else if (isset($_GET['stats'])) {
                $filters = [
                    'subject_id' => $_GET['subject_id'] ?? null,
                    'student_id' => $_GET['student_id'] ?? null
                ];
                $response = $api->getGradeStats($filters);
            } else {
                $filters = [
                    'student_id' => $_GET['student_id'] ?? null,
                    'subject_id' => $_GET['subject_id'] ?? null,
                    'evaluation_type' => $_GET['evaluation_type'] ?? null,
                    'date_from' => $_GET['date_from'] ?? null,
                    'date_to' => $_GET['date_to'] ?? null
                ];
                $response = $api->getGrades($filters);
            }
            break;

        case 'POST':
            $response = $api->createGrade($data);
            http_response_code(201);
            break;

        case 'PUT':
            if (!$id) {
                throw new Exception("ID de calificación no proporcionado");
            }
            $response = $api->updateGrade($id, $data);
            break;

        case 'DELETE':
            if (!$id) {
                throw new Exception("ID de calificación no proporcionado");
            }
            $response = $api->deleteGrade($id);
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
