
class ProfesorPanel {
    constructor() {
        this.currentSection = 'dashboard';
        this.profesorData = null;
        this.init();
    }
    
    async init() {
        // Wait for auth check
        await window.authManager.checkSession();
        
        // Verify profesor role
        if (!window.authManager.hasRole('profesor')) {
            alert('No tienes permisos para acceder a este panel');
            window.location.href = 'login.html';
            return;
        }
        
        this.loadProfesorData();
        this.setupEventListeners();
        this.showSection('dashboard');
    }
    
    setupEventListeners() {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = e.target.getAttribute('onclick').match(/'([^']+)'/)[1];
                this.showSection(section);
            });
        });
    }
    
    async loadProfesorData() {
        try {
            const response = await fetch('api/profesor-data.php');
            const result = await response.json();
            
            if (result.success) {
                this.profesorData = result.data;
                this.updateDashboard();
                this.updateProfesorInfo();
            } else {
                console.error('Error cargando datos:', result.error);
                this.showError('Error al cargar los datos del profesor');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Error de conexión');
        }
    }
    
    updateProfesorInfo() {
        if (!this.profesorData || !this.profesorData.profesor) return;
        
        const profesor = this.profesorData.profesor;
        
        // Update legajo
        const legajoElement = document.getElementById('user-legajo');
        if (legajoElement) {
            legajoElement.textContent = profesor.legajo || 'No asignado';
        }
        
        // Update especialidad
        const especialidadElement = document.getElementById('user-especialidad');
        if (especialidadElement) {
            especialidadElement.textContent = profesor.especialidad || 'No especificada';
        }
    }
    
    updateDashboard() {
        if (!this.profesorData) return;
        
        document.getElementById('total-cursos').textContent = this.profesorData.cursos?.length || 0;
        document.getElementById('total-estudiantes').textContent = this.profesorData.total_estudiantes || 0;
        document.getElementById('calificaciones-pendientes').textContent = this.profesorData.calificaciones_pendientes || 0;
        
        // Calculate promedio if available
        if (this.profesorData.promedio_general) {
            document.getElementById('promedio-cursos').textContent = this.profesorData.promedio_general;
        }
        
        this.updateRecentActivity();
    }
    
    updateRecentActivity() {
        const container = document.getElementById('actividad-profesor');
        
        if (!this.profesorData.actividad_reciente || this.profesorData.actividad_reciente.length === 0) {
            container.innerHTML = '<p>No hay actividad reciente registrada.</p>';
            return;
        }
        
        let html = `
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Curso</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        this.profesorData.actividad_reciente.forEach(activity => {
            html += `
                <tr>
                    <td>${this.formatDate(activity.fecha)}</td>
                    <td>${activity.tipo}</td>
                    <td>${activity.curso}</td>
                    <td>${activity.detalles}</td>
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
        
        if (sectionName === 'dashboard') {
            document.getElementById('dashboard').style.display = 'block';
        } else {
            this.loadSection(sectionName);
        }
        
        this.currentSection = sectionName;
    }
    
    async loadSection(sectionName) {
        const container = document.getElementById('dynamic-content');
        container.innerHTML = '<div class="loading">Cargando...</div>';
        
        try {
            const response = await fetch(`sections/profesor-${sectionName}.html`);
            const html = await response.text();
            container.innerHTML = html;
            
            if (window[`initProfesor${this.capitalize(sectionName)}`]) {
                window[`initProfesor${this.capitalize(sectionName)}`](this.profesorData);
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
    window.profesorPanel = new ProfesorPanel();
});

function showSection(sectionName) {
    if (window.profesorPanel) {
        window.profesorPanel.showSection(sectionName);
    }
}
