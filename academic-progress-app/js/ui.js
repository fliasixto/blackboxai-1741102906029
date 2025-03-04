import { studentsAPI, subjectsAPI, gradesAPI, attendanceAPI, notificationsAPI, chartUtils } from './api.js';

class UI {
    constructor() {
        this.initializeCharts();
        this.setupEventListeners();
        this.loadDashboardData();
    }

    // Initialize Charts
    initializeCharts() {
        // Academic Progress Chart
        this.academicProgressChart = new Chart(
            document.getElementById('academicProgressChart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: ['Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    datasets: [{
                        label: 'Promedio General',
                        data: [],
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: chartUtils.createChartOptions('Progreso Académico', 4, 10)
            }
        );

        // Attendance Chart
        this.attendanceChart = new Chart(
            document.getElementById('attendanceChart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: ['Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    datasets: [{
                        label: 'Porcentaje de Asistencia',
                        data: [],
                        backgroundColor: 'rgba(34, 197, 94, 0.5)',
                        borderColor: 'rgb(34, 197, 94)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: chartUtils.createChartOptions('Asistencia por Mes', 50, 100)
            }
        );
    }

    // Setup Event Listeners
    setupEventListeners() {
        // Navigation menu
        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleNavigation(e.target.getAttribute('href').substring(1));
            });
        });

        // Notification bell
        const notificationBell = document.querySelector('.fa-bell').parentElement;
        notificationBell.addEventListener('click', () => this.toggleNotifications());

        // Year selector for charts
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', (e) => {
                const chart = e.target.closest('.chart-container').id;
                this.updateChartData(chart, e.target.value);
            });
        });
    }

    // Load Dashboard Data
    async loadDashboardData() {
        try {
            // Show loading state
            this.showLoading();

            // Fetch data in parallel
            const [students, grades, attendance] = await Promise.all([
                studentsAPI.getStats(),
                gradesAPI.getStats(),
                attendanceAPI.getStats()
            ]);

            // Update statistics cards
            this.updateStatCards({
                totalStudents: students.data.total_students,
                averageAttendance: attendance.data.summary.present_percentage,
                averageGrade: grades.data.summary.average_grade,
                totalSubjects: grades.data.summary.total_subjects
            });

            // Update charts
            this.updateCharts(grades.data.trends, attendance.data.trends);

            // Hide loading state
            this.hideLoading();
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            this.showError('Error al cargar los datos del dashboard');
        }
    }

    // Update Statistics Cards
    updateStatCards(stats) {
        document.querySelector('[data-stat="total-students"] dd').textContent = stats.totalStudents;
        document.querySelector('[data-stat="average-attendance"] dd').textContent = `${stats.averageAttendance}%`;
        document.querySelector('[data-stat="average-grade"] dd').textContent = stats.averageGrade.toFixed(1);
        document.querySelector('[data-stat="total-subjects"] dd').textContent = stats.totalSubjects;
    }

    // Update Charts
    updateCharts(gradesData, attendanceData) {
        // Update Academic Progress Chart
        this.academicProgressChart.data.datasets[0].data = gradesData.map(d => d.average_grade);
        this.academicProgressChart.update();

        // Update Attendance Chart
        this.attendanceChart.data.datasets[0].data = attendanceData.map(d => d.present_percentage);
        this.attendanceChart.update();
    }

    // Handle Navigation
    async handleNavigation(page) {
        // Remove active class from all links
        document.querySelectorAll('nav a').forEach(link => {
            link.classList.remove('text-gray-900', 'border-indigo-500');
            link.classList.add('text-gray-500');
        });

        // Add active class to clicked link
        const activeLink = document.querySelector(`nav a[href="#${page}"]`);
        if (activeLink) {
            activeLink.classList.remove('text-gray-500');
            activeLink.classList.add('text-gray-900', 'border-indigo-500');
        }

        // Load page content
        await this.loadPageContent(page);
    }

    // Load Page Content
    async loadPageContent(page) {
        try {
            this.showLoading();

            switch (page) {
                case 'students':
                    await this.loadStudentsPage();
                    break;
                case 'subjects':
                    await this.loadSubjectsPage();
                    break;
                case 'grades':
                    await this.loadGradesPage();
                    break;
                case 'attendance':
                    await this.loadAttendancePage();
                    break;
                default:
                    await this.loadDashboardData();
            }

            this.hideLoading();
        } catch (error) {
            console.error(`Error loading ${page} page:`, error);
            this.showError(`Error al cargar la página de ${page}`);
        }
    }

    // Toggle Notifications Panel
    async toggleNotifications() {
        const notificationsPanel = document.getElementById('notifications-panel');
        
        if (!notificationsPanel) {
            // Create notifications panel if it doesn't exist
            await this.createNotificationsPanel();
        } else {
            // Toggle existing panel
            notificationsPanel.classList.toggle('hidden');
        }
    }

    // Create Notifications Panel
    async createNotificationsPanel() {
        try {
            const notifications = await notificationsAPI.getAll({ limit: 5 });
            
            const panel = document.createElement('div');
            panel.id = 'notifications-panel';
            panel.className = 'absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50';
            
            panel.innerHTML = `
                <div class="p-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium">Notificaciones</h3>
                        <button class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    ${notifications.data.notifications.map(notification => `
                        <div class="p-4 border-b border-gray-200 hover:bg-gray-50 ${notification.is_read ? 'opacity-75' : ''}">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-${this.getNotificationIcon(notification.type)} text-${this.getNotificationColor(notification.type)}"></i>
                                </div>
                                <div class="ml-3 w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                                    <p class="mt-1 text-sm text-gray-500">${notification.message}</p>
                                    <p class="mt-1 text-xs text-gray-400">${this.formatDate(notification.created_at)}</p>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <div class="p-4 border-t border-gray-200">
                    <a href="#" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                        Ver todas las notificaciones
                    </a>
                </div>
            `;

            document.querySelector('.relative').appendChild(panel);

            // Add close button event listener
            panel.querySelector('button').addEventListener('click', () => {
                panel.classList.add('hidden');
            });
        } catch (error) {
            console.error('Error creating notifications panel:', error);
            this.showError('Error al cargar las notificaciones');
        }
    }

    // Utility Functions
    getNotificationIcon(type) {
        const icons = {
            grade: 'graduation-cap',
            attendance: 'calendar-check',
            general: 'info-circle',
            alert: 'exclamation-circle'
        };
        return icons[type] || 'bell';
    }

    getNotificationColor(type) {
        const colors = {
            grade: 'blue-500',
            attendance: 'green-500',
            general: 'gray-500',
            alert: 'red-500'
        };
        return colors[type] || 'gray-500';
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return new Intl.RelativeTimeFormat('es', { numeric: 'auto' }).format(
            Math.ceil((date - new Date()) / (1000 * 60 * 60 * 24)),
            'day'
        );
    }

    showLoading() {
        // Implement loading indicator
    }

    hideLoading() {
        // Hide loading indicator
    }

    showError(message) {
        // Implement error message display
        console.error(message);
    }
}

// Initialize UI when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new UI();
});

export default UI;
