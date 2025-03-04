<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

class SubjectAPI {
    private $db;
    private $table = "subjects";

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get all subjects with optional filters and teacher information
    public function getSubjects($filters = []) {
        try {
            $sql = "SELECT s.*, 
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                    t.email as teacher_email
                   FROM {$this->table} s
                   LEFT JOIN teachers t ON s.teacher_id = t.id
                   WHERE 1=1";
            
            $params = [];

            // Apply filters
            if (!empty($filters['academic_year'])) {
                $sql .= " AND s.academic_year = :academic_year";
                $params[':academic_year'] = $filters['academic_year'];
            }

            if (!empty($filters['teacher_id'])) {
                $sql .= " AND s.teacher_id = :teacher_id";
                $params[':teacher_id'] = $filters['teacher_id'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (s.name LIKE :search OR s.subject_code LIKE :search)";
                $params[':search'] = "%{$filters['search']}%";
            }

            $sql .= " ORDER BY s.name ASC";

            $subjects = $this->db->getAll($sql, $params);

            return [
                'error' => false,
                'message' => 'Asignaturas recuperadas exitosamente',
                'data' => $subjects
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar asignaturas: " . $e->getMessage());
        }
    }

    // Get a single subject with teacher information
    public function getSubject($id) {
        try {
            $sql = "SELECT s.*, 
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                    t.email as teacher_email
                   FROM {$this->table} s
                   LEFT JOIN teachers t ON s.teacher_id = t.id
                   WHERE s.id = :id";

            $subject = $this->db->getOne($sql, [':id' => $id]);

            if (!$subject) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Asignatura no encontrada'
                ];
            }

            // Get additional statistics
            $statsSQL = "SELECT 
                COUNT(DISTINCT student_id) as total_students,
                AVG(grade) as average_grade
                FROM grades 
                WHERE subject_id = :id";
            
            $stats = $this->db->getOne($statsSQL, [':id' => $id]);
            $subject['statistics'] = $stats;

            return [
                'error' => false,
                'message' => 'Asignatura recuperada exitosamente',
                'data' => $subject
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar asignatura: " . $e->getMessage());
        }
    }

    // Create a new subject
    public function createSubject($data) {
        try {
            // Validate required fields
            $requiredFields = ['subject_code', 'name', 'credits', 'academic_year'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    return [
                        'error' => true,
                        'message' => "El campo {$field} es requerido"
                    ];
                }
            }

            // Check if subject code already exists
            if ($this->db->exists("SELECT 1 FROM {$this->table} WHERE subject_code = :code", [':code' => $data['subject_code']])) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'El código de asignatura ya existe'
                ];
            }

            // Validate teacher exists if provided
            if (!empty($data['teacher_id'])) {
                if (!$this->db->exists("SELECT 1 FROM teachers WHERE id = :id", [':id' => $data['teacher_id']])) {
                    http_response_code(400);
                    return [
                        'error' => true,
                        'message' => 'El profesor especificado no existe'
                    ];
                }
            }

            // Sanitize input data
            $sanitizedData = Database::sanitize($data);

            $sql = "INSERT INTO {$this->table} (
                subject_code, name, description, teacher_id, 
                credits, schedule, academic_year
            ) VALUES (
                :subject_code, :name, :description, :teacher_id,
                :credits, :schedule, :academic_year
            )";

            $params = [
                ':subject_code' => $sanitizedData['subject_code'],
                ':name' => $sanitizedData['name'],
                ':description' => $sanitizedData['description'] ?? null,
                ':teacher_id' => $sanitizedData['teacher_id'] ?? null,
                ':credits' => $sanitizedData['credits'],
                ':schedule' => $sanitizedData['schedule'] ?? null,
                ':academic_year' => $sanitizedData['academic_year']
            ];

            $id = $this->db->insert($sql, $params);

            return [
                'error' => false,
                'message' => 'Asignatura creada exitosamente',
                'data' => ['id' => $id]
            ];
        } catch (Exception $e) {
            throw new Exception("Error al crear asignatura: " . $e->getMessage());
        }
    }

    // Update an existing subject
    public function updateSubject($id, $data) {
        try {
            // Check if subject exists
            if (!$this->db->exists("SELECT 1 FROM {$this->table} WHERE id = :id", [':id' => $id])) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Asignatura no encontrada'
                ];
            }

            // Validate teacher exists if provided
            if (!empty($data['teacher_id'])) {
                if (!$this->db->exists("SELECT 1 FROM teachers WHERE id = :id", [':id' => $data['teacher_id']])) {
                    http_response_code(400);
                    return [
                        'error' => true,
                        'message' => 'El profesor especificado no existe'
                    ];
                }
            }

            // Sanitize input data
            $sanitizedData = Database::sanitize($data);

            $updateFields = [];
            $params = [':id' => $id];

            // Build dynamic update query
            foreach ($sanitizedData as $key => $value) {
                if ($key !== 'id' && $value !== null) {
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
                'message' => 'Asignatura actualizada exitosamente'
            ];
        } catch (Exception $e) {
            throw new Exception("Error al actualizar asignatura: " . $e->getMessage());
        }
    }

    // Delete a subject
    public function deleteSubject($id) {
        try {
            // Check if subject exists
            if (!$this->db->exists("SELECT 1 FROM {$this->table} WHERE id = :id", [':id' => $id])) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Asignatura no encontrada'
                ];
            }

            // Start transaction
            $this->db->beginTransaction();

            // Delete related records (grades and attendance)
            $this->db->delete("DELETE FROM grades WHERE subject_id = :id", [':id' => $id]);
            $this->db->delete("DELETE FROM attendance WHERE subject_id = :id", [':id' => $id]);

            // Delete the subject
            $this->db->delete("DELETE FROM {$this->table} WHERE id = :id", [':id' => $id]);

            // Commit transaction
            $this->db->commit();

            return [
                'error' => false,
                'message' => 'Asignatura eliminada exitosamente'
            ];
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->rollback();
            throw new Exception("Error al eliminar asignatura: " . $e->getMessage());
        }
    }

    // Get subject statistics
    public function getSubjectStats($id) {
        try {
            // Get general statistics
            $statsSQL = "SELECT 
                COUNT(DISTINCT g.student_id) as total_students,
                AVG(g.grade) as average_grade,
                MIN(g.grade) as lowest_grade,
                MAX(g.grade) as highest_grade,
                COUNT(g.id) as total_evaluations
            FROM {$this->table} s
            LEFT JOIN grades g ON s.id = g.subject_id
            WHERE s.id = :id";

            $stats = $this->db->getOne($statsSQL, [':id' => $id]);

            // Get attendance statistics
            $attendanceSQL = "SELECT 
                COUNT(*) as total_sessions,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
            FROM attendance
            WHERE subject_id = :id";

            $attendance = $this->db->getOne($attendanceSQL, [':id' => $id]);

            // Calculate grade distribution
            $distributionSQL = "SELECT 
                CASE 
                    WHEN grade >= 9 THEN 'Excelente'
                    WHEN grade >= 7 THEN 'Bueno'
                    WHEN grade >= 5 THEN 'Regular'
                    ELSE 'Insuficiente'
                END as range,
                COUNT(*) as count
            FROM grades 
            WHERE subject_id = :id
            GROUP BY range";

            $distribution = $this->db->getAll($distributionSQL, [':id' => $id]);

            return [
                'error' => false,
                'message' => 'Estadísticas recuperadas exitosamente',
                'data' => [
                    'general' => $stats,
                    'attendance' => $attendance,
                    'grade_distribution' => $distribution
                ]
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar estadísticas: " . $e->getMessage());
        }
    }
}

// Handle API requests
try {
    $api = new SubjectAPI();
    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $data = json_decode(file_get_contents('php://input'), true);

    switch ($method) {
        case 'GET':
            if ($id) {
                if (isset($_GET['stats'])) {
                    $response = $api->getSubjectStats($id);
                } else {
                    $response = $api->getSubject($id);
                }
            } else {
                $filters = [
                    'academic_year' => $_GET['academic_year'] ?? null,
                    'teacher_id' => $_GET['teacher_id'] ?? null,
                    'search' => $_GET['search'] ?? null
                ];
                $response = $api->getSubjects($filters);
            }
            break;

        case 'POST':
            $response = $api->createSubject($data);
            http_response_code(201);
            break;

        case 'PUT':
            if (!$id) {
                throw new Exception("ID de asignatura no proporcionado");
            }
            $response = $api->updateSubject($id, $data);
            break;

        case 'DELETE':
            if (!$id) {
                throw new Exception("ID de asignatura no proporcionado");
            }
            $response = $api->deleteSubject($id);
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
