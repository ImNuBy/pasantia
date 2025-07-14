
class SecretarioPanel {
    constructor() {
        this.currentSection = 'dashboard';
        this.secretarioData = null;
        this.init();
    }
    
    async init() {
        // Wait for auth check
        await window.authManager.checkSession();
        
        // Verify secretario role
        if (!window.authManager.hasRole('secretario')) {
            alert('No tienes permisos para acceder a este panel');
            window.location.href = 'login.html';
            return;
        }
        
        this.loadSecretarioData();
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
    
    async loadSecretarioData() {
        try {
            const response = await fetch('api/secretario-data.php');
            const result = await response.json();
            
            if (result.success) {
                this.secretarioData = result.data;
                this.updateDashboard();
            } else {
                console.error('Error cargando datos:', result.error);
                this.showError('Error al cargar los datos');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Error de conexión');
        }
    }
    
    updateDashboard() {
        if (!this.secretarioData) return;
        
        document.getElementById('total-estudiantes').textContent = this.secretarioData.total_estudiantes || 0;
        document.getElementById('total-profesores').textContent = this.secretarioData.total_profesores || 0;
        document.getElementById('consultas-pendientes').textContent = this.secretarioData.consultas_pendientes || 0;
        document.getElementById('inscripciones-mes').textContent = this.secretarioData.inscripciones_mes || 0;
        
        this.updateRecentActivity();
        this.updatePendingTasks();
    }
    
    updateRecentActivity() {
        const container = document.getElementById('actividad-reciente');
        
        if (!this.secretarioData.actividad_reciente || this.secretarioData.actividad_reciente.length === 0) {
            container.innerHTML = '<p>No hay actividad reciente registrada.</p>';
            return;
        }
        
        let html = `
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        this.secretarioData.actividad_reciente.forEach(activity => {
            html += `
                <tr>
                    <td>${this.formatDate(activity.fecha)}</td>
                    <td><span class="badge">${activity.tipo}</span></td>
                    <td>${activity.descripcion}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    updatePendingTasks() {
        const container = document.getElementById('tareas-pendientes');
        
        if (!this.secretarioData.tareas_pendientes || this.secretarioData.tareas_pendientes.length === 0) {
            container.innerHTML = '<p>No hay tareas pendientes.</p>';
            return;
        }
        
        let html = '<ul style="list-style: none; padding: 0;">';
        
        this.secretarioData.tareas_pendientes.forEach(task => {
            const priorityClass = task.prioridad === 'alta' ? 'error' : 
                                task.prioridad === 'media' ? 'warning' : 'success';
            
            html += `
                <li style="padding: 0.75rem; margin-bottom: 0.5rem; background: var(--light-bg); border-radius: 6px; border-left: 4px solid var(--${priorityClass === 'error' ? 'error' : priorityClass === 'warning' ? 'warning' : 'success'}-color);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>${task.descripcion}</span>
                        <span class="badge ${priorityClass}">${task.prioridad}</span>
                    </div>
                    <small style="color: var(--text-light);">Fecha límite: ${this.formatDate(task.fecha_limite)}</small>
                </li>
            `;
        });
        
        html += '</ul>';
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
            const response = await fetch(`sections/secretario-${sectionName}.html`);
            const html = await response.text();
            container.innerHTML = html;
            
            if (window[`initSecretario${this.capitalize(sectionName)}`]) {
                window[`initSecretario${this.capitalize(sectionName)}`](this.secretarioData);
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
    window.secretarioPanel = new SecretarioPanel();
});

function showSection(sectionName) {
    if (window.secretarioPanel) {
        window.secretarioPanel.showSection(sectionName);
    }
}
