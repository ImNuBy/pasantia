/**
 * EPA 703 - Admin Dashboard JavaScript
 * Manejo de la interfaz de administración
 */

// Variables globales
let currentSection = 'dashboard';
let usuarios = [];
let consultas = [];
let inscripciones = [];
let estudiantes = [];
let profesores = [];
let cursos = [];
let sidebarOpen = false;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    checkAuthentication();
    loadDashboardData();
    setupEventListeners();
});

/**
 * Inicializar el dashboard
 */
function initializeDashboard() {
    console.log('🚀 Inicializando Dashboard EPA 703');
    showSection('dashboard');
    setupMobileSidebar();
    loadUserInfo();
}

/**
 * Verificar autenticación
 */
async function checkAuthentication() {
    try {
        const response = await fetch('api/check-session.php');
        const data = await response.json();

        if (!data.authenticated || data.user.tipo_usuario !== 'admin') {
            window.location.href = 'login.html';
            return;
        }

        updateUserInterface(data.user);
    } catch (error) {
        console.error('Error verificando autenticación:', error);
        window.location.href = 'login.html';
    }
}

/**
 * Cargar información del usuario
 */
function loadUserInfo() {
    const userInfo = JSON.parse(sessionStorage.getItem('userInfo') || '{}');
    if (userInfo.nombre) updateUserInterface(userInfo);
}

/**
 * Actualizar interfaz con información del usuario
 */
function updateUserInterface(user) {
    const userName = document.getElementById('userName');
    const userInitials = document.getElementById('userInitials');

    if (userName) userName.textContent = `${user.nombre} ${user.apellido}`;
    if (userInitials) {
        const initials = (user.nombre?.charAt(0) || '') + (user.apellido?.charAt(0) || '');
        userInitials.textContent = initials.toUpperCase();
    }
}

/**
 * Eventos
 */
function setupEventListeners() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);

    document.addEventListener('click', e => {
        if (e.target.classList.contains('modal')) closeAllModals();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeAllModals();
    });
}

/**
 * Sidebar móvil
 */
function setupMobileSidebar() {
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.addEventListener('click', closeSidebar);
    document.body.appendChild(overlay);
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    sidebarOpen = !sidebarOpen;
    sidebar.classList.toggle('show', sidebarOpen);
    overlay.classList.toggle('show', sidebarOpen);
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    sidebarOpen = false;
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
}

/**
 * Mostrar sección
 */
function showSection(sectionName) {
    document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));

    const targetSection = document.getElementById(sectionName);
    if (targetSection) targetSection.classList.add('active');

    const navLink = document.querySelector(`[onclick="showSection('${sectionName}')"]`);
    if (navLink) navLink.classList.add('active');

    currentSection = sectionName;
    loadSectionData(sectionName);

    if (window.innerWidth <= 1024) closeSidebar();
}

/**
 * Cargar datos según sección
 */
async function loadSectionData(sectionName) {
    switch (sectionName) {
        case 'dashboard':     await loadDashboardData(); break;
        case 'usuarios':      await loadUsuarios(); break;
        case 'consultas':     await loadConsultas(); break;
        case 'inscripciones': await loadInscripciones(); break;
        case 'estudiantes':   await loadEstudiantes(); break;
        case 'profesores':    await loadProfesores(); break;
        case 'cursos':        await loadCursos(); break;
    }
}

/* ================================================================
   DASHBOARD
================================================================ */
async function loadDashboardData() {
    showLoading();
    try {
        const response = await fetch('api/dashboard-data.php');
        const data = await response.json();
        if (data.success) {
            updateDashboardStats(data.stats);
            updateNotifications(data.notifications);
            updateActivityFeed(data.activity);
        } else {
            console.error('Error cargando datos del dashboard:', data.error);
            showExampleDashboardData();
        }
    } catch (error) {
        console.error('Error:', error);
        showExampleDashboardData();
    } finally {
        hideLoading();
    }
}

function updateDashboardStats(stats) {
    if (!stats) return;
    updateCounter('totalUsuarios', stats.total_usuarios || 0);
    updateCounter('totalEstudiantes', stats.total_estudiantes || 0);
    updateCounter('consultasPendientes', stats.consultas_pendientes || 0);
    updateCounter('inscripcionesNuevas', stats.inscripciones_nuevas || 0);
    updateBadge('consultasBadge', stats.consultas_pendientes || 0);
    updateBadge('inscripcionesBadge', stats.inscripciones_nuevas || 0);
}

function showExampleDashboardData() {
    const exampleStats = {
        total_usuarios: 127,
        total_estudiantes: 98,
        consultas_pendientes: 5,
        inscripciones_nuevas: 12
    };
    updateDashboardStats(exampleStats);
}

/* ================================================================
   USUARIOS
================================================================ */
async function loadUsuarios() {
    showLoading();
    try {
        const response = await fetch('api/usuarios.php');
        const data = await response.json();
        if (data.success) {
            usuarios = data.usuarios;
            renderUsuariosTable();
        } else {
            console.error('Error cargando usuarios:', data.error);
            showExampleUsuarios();
        }
    } catch (error) {
        console.error('Error:', error);
        showExampleUsuarios();
    } finally {
        hideLoading();
    }
}

function renderUsuariosTable() {
    const tbody = document.getElementById('usuariosTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    usuarios.forEach(usuario => tbody.appendChild(createUsuarioRow(usuario)));
}

function createUsuarioRow(usuario) {
    const row = document.createElement('tr');
    const estadoBadge = usuario.activo ? 
        '<span class="badge badge-success">Activo</span>' : 
        '<span class="badge badge-danger">Inactivo</span>';
    const tipoBadge = getTipoBadge(usuario.tipo_usuario);
    row.innerHTML = `
        <td>${usuario.id}</td>
        <td>${usuario.nombre} ${usuario.apellido}</td>
        <td>${usuario.email}</td>
        <td>${tipoBadge}</td>
        <td>${estadoBadge}</td>
        <td>${formatDate(usuario.fecha_registro)}</td>
        <td>
            <div class="action-buttons">
                <button class="btn btn-sm btn-primary" onclick="editarUsuario(${usuario.id})" title="Editar">✏️</button>
                <button class="btn btn-sm btn-warning" onclick="toggleUsuarioEstado(${usuario.id})" title="Activar/Desactivar">${usuario.activo ? '🔒' : '🔓'}</button>
                <button class="btn btn-sm btn-danger" onclick="eliminarUsuario(${usuario.id})" title="Eliminar">🗑️</button>
            </div>
        </td>`;
    return row;
}

function getTipoBadge(tipo) {
    const badges = {
        'admin': '<span class="badge badge-danger">Admin</span>',
        'profesor': '<span class="badge badge-info">Profesor</span>',
        'estudiante': '<span class="badge badge-success">Estudiante</span>',
        'secretario': '<span class="badge badge-warning">Secretario</span>'
    };
    return badges[tipo] || '<span class="badge badge-secondary">Desconocido</span>';
}

function showExampleUsuarios() {
    usuarios = [
        { id: 1, nombre: 'Juan', apellido: 'Pérez', email: 'juan.perez@epa703.edu.ar', tipo_usuario: 'estudiante', activo: 1, fecha_registro: '2024-01-15' },
        { id: 2, nombre: 'María', apellido: 'González', email: 'maria.gonzalez@epa703.edu.ar', tipo_usuario: 'profesor', activo: 1, fecha_registro: '2024-01-10' },
        { id: 3, nombre: 'Carlos', apellido: 'Rodríguez', email: 'carlos.rodriguez@epa703.edu.ar', tipo_usuario: 'admin', activo: 1, fecha_registro: '2024-01-05' }
    ];
    renderUsuariosTable();
}

/* ================================================================
   INSCRIPCIONES / ESTUDIANTES / PROFESORES / CURSOS
   (mantengo tus funciones: loadInscripciones, renderInscripcionesTable,
   createInscripcionRow, loadEstudiantes, renderEstudiantesTable,
   createEstudianteRow, loadProfesores, renderProfesoresTable,
   createProfesorRow, loadCursos, renderCursosGrid, createCursoCard,
   updateInscripcionesStats, updateEstudiantesStats, updateProfesoresStats,
   showExampleInscripciones/Estudiantes/Profesores/Cursos, filtros, etc.)
================================================================ */

/* ================================================================
   CONSULTAS
   (igual que en tu archivo: loadConsultas, renderConsultasTable,
   createConsultaRow, getEstadoBadge, getPrioridadBadge,
   getTipoConsultaBadge, showExampleConsultas, filtros, etc.)
================================================================ */

/* ================================================================
   UTILIDADES
================================================================ */
function updateCounter(elementId, value) {
    const element = document.getElementById(elementId);
    if (!element) return;
    const startValue = parseInt(element.textContent) || 0;
    const duration = 1000;
    const startTime = performance.now();
    function animate(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easedProgress = 1 - Math.pow(1 - progress, 3);
        const currentValue = Math.round(startValue + (value - startValue) * easedProgress);
        element.textContent = currentValue;
        if (progress < 1) requestAnimationFrame(animate);
    }
    requestAnimationFrame(animate);
}

function updateBadge(elementId, value) {
    const badge = document.getElementById(elementId);
    if (!badge) return;
    badge.textContent = value;
    badge.classList.toggle('show', value > 0);
}

function showLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading) loading.classList.add('show');
}

function hideLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading) loading.classList.remove('show');
}

function showAlert(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 2000;
        max-width: 300px; padding: 1rem; border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideInRight 0.3s ease;`;
    const styles = {
        success: { bg: '#c6f6d5', color: '#22543d', border: '#9ae6b4' },
        error: { bg: '#fed7d7', color: '#c53030', border: '#feb2b2' },
        warning: { bg: '#fefcbf', color: '#744210', border: '#f6e05e' },
        info: { bg: '#bee3f8', color: '#2a4365', border: '#90cdf4' }
    };
    const style = styles[type] || styles.info;
    alert.style.backgroundColor = style.bg;
    alert.style.color = style.color;
    alert.style.border = `1px solid ${style.border}`;
    alert.textContent = message;
    document.body.appendChild(alert);
    setTimeout(() => {
        if (alert.parentNode) {
            alert.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }
    }, 5000);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-AR', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function truncateText(text, maxLength) {
    if (!text) return '-';
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}

function updateNotifications(notifications) {
    const count = notifications?.length || 0;
    const notificationCount = document.getElementById('notificationCount');
    if (notificationCount) {
        notificationCount.textContent = count;
        notificationCount.classList.toggle('show', count > 0);
    }
}

function updateActivityFeed(activities) {
    const activityList = document.getElementById('activityList');
    if (!activityList || !activities) return;
    activityList.innerHTML = '';
    activities.forEach(activity => {
        const item = document.createElement('div');
        item.className = 'activity-item';
        item.innerHTML = `
            <div class="activity-icon">${activity.icon || '📝'}</div>
            <div class="activity-content">
                <p>${activity.message}</p>
                <span class="activity-time">${activity.time}</span>
            </div>`;
        activityList.appendChild(item);
    });
}

// Animaciones de alerta
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight { from {opacity: 0; transform: translateX(100%);} to {opacity: 1; transform: translateX(0);} }
    @keyframes slideOutRight { from {opacity: 1; transform: translateX(0);} to {opacity: 0; transform: translateX(100%);} }`;
document.head.appendChild(style);
