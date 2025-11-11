
class AdminPanel {
    constructor() {
        this.currentSection = 'dashboard';
        this.adminData = null;
        this.init();
    }
    
    async init() {
        // Wait for auth check
        await window.authManager.checkSession();
        
        // Verify admin role
        if (!window.authManager.hasRole('admin')) {
            alert('No tienes permisos para acceder a este panel');
            window.location.href = 'login.html';
            return;
        }
        
        this.loadAdminData();
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
    
    async loadAdminData() {
        try {
            const response = await fetch('api/admin-stats.php');
            const result = await response.json();
            
            if (result.success) {
                this.adminData = result.data;
                this.updateDashboard();
            } else {
                console.error('Error cargando datos:', result.error);
                this.showError('Error al cargar estadísticas');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Error de conexión');
        }
    }
    
    updateDashboard() {
        if (!this.adminData) return;
        
        // Calculate totals
        let totalUsuarios = 0;
        let totalEstudiantes = 0;
        let totalProfesores = 0;
        
        this.adminData.user_stats.forEach(stat => {
            totalUsuarios += parseInt(stat.total);
            if (stat.tipo_usuario === 'estudiante') {
                totalEstudiantes = parseInt(stat.total);
            } else if (stat.tipo_usuario === 'profesor') {
                totalProfesores = parseInt(stat.total);
            }
        });
        
        document.getElementById('total-usuarios').textContent = totalUsuarios;
        document.getElementById('total-estudiantes').textContent = totalEstudiantes;
        document.getElementById('total-profesores').textContent = totalProfesores;
        document.getElementById('contactos-pendientes').textContent = this.adminData.pending_contacts;
        
        this.updateRecentUsers();
        this.updateSystemActivity();
    }
    
    updateRecentUsers() {
        const container = document.getElementById('ultimos-registros');
        
        if (!this.adminData.recent_users || this.adminData.recent_users.length === 0) {
            container.innerHTML = '<p>No hay registros recientes.</p>';
            return;
        }
        
        let html = `
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        this.adminData.recent_users.slice(0, 5).forEach(user => {
            html += `
                <tr>
                    <td>${user.nombre} ${user.apellido}</td>
                    <td><span class="badge badge-${user.tipo_usuario}">${user.tipo_usuario}</span></td>
                    <td>${this.formatDate(user.fecha_registro)}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    updateSystemActivity() {
        const container = document.getElementById('actividad-sistema');
        
        let html = `
            <div class="system-stats">
                <h5 style="color: var(--primary-color); margin-bottom: 1rem;">Estadísticas de Proyectos</h5>
        `;
        
        if (this.adminData.project_stats && this.adminData.project_stats.length > 0) {
            this.adminData.project_stats.forEach(stat => {
                const completionRate = stat.total_proyectos > 0 ? 
                    ((stat.completados / stat.total_proyectos) * 100).toFixed(1) : 0;
                
                html += `
                    <div style="margin-bottom: 1rem; padding: 1rem; background: var(--light-bg); border-radius: 6px; border-left: 4px solid var(--accent-color);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <strong style="color: var(--primary-color);">${stat.orientacion}</strong>
                            <span class="badge">${completionRate}% completado</span>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; font-size: 0.9rem;">
                            <div>Total: <strong>${stat.total_proyectos}</strong></div>
                            <div class="success">Completados: <strong>${stat.completados}</strong></div>
                            <div class="warning">Destacados: <strong>${stat.destacados}</strong></div>
                        </div>
                    </div>
                `;
            });
        } else {
            html += '<p>No hay estadísticas de proyectos disponibles.</p>';
        }
        
        html += '</div>';
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
            const response = await fetch(`sections/admin-${sectionName}.html`);
            const html = await response.text();
            container.innerHTML = html;
            
            if (window[`initAdmin${this.capitalize(sectionName)}`]) {
                window[`initAdmin${this.capitalize(sectionName)}`](this.adminData);
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
    window.adminPanel = new AdminPanel();
});

function showSection(sectionName) {
    if (window.adminPanel) {
        window.adminPanel.showSection(sectionName);
    }
}
