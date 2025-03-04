// API Base URL
const API_BASE_URL = '/api';

// API Endpoints object
const API_ENDPOINTS = {
    students: `${API_BASE_URL}/students.php`,
    subjects: `${API_BASE_URL}/subjects.php`,
    grades: `${API_BASE_URL}/grades.php`,
    attendance: `${API_BASE_URL}/attendance.php`,
    notifications: `${API_BASE_URL}/notifications.php`,
    export: `${API_BASE_URL}/export.php`
};

// Generic API call function
async function apiCall(endpoint, method = 'GET', data = null, params = {}) {
    try {
        // Construct URL with query parameters
        const url = new URL(endpoint, window.location.origin);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.append(key, params[key]);
            }
        });

        // Configure request options
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        // Add body for POST/PUT requests
        if (data && ['POST', 'PUT'].includes(method)) {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Error en la solicitud');
        }

        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// Students API
const studentsAPI = {
    // Get all students with optional filters
    getAll: (filters = {}) => apiCall(API_ENDPOINTS.students, 'GET', null, filters),
    
    // Get a single student by ID
    getById: (id) => apiCall(API_ENDPOINTS.students, 'GET', null, { id }),
    
    // Create a new student
    create: (data) => apiCall(API_ENDPOINTS.students, 'POST', data),
    
    // Update a student
    update: (id, data) => apiCall(API_ENDPOINTS.students, 'PUT', data, { id }),
    
    // Delete a student
    delete: (id) => apiCall(API_ENDPOINTS.students, 'DELETE', null, { id }),
    
    // Get student statistics
    getStats: (id) => apiCall(API_ENDPOINTS.students, 'GET', null, { id, stats: true })
};

// Subjects API
const subjectsAPI = {
    // Get all subjects with optional filters
    getAll: (filters = {}) => apiCall(API_ENDPOINTS.subjects, 'GET', null, filters),
    
    // Get a single subject by ID
    getById: (id) => apiCall(API_ENDPOINTS.subjects, 'GET', null, { id }),
    
    // Create a new subject
    create: (data) => apiCall(API_ENDPOINTS.subjects, 'POST', data),
    
    // Update a subject
    update: (id, data) => apiCall(API_ENDPOINTS.subjects, 'PUT', data, { id }),
    
    // Delete a subject
    delete: (id) => apiCall(API_ENDPOINTS.subjects, 'DELETE', null, { id }),
    
    // Get subject statistics
    getStats: (id) => apiCall(API_ENDPOINTS.subjects, 'GET', null, { id, stats: true })
};

// Grades API
const gradesAPI = {
    // Get all grades with optional filters
    getAll: (filters = {}) => apiCall(API_ENDPOINTS.grades, 'GET', null, filters),
    
    // Get a single grade by ID
    getById: (id) => apiCall(API_ENDPOINTS.grades, 'GET', null, { id }),
    
    // Create a new grade
    create: (data) => apiCall(API_ENDPOINTS.grades, 'POST', data),
    
    // Update a grade
    update: (id, data) => apiCall(API_ENDPOINTS.grades, 'PUT', data, { id }),
    
    // Delete a grade
    delete: (id) => apiCall(API_ENDPOINTS.grades, 'DELETE', null, { id }),
    
    // Get grade statistics
    getStats: (filters = {}) => apiCall(API_ENDPOINTS.grades, 'GET', null, { ...filters, stats: true })
};

// Attendance API
const attendanceAPI = {
    // Get all attendance records with optional filters
    getAll: (filters = {}) => apiCall(API_ENDPOINTS.attendance, 'GET', null, filters),
    
    // Get a single attendance record by ID
    getById: (id) => apiCall(API_ENDPOINTS.attendance, 'GET', null, { id }),
    
    // Create new attendance records (bulk)
    create: (data) => apiCall(API_ENDPOINTS.attendance, 'POST', data),
    
    // Update an attendance record
    update: (id, data) => apiCall(API_ENDPOINTS.attendance, 'PUT', data, { id }),
    
    // Delete an attendance record
    delete: (id) => apiCall(API_ENDPOINTS.attendance, 'DELETE', null, { id }),
    
    // Get attendance statistics
    getStats: (filters = {}) => apiCall(API_ENDPOINTS.attendance, 'GET', null, { ...filters, stats: true })
};

// Notifications API
const notificationsAPI = {
    // Get all notifications with optional filters
    getAll: (filters = {}) => apiCall(API_ENDPOINTS.notifications, 'GET', null, filters),
    
    // Get a single notification by ID
    getById: (id) => apiCall(API_ENDPOINTS.notifications, 'GET', null, { id }),
    
    // Create a new notification
    create: (data) => apiCall(API_ENDPOINTS.notifications, 'POST', data),
    
    // Mark a notification as read
    markAsRead: (id) => apiCall(API_ENDPOINTS.notifications, 'POST', null, { id, mark_read: true }),
    
    // Mark all notifications as read for a recipient
    markAllAsRead: (recipientType, recipientId) => 
        apiCall(API_ENDPOINTS.notifications, 'POST', null, { 
            mark_all_read: true, 
            recipient_type: recipientType, 
            recipient_id: recipientId 
        }),
    
    // Delete a notification
    delete: (id) => apiCall(API_ENDPOINTS.notifications, 'DELETE', null, { id }),
    
    // Get notification statistics
    getStats: (recipientType, recipientId) => 
        apiCall(API_ENDPOINTS.notifications, 'GET', null, { 
            stats: true, 
            recipient_type: recipientType, 
            recipient_id: recipientId 
        })
};

// Chart utilities
const chartUtils = {
    // Create options for Chart.js
    createChartOptions: (title, yAxisMin = 0, yAxisMax = 100) => ({
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: title,
                font: {
                    size: 16,
                    weight: 'bold'
                }
            },
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                min: yAxisMin,
                max: yAxisMax,
                ticks: {
                    stepSize: (yAxisMax - yAxisMin) / 10
                }
            }
        }
    }),

    // Create dataset configuration
    createDataset: (label, data, color) => ({
        label,
        data,
        backgroundColor: `rgba(${color}, 0.2)`,
        borderColor: `rgba(${color}, 1)`,
        borderWidth: 1
    })
};

// Export all APIs and utilities
// Export API
const exportAPI = {
    // Export grades report
    exportGrades: (format = 'pdf', filters = {}) => {
        const queryParams = new URLSearchParams({
            type: 'grades',
            format,
            ...filters
        });
        window.location.href = `${API_ENDPOINTS.export}?${queryParams}`;
    },
    
    // Export attendance report
    exportAttendance: (format = 'pdf', filters = {}) => {
        const queryParams = new URLSearchParams({
            type: 'attendance',
            format,
            ...filters
        });
        window.location.href = `${API_ENDPOINTS.export}?${queryParams}`;
    }
};

export {
    studentsAPI,
    subjectsAPI,
    gradesAPI,
    attendanceAPI,
    notificationsAPI,
    exportAPI,
    chartUtils
};
