<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'config/database.php';

function testDatabaseConnection() {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $results = [
            'database_connection' => [
                'status' => 'success',
                'message' => 'Conexión a la base de datos establecida correctamente'
            ]
        ];

        // Test each table
        $tables = ['students', 'subjects', 'grades', 'attendance', 'notifications', 'teachers', 'academic_calendar'];
        foreach ($tables as $table) {
            try {
                $stmt = $conn->query("SELECT 1 FROM {$table} LIMIT 1");
                $results[$table] = [
                    'status' => 'success',
                    'message' => "Tabla '{$table}' accesible"
                ];
            } catch (PDOException $e) {
                $results[$table] = [
                    'status' => 'error',
                    'message' => "Error al acceder a la tabla '{$table}': " . $e->getMessage()
                ];
            }
        }

        return $results;
    } catch (Exception $e) {
        return [
            'database_connection' => [
                'status' => 'error',
                'message' => 'Error de conexión: ' . $e->getMessage()
            ]
        ];
    }
}

function testDirectoryPermissions() {
    $directories = [
        '/' => 'Directorio raíz',
        '/api' => 'Directorio API',
        '/config' => 'Directorio de configuración',
        '/css' => 'Directorio CSS',
        '/js' => 'Directorio JavaScript',
        '/pages' => 'Directorio de páginas'
    ];

    $results = [];
    foreach ($directories as $dir => $description) {
        $fullPath = __DIR__ . $dir;
        $results[$dir] = [
            'path' => $fullPath,
            'readable' => is_readable($fullPath),
            'writable' => is_writable($fullPath),
            'description' => $description
        ];
    }

    return $results;
}

function testPHPConfiguration() {
    return [
        'php_version' => PHP_VERSION,
        'display_errors' => ini_get('display_errors'),
        'error_reporting' => ini_get('error_reporting'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'pdo_drivers' => PDO::getAvailableDrivers()
    ];
}

// Run tests
$dbResults = testDatabaseConnection();
$dirResults = testDirectoryPermissions();
$phpConfig = testPHPConfiguration();

// Determine overall status
$overallStatus = !in_array('error', array_column(array_column($dbResults, 'status'), 0));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Conexión - Sistema de Gestión Académica</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gray-50 font-[Inter]">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Test de Conexión y Configuración</h1>
            <p class="text-gray-600">Resultados del diagnóstico del sistema</p>
        </div>

        <!-- Overall Status -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Estado General</h2>
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <?php if ($overallStatus): ?>
                            <i class="fas fa-check-circle text-4xl text-green-500"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-circle text-4xl text-red-500"></i>
                        <?php endif; ?>
                    </div>
                    <div class="ml-4">
                        <p class="text-lg font-medium text-gray-900">
                            <?php echo $overallStatus ? 'Sistema funcionando correctamente' : 'Se encontraron problemas'; ?>
                        </p>
                        <p class="text-gray-600">
                            <?php echo date('Y-m-d H:i:s'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Results -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Estado de la Base de Datos</h2>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="divide-y divide-gray-200">
                    <?php foreach ($dbResults as $key => $result): ?>
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <?php if ($result['status'] === 'success'): ?>
                                        <i class="fas fa-check-circle text-green-500"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle text-red-500"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900"><?php echo ucfirst($key); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo $result['message']; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Directory Permissions -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Permisos de Directorios</h2>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Directorio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lectura</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Escritura</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($dirResults as $dir => $info): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo $info['description']; ?></div>
                                    <div class="text-sm text-gray-500"><?php echo $dir; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($info['readable']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i> Sí
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-times mr-1"></i> No
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($info['writable']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i> Sí
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-times mr-1"></i> No
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PHP Configuration -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Configuración PHP</h2>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6">
                    <?php foreach ($phpConfig as $key => $value): ?>
                        <div class="bg-gray-50 rounded p-4">
                            <div class="text-sm font-medium text-gray-500"><?php echo ucwords(str_replace('_', ' ', $key)); ?></div>
                            <div class="mt-1 text-sm text-gray-900">
                                <?php 
                                if (is_array($value)) {
                                    echo implode(', ', $value);
                                } else {
                                    echo $value;
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-between">
            <a href="index.html" class="btn-primary">
                <i class="fas fa-home mr-2"></i>
                Ir al Dashboard
            </a>
            <button onclick="window.location.reload()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i>
                Volver a Probar
            </button>
        </div>
    </div>
</body>
</html>
