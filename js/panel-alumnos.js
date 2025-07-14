class StudentPanel {
    constructor() {
        this.currentSection = 'dashboard';
        this.studentData = null;
        this.init();
    }
    
    async init() {
        // Wait for auth check
        await window.authManager.checkSession();
        
        // Verify student role
        if (!window.authManager.hasRole('estudiante')) {
            alert('No tienes permisos para acceder a este panel');
            window.location.href = 'login.html';
            return;
        }
        
        this.loadStudentData();
        this.setupEventListeners();
        this.showSection('dashboard');
    }
    
    setupEventListeners() {
        // Navigation clicks
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = e.target.getAttribute('onclick').match(/'([^']+)'/)[1];
                this.showSection(section);
            });
        });
    }
    
    async loadStudentData() {
        try {
            const response = await fetch('api/student-data.php');
            const result = await response.json();
            
            if (result.success) {
                this.studentData = result.data;
                this.updateDashboard();
                this.updateStudentInfo();
            } else {
                console.error('Error cargando datos:', result.error);
                this.showError('Error al cargar los datos del estudiante');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Error de conexión');
        }
    }
    
    updateStudentInfo() {
        if (!this.studentData || !this.studentData.estudiante) return;
        
        const student = this.studentData.estudiante;
        
        // Update course info
        const cursoElement = document.getElementById('user-curso');
        if (cursoElement) {
            cursoElement.textContent = student.curso_nombre || 'No asignado';
        }
        
        // Update orientation
        const orientacionElement = document.getElementById('orientacion');
        if (orientacionElement) {
            orientacionElement.textContent = student.orientacion_nombre || 'No asignada';
        }
    }
    
    updateDashboard() {
        if (!this.studentData) return;
        
        // Update próximas evaluaciones
        document.getElementById('proximas-evaluaciones').textContent = '0';
        
        // Calculate promedio general
        if (this.studentData.calificaciones && this.studentData.calificaciones.length > 0) {
            const notas = this.studentData.calificaciones
                .map(c => parseFloat(c.nota))
                .filter(n => !isNaN(n));
            
            if (notas.length > 0) {
                const promedio = notas.reduce((a, b) => a + b, 0) / notas.length;
                document.getElementById('promedio-general').textContent = promedio.toFixed(1);
            }
        }
        
        // Calculate asistencia del mes
        if (this.studentData.asistencias && this.studentData.asistencias.length > 0) {
            const totalAsistencia = this.studentData.asistencias.reduce((acc, curr) => 
                acc + parseFloat(curr.porcentaje_asistencia), 0
            ) / this.studentData.asistencias.length;
            document.getElementById('asistencia-mes').textContent = totalAsistencia.toFixed(0) + '%';
        }
        
        // Update recent activity
        this.updateRecentActivity();
    }
    
    updateRecentActivity() {
        const container = document.getElementById('actividad-reciente');
        
        if (!this.studentData.calificaciones || this.studentData.calificaciones.length === 0) {
            container.innerHTML = '<p>No hay actividad reciente registrada.</p>';
            return;
        }
        
        // Show últimas 5 calificaciones
        const recentGrades = this.studentData.calificaciones
            .sort((a, b) => new Date(b.fecha_evaluacion) - new Date(a.fecha_evaluacion))
            .slice(0, 5);
        
        let html = `
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Materia</th>
                        <th>Tipo</th>
                        <th>Nota</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        recentGrades.forEach(grade => {
            html += `
                <tr>
                    <td>${this.formatDate(grade.fecha_evaluacion)}</td>
                    <td>${grade.materia}</td>
                    <td>${grade.tipo_evaluacion}</td>
                    <td>${grade.nota || 'Pendiente'}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    showSection(sectionName) {
        // Update navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        // Find the clicked link and make it active
        const clickedLink = Array.from(document.querySelectorAll('.nav-link'))
            .find(link => link.getAttribute('onclick').includes(sectionName));
        if (clickedLink) {
            clickedLink.classList.add('active');
        }
        
        // Hide all sections
        document.querySelectorAll('.panel-section').forEach(section => {
            section.style.display = 'none';
        });
        
        // Show selected section
        if (sectionName === 'dashboard') {
            document.getElementById('dashboard').style.display = 'block';
            this.updateDashboard();
        } else {
            this.loadSection(sectionName);
        }
        
        this.currentSection = sectionName;
    }
    
    async loadSection(sectionName) {
        const container = document.getElementById('dynamic-content');
        container.innerHTML = '<div class="loading">Cargando...</div>';
        
        try {
            const response = await fetch(`sections/student-${sectionName}.html`);
            const html = await response.text();
            container.innerHTML = html;
            
            // Initialize section-specific functionality
            if (window[`init${this.capitalize(sectionName)}`]) {
                window[`init${this.capitalize(sectionName)}`](this.studentData);
            }
        } catch (error) {
            console.error('Error loading section:', error);
            container.innerHTML = '<div class="error-message">Error al cargar la sección</div>';
        }
    }
    
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('es-AR');
    }
    
    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    showError(message) {
        const container = document.getElementById('dynamic-content');
        container.innerHTML = `<div class="error-message">${message}</div>`;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.studentPanel = new StudentPanel();
});

// Global function for navigation (backward compatibility)
function showSection(sectionName) {
    if (window.studentPanel) {
        window.studentPanel.showSection(sectionName);
    }
}