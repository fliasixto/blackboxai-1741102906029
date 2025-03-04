<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

class NotificationAPI {
    private $db;
    private $table = "notifications";

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get notifications with optional filters
    public function getNotifications($filters = []) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            // Apply filters
            if (!empty($filters['recipient_type'])) {
                $sql .= " AND recipient_type = :recipient_type";
                $params[':recipient_type'] = $filters['recipient_type'];
            }

            if (!empty($filters['recipient_id'])) {
                $sql .= " AND recipient_id = :recipient_id";
                $params[':recipient_id'] = $filters['recipient_id'];
            }

            if (!empty($filters['type'])) {
                $sql .= " AND type = :type";
                $params[':type'] = $filters['type'];
            }

            if (isset($filters['is_read'])) {
                $sql .= " AND is_read = :is_read";
                $params[':is_read'] = $filters['is_read'];
            }

            // Add date range filter if provided
            if (!empty($filters['date_from'])) {
                $sql .= " AND created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            $sql .= " ORDER BY created_at DESC";

            // Add pagination if requested
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT :limit";
                $params[':limit'] = (int)$filters['limit'];

                if (!empty($filters['offset'])) {
                    $sql .= " OFFSET :offset";
                    $params[':offset'] = (int)$filters['offset'];
                }
            }

            $notifications = $this->db->getAll($sql, $params);

            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
            $countParams = $params;
            unset($countParams[':limit'], $countParams[':offset']);
            
            foreach ($filters as $key => $value) {
                if (in_array($key, ['recipient_type', 'recipient_id', 'type', 'is_read'])) {
                    $countSql .= " AND $key = :$key";
                }
            }
            
            $totalCount = $this->db->getOne($countSql, $countParams)['total'];

            return [
                'error' => false,
                'message' => 'Notificaciones recuperadas exitosamente',
                'data' => [
                    'notifications' => $notifications,
                    'total' => $totalCount
                ]
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar notificaciones: " . $e->getMessage());
        }
    }

    // Get a single notification
    public function getNotification($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = :id";
            $notification = $this->db->getOne($sql, [':id' => $id]);

            if (!$notification) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Notificación no encontrada'
                ];
            }

            return [
                'error' => false,
                'message' => 'Notificación recuperada exitosamente',
                'data' => $notification
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar notificación: " . $e->getMessage());
        }
    }

    // Create a new notification
    public function createNotification($data) {
        try {
            // Validate required fields
            $requiredFields = ['recipient_type', 'recipient_id', 'title', 'message', 'type'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    return [
                        'error' => true,
                        'message' => "El campo {$field} es requerido"
                    ];
                }
            }

            // Validate recipient type
            if (!in_array($data['recipient_type'], ['student', 'teacher', 'parent'])) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'Tipo de destinatario inválido'
                ];
            }

            // Validate notification type
            if (!in_array($data['type'], ['grade', 'attendance', 'general', 'alert'])) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'Tipo de notificación inválido'
                ];
            }

            // Sanitize input data
            $sanitizedData = Database::sanitize($data);

            $sql = "INSERT INTO {$this->table} (
                recipient_type, recipient_id, title, message, type
            ) VALUES (
                :recipient_type, :recipient_id, :title, :message, :type
            )";

            $params = [
                ':recipient_type' => $sanitizedData['recipient_type'],
                ':recipient_id' => $sanitizedData['recipient_id'],
                ':title' => $sanitizedData['title'],
                ':message' => $sanitizedData['message'],
                ':type' => $sanitizedData['type']
            ];

            $id = $this->db->insert($sql, $params);

            return [
                'error' => false,
                'message' => 'Notificación creada exitosamente',
                'data' => ['id' => $id]
            ];
        } catch (Exception $e) {
            throw new Exception("Error al crear notificación: " . $e->getMessage());
        }
    }

    // Mark notification as read
    public function markAsRead($id) {
        try {
            // Check if notification exists
            if (!$this->db->exists("SELECT 1 FROM {$this->table} WHERE id = :id", [':id' => $id])) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Notificación no encontrada'
                ];
            }

            $sql = "UPDATE {$this->table} SET is_read = true WHERE id = :id";
            $this->db->update($sql, [':id' => $id]);

            return [
                'error' => false,
                'message' => 'Notificación marcada como leída exitosamente'
            ];
        } catch (Exception $e) {
            throw new Exception("Error al marcar notificación como leída: " . $e->getMessage());
        }
    }

    // Mark all notifications as read for a recipient
    public function markAllAsRead($recipientType, $recipientId) {
        try {
            $sql = "UPDATE {$this->table} 
                   SET is_read = true 
                   WHERE recipient_type = :recipient_type 
                   AND recipient_id = :recipient_id";

            $params = [
                ':recipient_type' => $recipientType,
                ':recipient_id' => $recipientId
            ];

            $this->db->update($sql, $params);

            return [
                'error' => false,
                'message' => 'Todas las notificaciones marcadas como leídas exitosamente'
            ];
        } catch (Exception $e) {
            throw new Exception("Error al marcar notificaciones como leídas: " . $e->getMessage());
        }
    }

    // Delete a notification
    public function deleteNotification($id) {
        try {
            // Check if notification exists
            if (!$this->db->exists("SELECT 1 FROM {$this->table} WHERE id = :id", [':id' => $id])) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Notificación no encontrada'
                ];
            }

            $sql = "DELETE FROM {$this->table} WHERE id = :id";
            $this->db->delete($sql, [':id' => $id]);

            return [
                'error' => false,
                'message' => 'Notificación eliminada exitosamente'
            ];
        } catch (Exception $e) {
            throw new Exception("Error al eliminar notificación: " . $e->getMessage());
        }
    }

    // Get notification statistics
    public function getNotificationStats($recipientType = null, $recipientId = null) {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_notifications,
                    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count,
                    type,
                    COUNT(*) as type_count
                   FROM {$this->table}
                   WHERE 1=1";
            
            $params = [];

            if ($recipientType) {
                $sql .= " AND recipient_type = :recipient_type";
                $params[':recipient_type'] = $recipientType;
            }

            if ($recipientId) {
                $sql .= " AND recipient_id = :recipient_id";
                $params[':recipient_id'] = $recipientId;
            }

            $sql .= " GROUP BY type";

            $stats = $this->db->getAll($sql, $params);

            return [
                'error' => false,
                'message' => 'Estadísticas recuperadas exitosamente',
                'data' => $stats
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar estadísticas: " . $e->getMessage());
        }
    }
}

// Handle API requests
try {
    $api = new NotificationAPI();
    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $data = json_decode(file_get_contents('php://input'), true);

    switch ($method) {
        case 'GET':
            if ($id) {
                $response = $api->getNotification($id);
            } else if (isset($_GET['stats'])) {
                $response = $api->getNotificationStats(
                    $_GET['recipient_type'] ?? null,
                    $_GET['recipient_id'] ?? null
                );
            } else {
                $filters = [
                    'recipient_type' => $_GET['recipient_type'] ?? null,
                    'recipient_id' => $_GET['recipient_id'] ?? null,
                    'type' => $_GET['type'] ?? null,
                    'is_read' => isset($_GET['is_read']) ? (bool)$_GET['is_read'] : null,
                    'date_from' => $_GET['date_from'] ?? null,
                    'date_to' => $_GET['date_to'] ?? null,
                    'limit' => $_GET['limit'] ?? null,
                    'offset' => $_GET['offset'] ?? null
                ];
                $response = $api->getNotifications($filters);
            }
            break;

        case 'POST':
            if (isset($_GET['mark_all_read'])) {
                if (empty($_GET['recipient_type']) || empty($_GET['recipient_id'])) {
                    throw new Exception("Tipo y ID de destinatario son requeridos");
                }
                $response = $api->markAllAsRead($_GET['recipient_type'], $_GET['recipient_id']);
            } else if (isset($_GET['mark_read'])) {
                if (!$id) {
                    throw new Exception("ID de notificación no proporcionado");
                }
                $response = $api->markAsRead($id);
            } else {
                $response = $api->createNotification($data);
                http_response_code(201);
            }
            break;

        case 'DELETE':
            if (!$id) {
                throw new Exception("ID de notificación no proporcionado");
            }
            $response = $api->deleteNotification($id);
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
