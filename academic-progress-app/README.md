# Sistema de Gestión Académica

Un sistema completo de gestión académica para el seguimiento del progreso de los estudiantes, calificaciones y asistencia.

## Características

- Dashboard interactivo con estadísticas y gráficos
- Gestión de alumnos
- Gestión de asignaturas
- Control de calificaciones
- Control de asistencia
- Sistema de notificaciones
- Reportes y estadísticas
- Interfaz responsiva y moderna
- Filtros y búsqueda avanzada

## Tecnologías Utilizadas

- Frontend:
  - HTML5
  - Tailwind CSS
  - JavaScript (ES6+)
  - Chart.js para visualización de datos
  - Font Awesome para iconos
  - Google Fonts

- Backend:
  - PHP 7.4+
  - MySQL/MariaDB
  - PDO para conexión a base de datos

## Requisitos del Sistema

- Servidor web (Apache/Nginx)
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Navegador web moderno

## Instalación

1. Clonar el repositorio:
```bash
git clone https://github.com/tu-usuario/academic-progress-app.git
cd academic-progress-app
```

2. Configurar la base de datos:
```bash
# Importar el esquema de la base de datos
mysql -u root -p < database/schema.sql
```

3. Configurar la conexión a la base de datos:
   - Abrir `config/database.php`
   - Modificar las credenciales de conexión según tu entorno:
```php
private $host = "localhost";
private $db_name = "academic_progress_db";
private $username = "tu_usuario";
private $password = "tu_contraseña";
```

4. Configurar el servidor web:
   - Asegurarse de que el directorio del proyecto sea accesible por el servidor web
   - Configurar los permisos adecuados:
```bash
chmod 755 -R academic-progress-app
chmod 777 -R academic-progress-app/uploads  # Si se implementa carga de archivos
```

## Estructura del Proyecto

```
academic-progress-app/
├── api/                    # Endpoints de la API
│   ├── students.php
│   ├── subjects.php
│   ├── grades.php
│   ├── attendance.php
│   └── notifications.php
├── config/                 # Configuración
│   └── database.php
├── css/                    # Estilos
│   └── styles.css
├── database/              # Esquema de base de datos
│   └── schema.sql
├── js/                    # JavaScript
│   ├── api.js
│   └── ui.js
├── pages/                 # Páginas de la aplicación
│   ├── students.html
│   ├── subjects.html
│   ├── grades.html
│   └── attendance.html
├── index.html            # Página principal/Dashboard
└── README.md
```

## Uso

1. Acceder al sistema:
   - Abrir el navegador y visitar `http://localhost/academic-progress-app`
   - La página principal mostrará el dashboard con estadísticas generales

2. Gestión de Alumnos:
   - Agregar, editar y eliminar información de estudiantes
   - Ver estadísticas individuales
   - Filtrar por grado y buscar por nombre/código

3. Gestión de Asignaturas:
   - Administrar materias y sus profesores asignados
   - Ver estadísticas por asignatura
   - Asignar horarios y créditos

4. Control de Calificaciones:
   - Registrar calificaciones por tipo de evaluación
   - Ver promedios y tendencias
   - Generar reportes de rendimiento

5. Control de Asistencia:
   - Tomar asistencia por asignatura
   - Ver estadísticas de asistencia
   - Justificar ausencias

## Características de la Interfaz

- Diseño responsivo que se adapta a diferentes dispositivos
- Tema claro y profesional
- Navegación intuitiva
- Gráficos interactivos
- Notificaciones en tiempo real
- Filtros y búsqueda avanzada
- Tablas ordenables y paginadas
- Formularios validados
- Mensajes de confirmación y error
- Animaciones suaves

## Seguridad

- Validación de datos en frontend y backend
- Sanitización de entradas
- Prevención de SQL injection mediante PDO
- Manejo seguro de sesiones
- Escape de salida HTML

## Mantenimiento

- Mantener actualizado PHP y MySQL
- Realizar backups regulares de la base de datos
- Monitorear los logs de error
- Actualizar las dependencias cuando sea necesario

## Contribuir

1. Fork el repositorio
2. Crear una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## Licencia

Este proyecto está licenciado bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

## Soporte

Para soporte, enviar un email a soporte@tudominio.com o abrir un issue en el repositorio.

## Créditos

- Tailwind CSS - Framework CSS
- Chart.js - Librería de gráficos
- Font Awesome - Iconos
- Google Fonts - Tipografía
