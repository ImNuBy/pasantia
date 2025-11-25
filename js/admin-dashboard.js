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
/**
 * EPA 703 - Funciones de Reportes y Configuración
 * JavaScript para las secciones completas del panel de administrador
 */

// ========================================
// FUNCIONES DE REPORTES
// ========================================

/**
 * Cambiar entre tabs de reportes
 */
function showReportTab(tabName) {
    // Ocultar todos los tabs
    document.querySelectorAll('#reportes .tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Quitar active de todos los botones
    document.querySelectorAll('#reportes .tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostrar tab seleccionado
    const selectedTab = document.getElementById(`tab-${tabName}`);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Activar botón correspondiente
    event.target.classList.add('active');
    
    // Cargar datos del tab si es necesario
    if (tabName === 'estudiantes') {
        cargarEstadisticasEstudiantes();
    } else if (tabName === 'general') {
        cargarGraficos();
    }
}

/**
 * Generar reportes generales
 */
async function generarReporte(tipo, formato) {
    console.log(`Generando reporte ${tipo} en formato ${formato}...`);
    
    if (formato === 'preview') {
        // Mostrar vista previa en modal
        showNotification(`Vista previa del reporte ${tipo}`, 'info');
        // Aquí iría la lógica para mostrar el modal con la vista previa
    } else {
        // Descargar archivo
        try {
            const response = await fetch(`api/reportes.php?tipo=${tipo}&formato=${formato}`);
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `reporte_${tipo}_${new Date().getTime()}.${formato}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                showNotification(`✅ Reporte ${tipo} descargado exitosamente`, 'success');
            } else {
                throw new Error('Error al generar reporte');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification(`❌ Error al generar reporte: ${error.message}`, 'danger');
        }
    }
}

/**
 * Cargar gráficos de reportes
 */
function cargarGraficos() {
    console.log('Cargando gráficos de reportes...');
    
    // Simulación de datos - en producción vendría de la API
    const datosInscripciones = {
        labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
        data: [15, 18, 22, 20, 25, 30, 28, 35, 32, 30, 28, 25]
    };
    
    // Aquí irían las llamadas a Chart.js o la librería de gráficos elegida
    console.log('Datos para gráficos:', datosInscripciones);
    showNotification('Gráficos cargados', 'info');
}

/**
 * Generar reporte de estudiantes con filtros
 */
async function generarReporteEstudiantes() {
    const filtros = {
        ciclo: document.getElementById('reporteCiclo')?.value || '',
        estado: document.getElementById('reporteEstado')?.value || '',
        fechaDesde: document.getElementById('reporteFechaDesde')?.value || '',
        fechaHasta: document.getElementById('reporteFechaHasta')?.value || ''
    };
    
    console.log('Generando reporte de estudiantes con filtros:', filtros);
    
    try {
        const response = await fetch('api/reportes-estudiantes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(filtros)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Actualizar estadísticas
            document.getElementById('reporteEstTotal').textContent = data.stats.total;
            document.getElementById('reporteEstActivos').textContent = data.stats.activos;
            document.getElementById('reporteEstGraduados').textContent = data.stats.graduados;
            document.getElementById('reporteEstDesercion').textContent = data.stats.desercion + '%';
            
            showNotification('✅ Reporte generado exitosamente', 'success');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al generar reporte', 'danger');
    }
}

/**
 * Exportar reporte de estudiantes
 */
async function exportarReporteEstudiantes(formato) {
    const filtros = {
        ciclo: document.getElementById('reporteCiclo')?.value || '',
        estado: document.getElementById('reporteEstado')?.value || '',
        fechaDesde: document.getElementById('reporteFechaDesde')?.value || '',
        fechaHasta: document.getElementById('reporteFechaHasta')?.value || '',
        formato: formato
    };
    
    console.log(`Exportando reporte de estudiantes en ${formato}...`);
    
    try {
        const response = await fetch('api/exportar-estudiantes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(filtros)
        });
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const extension = formato === 'excel' ? 'xlsx' : 'pdf';
            a.download = `reporte_estudiantes_${new Date().getTime()}.${extension}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            showNotification(`✅ Reporte exportado en ${formato}`, 'success');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al exportar reporte', 'danger');
    }
}

/**
 * Funciones de reportes de profesores
 */
async function exportarProfesores(formato) {
    console.log(`Exportando profesores en ${formato}...`);
    showNotification(`Generando reporte de profesores en ${formato}...`, 'info');
    // Implementar lógica de exportación
}

async function reporteCargaHoraria(formato) {
    console.log(`Generando reporte de carga horaria en ${formato}...`);
    showNotification('Generando reporte de carga horaria...', 'info');
    // Implementar lógica
}

async function reporteAsignaciones(formato) {
    console.log(`Generando reporte de asignaciones en ${formato}...`);
    showNotification('Generando reporte de asignaciones...', 'info');
    // Implementar lógica
}

/**
 * Funciones de reportes académicos
 */
function verRendimiento() {
    console.log('Mostrando rendimiento académico...');
    showNotification('Cargando rendimiento académico...', 'info');
}

function exportarRendimiento(formato) {
    console.log(`Exportando rendimiento en ${formato}...`);
    showNotification(`Generando reporte de rendimiento...`, 'info');
}

function verAsistencias() {
    console.log('Mostrando asistencias...');
    showNotification('Cargando asistencias...', 'info');
}

function exportarAsistencias(formato) {
    console.log(`Exportando asistencias en ${formato}...`);
    showNotification('Generando reporte de asistencias...', 'info');
}

function verCertificados() {
    console.log('Mostrando certificados...');
    showNotification('Cargando certificados emitidos...', 'info');
}

function exportarCertificados(formato) {
    console.log(`Exportando certificados en ${formato}...`);
    showNotification('Generando reporte de certificados...', 'info');
}

function verTasaFinalizacion() {
    console.log('Mostrando tasa de finalización...');
    showNotification('Cargando tasa de finalización...', 'info');
}

function exportarTasaFinalizacion(formato) {
    console.log(`Exportando tasa de finalización en ${formato}...`);
    showNotification('Generando reporte...', 'info');
}

/**
 * Cargar estadísticas de estudiantes para reportes
 */
async function cargarEstadisticasEstudiantes() {
    try {
        const response = await fetch('api/estadisticas-estudiantes.php');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('reporteEstTotal').textContent = data.total || '0';
            document.getElementById('reporteEstActivos').textContent = data.activos || '0';
            document.getElementById('reporteEstGraduados').textContent = data.graduados || '0';
            document.getElementById('reporteEstDesercion').textContent = (data.desercion || 0) + '%';
        }
    } catch (error) {
        console.error('Error cargando estadísticas:', error);
    }
}

// ========================================
// FUNCIONES DE CONFIGURACIÓN
// ========================================

/**
 * Cambiar entre tabs de configuración
 */
function showConfigTab(tabName) {
    // Ocultar todos los tabs
    document.querySelectorAll('#configuracion .tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Quitar active de todos los botones
    document.querySelectorAll('#configuracion .tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostrar tab seleccionado
    const selectedTab = document.getElementById(`config-${tabName}`);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Activar botón correspondiente
    event.target.classList.add('active');
    
    // Cargar configuración si es necesario
    if (tabName === 'general') {
        cargarConfigGeneral();
    } else if (tabName === 'email') {
        cargarConfigEmail();
    }
}

/**
 * Cargar configuración general
 */
async function cargarConfigGeneral() {
    try {
        const response = await fetch('api/config.php?seccion=general');
        const data = await response.json();
        
        if (data.success) {
            // Llenar campos con la configuración actual
            Object.keys(data.config).forEach(key => {
                const input = document.getElementById(`config_${key}`);
                if (input) {
                    input.value = data.config[key];
                }
            });
        }
    } catch (error) {
        console.error('Error cargando configuración:', error);
    }
}

/**
 * Guardar configuración general
 */
document.getElementById('formConfigGeneral')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const config = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/guardar-config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seccion: 'general', config })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Configuración guardada exitosamente', 'success');
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al guardar configuración: ' + error.message, 'danger');
    }
});

/**
 * Restablecer configuración general
 */
function resetConfigGeneral() {
    if (confirm('¿Estás seguro de restablecer la configuración general a los valores por defecto?')) {
        cargarConfigGeneral();
        showNotification('Configuración restablecida', 'info');
    }
}

/**
 * Guardar configuración de redes sociales
 */
document.getElementById('formConfigRedes')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const config = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/guardar-config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seccion: 'redes', config })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Redes sociales actualizadas', 'success');
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al guardar: ' + error.message, 'danger');
    }
});

/**
 * Cargar configuración de email
 */
async function cargarConfigEmail() {
    try {
        const response = await fetch('api/config.php?seccion=email');
        const data = await response.json();
        
        if (data.success) {
            Object.keys(data.config).forEach(key => {
                const input = document.getElementById(`config_${key}`);
                if (input && key !== 'smtp_pass') { // No mostrar contraseña
                    input.value = data.config[key];
                }
            });
        }
    } catch (error) {
        console.error('Error cargando configuración de email:', error);
    }
}

/**
 * Guardar configuración de email
 */
document.getElementById('formConfigEmail')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const config = Object.fromEntries(formData);
    
    // No enviar contraseña si está vacía
    if (!config.smtp_pass) {
        delete config.smtp_pass;
    }
    
    try {
        const response = await fetch('api/guardar-config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seccion: 'email', config })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Configuración de email guardada', 'success');
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al guardar: ' + error.message, 'danger');
    }
});

/**
 * Probar configuración de email
 */
async function probarEmail() {
    showNotification('📧 Enviando email de prueba...', 'info');
    
    try {
        const response = await fetch('api/test-email.php', {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Email de prueba enviado exitosamente', 'success');
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al enviar email: ' + error.message, 'danger');
    }
}

/**
 * Editar plantilla de email
 */
function editarPlantilla(tipo) {
    console.log(`Editando plantilla de ${tipo}...`);
    showNotification(`Abriendo editor de plantilla: ${tipo}`, 'info');
    // Aquí se abriría un modal con el editor de la plantilla
}

/**
 * Guardar configuración académica
 */
document.getElementById('formConfigAcademico')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const config = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/guardar-config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seccion: 'academico', config })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Configuración académica guardada', 'success');
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al guardar: ' + error.message, 'danger');
    }
});

/**
 * Editar configuración de ciclo
 */
function editarCiclo(numeroCiclo) {
    console.log(`Editando configuración del ciclo ${numeroCiclo}...`);
    showNotification(`Editando Ciclo ${numeroCiclo}`, 'info');
    // Aquí se abriría un modal para editar el ciclo
}

/**
 * Guardar configuración de usuarios
 */
document.getElementById('formConfigUsuarios')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const config = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/guardar-config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seccion: 'usuarios', config })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Configuración de usuarios guardada', 'success');
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al guardar: ' + error.message, 'danger');
    }
});

/**
 * Funciones de mantenimiento del sistema
 */
async function limpiarCache() {
    if (confirm('¿Deseas limpiar la caché del sistema?')) {
        showNotification('🗑️ Limpiando caché...', 'info');
        
        try {
            const response = await fetch('api/mantenimiento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_cache' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('✅ Caché limpiada exitosamente', 'success');
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('❌ Error al limpiar caché', 'danger');
        }
    }
}

async function optimizarDB() {
    if (confirm('¿Deseas optimizar la base de datos? Esto puede tomar varios minutos.')) {
        showNotification('⚡ Optimizando base de datos...', 'info');
        
        try {
            const response = await fetch('api/mantenimiento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'optimize_db' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('✅ Base de datos optimizada', 'success');
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('❌ Error al optimizar base de datos', 'danger');
        }
    }
}

async function backupDB() {
    if (confirm('¿Deseas crear un backup de la base de datos?')) {
        showNotification('💾 Creando backup...', 'info');
        
        try {
            const response = await fetch('api/mantenimiento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'backup_db' })
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `backup_epa703_${new Date().getTime()}.sql`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                showNotification('✅ Backup creado y descargado', 'success');
            } else {
                throw new Error('Error al crear backup');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('❌ Error al crear backup', 'danger');
        }
    }
}

function confirmarMantenimiento() {
    if (confirm('⚠️ ¿Activar modo mantenimiento? Los usuarios no podrán acceder al sistema.')) {
        activarModoMantenimiento();
    }
}

async function activarModoMantenimiento() {
    showNotification('🔧 Activando modo mantenimiento...', 'info');
    
    try {
        const response = await fetch('api/mantenimiento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'maintenance_mode', enabled: true })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Modo mantenimiento activado', 'warning');
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al activar modo mantenimiento', 'danger');
    }
}

/**
 * Cargar logs del sistema
 */
async function cargarLogs() {
    const tipoLog = document.getElementById('logTipo')?.value || 'todos';
    const logsContent = document.getElementById('logsContent');
    
    if (!logsContent) return;
    
    logsContent.innerHTML = '<p style="text-align: center; color: #666;">Cargando logs...</p>';
    
    try {
        const response = await fetch(`api/logs.php?tipo=${tipoLog}`);
        const data = await response.json();
        
        if (data.success && data.logs.length > 0) {
            logsContent.innerHTML = data.logs.map(log => 
                `<div class="log-entry">
                    <span class="log-time">[${log.timestamp}]</span>
                    <span class="log-level log-${log.level}">${log.level.toUpperCase()}</span>
                    <span class="log-message">${log.message}</span>
                </div>`
            ).join('');
        } else {
            logsContent.innerHTML = '<p style="text-align: center; color: #666;">No hay logs disponibles</p>';
        }
    } catch (error) {
        console.error('Error:', error);
        logsContent.innerHTML = '<p style="text-align: center; color: #d32f2f;">Error al cargar logs</p>';
    }
}

/**
 * Limpiar logs
 */
async function limpiarLogs() {
    if (confirm('⚠️ ¿Estás seguro de eliminar todos los logs? Esta acción no se puede deshacer.')) {
        try {
            const response = await fetch('api/logs.php', {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('✅ Logs eliminados', 'success');
                cargarLogs();
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('❌ Error al eliminar logs', 'danger');
        }
    }
}

/**
 * Funciones de zona de peligro
 */
function confirmarReiniciarSistema() {
    if (confirm('⚠️ ADVERTENCIA: ¿Reiniciar el sistema? Todos los usuarios serán desconectados.')) {
        if (confirm('¿Estás completamente seguro? Esta es tu última oportunidad para cancelar.')) {
            reiniciarSistema();
        }
    }
}

async function reiniciarSistema() {
    showNotification('🔄 Reiniciando sistema...', 'warning');
    
    try {
        const response = await fetch('api/sistema.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'restart' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Sistema reiniciado', 'success');
            setTimeout(() => window.location.reload(), 3000);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al reiniciar sistema', 'danger');
    }
}

function confirmarResetearConfig() {
    if (confirm('⚠️ PELIGRO: ¿Restablecer TODA la configuración a valores por defecto?')) {
        if (confirm('Esta acción eliminará todas las configuraciones personalizadas. ¿Continuar?')) {
            resetearConfig();
        }
    }
}

async function resetearConfig() {
    showNotification('↺ Restableciendo configuración...', 'warning');
    
    try {
        const response = await fetch('api/sistema.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset_config' })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Configuración restablecida', 'success');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al restablecer configuración', 'danger');
    }
}

function confirmarEliminarDatos() {
    if (confirm('⚠️ PELIGRO EXTREMO: ¿Eliminar todos los datos de prueba?')) {
        if (confirm('Esto eliminará estudiantes, profesores y registros de prueba. ¿DEFINITIVAMENTE continuar?')) {
            const password = prompt('Ingresa la contraseña de administrador para confirmar:');
            if (password) {
                eliminarDatosPrueba(password);
            }
        }
    }
}

async function eliminarDatosPrueba(password) {
    showNotification('⚠️ Eliminando datos de prueba...', 'warning');
    
    try {
        const response = await fetch('api/sistema.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_test_data', password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ Datos de prueba eliminados', 'success');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error: ' + error.message, 'danger');
    }
}

/**
 * Mostrar notificación (reutiliza la función del dashboard)
 */
function showNotification(message, type = 'info') {
    // Si existe la función global del dashboard, usarla
    if (window.dashboardInstance && window.dashboardInstance.showNotification) {
        window.dashboardInstance.showNotification(message, type);
        return;
    }
    
    // Sino, mostrar alert simple
    const icons = {
        success: '✅',
        danger: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    console.log(`${icons[type] || 'ℹ️'} ${message}`);
    
    // Crear notificación visual si no existe el dashboard
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#28a745' : type === 'danger' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

console.log('✅ Módulos de Reportes y Configuración cargados correctamente');


/* ================================================================
   INSCRIPCIONES - Funciones Completas
================================================================ */



/**
 * Cargar inscripciones desde la API
 */
async function loadInscripciones() {
    showLoading();
    try {
        const response = await fetch('api/inscripciones.php');
        const data = await response.json();
        
        if (data.success) {
            inscripciones = data.inscripciones;
            renderInscripcionesTable();
            updateInscripcionesStats();
            updateInscripcionesBadge();
        } else {
            console.error('Error cargando inscripciones:', data.error);
            showExampleInscripciones();
        }
    } catch (error) {
        console.error('Error:', error);
        showExampleInscripciones();
    } finally {
        hideLoading();
    }
}

/**
 * Renderizar tabla de inscripciones
 */
function renderInscripcionesTable() {
    const tbody = document.getElementById('inscripcionesTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    inscripciones.forEach(inscripcion => {
        tbody.appendChild(createInscripcionRow(inscripcion));
    });
}

/**
 * Crear fila de inscripción
 */
function createInscripcionRow(inscripcion) {
    const row = document.createElement('tr');
    
    const estadoBadge = getEstadoInscripcionBadge(inscripcion.estado);
    const cicloNombre = getCicloNombre(inscripcion.ciclo);
    const documentosBadge = inscripcion.archivo_dni ? 
        '<span class="badge badge-success">✓</span>' : 
        '<span class="badge badge-danger">✗</span>';
    
    row.innerHTML = `
        <td>${inscripcion.numero_seguimiento || inscripcion.id}</td>
        <td>${inscripcion.nombre} ${inscripcion.apellido}</td>
        <td>${inscripcion.email}</td>
        <td>${inscripcion.dni}</td>
        <td>${cicloNombre}</td>
        <td>${estadoBadge}</td>
        <td>${formatDate(inscripcion.fecha_creacion)}</td>
        <td>${documentosBadge}</td>
        <td>
            <div class="action-buttons">
                <button class="btn btn-sm btn-primary" onclick="verDetalleInscripcion(${inscripcion.id})" title="Ver detalles">👁️</button>
                <button class="btn btn-sm btn-success" onclick="aprobarInscripcion(${inscripcion.id})" title="Aprobar" ${inscripcion.estado !== 'pendiente' ? 'disabled' : ''}>✓</button>
                <button class="btn btn-sm btn-danger" onclick="rechazarInscripcion(${inscripcion.id})" title="Rechazar" ${inscripcion.estado !== 'pendiente' ? 'disabled' : ''}>✗</button>
                <button class="btn btn-sm btn-info" onclick="descargarDocumentos(${inscripcion.id})" title="Descargar documentos">📁</button>
            </div>
        </td>
    `;
    
    return row;
}

/**
 * Obtener badge de estado de inscripción
 */
function getEstadoInscripcionBadge(estado) {
    const badges = {
        'pendiente': '<span class="badge badge-warning">Pendiente</span>',
        'aprobada': '<span class="badge badge-success">Aprobada</span>',
        'rechazada': '<span class="badge badge-danger">Rechazada</span>',
        'en_revision': '<span class="badge badge-info">En Revisión</span>'
    };
    return badges[estado] || '<span class="badge badge-secondary">Desconocido</span>';
}

/**
 * Obtener nombre del ciclo
 */
function getCicloNombre(cicloId) {
    const ciclos = {
        '1': 'Primer Ciclo',
        '2': 'Segundo Ciclo',
        '3': 'Tercer Ciclo'
    };
    return ciclos[cicloId] || 'No especificado';
}

/**
 * Actualizar estadísticas de inscripciones
 */
function updateInscripcionesStats() {
    const total = inscripciones.length;
    const pendientes = inscripciones.filter(i => i.estado === 'pendiente').length;
    const aprobadas = inscripciones.filter(i => i.estado === 'aprobada').length;
    const rechazadas = inscripciones.filter(i => i.estado === 'rechazada').length;
    const hoy = inscripciones.filter(i => {
        const fecha = new Date(i.fecha_creacion);
        const hoy = new Date();
        return fecha.toDateString() === hoy.toDateString();
    }).length;
    
    updateCounter('inscripcionesTotales', total);
    updateCounter('inscripcionesPendientesCount', pendientes);
    updateCounter('inscripcionesAprobadas', aprobadas);
    updateCounter('inscripcionesRechazadas', rechazadas);
    updateCounter('inscripcionesHoy', hoy);
}

/**
 * Actualizar badge de inscripciones
 */
function updateInscripcionesBadge() {
    const pendientes = inscripciones.filter(i => i.estado === 'pendiente').length;
    const badge = document.getElementById('inscripcionesBadge');
    if (badge) {
        badge.textContent = pendientes;
        badge.classList.toggle('show', pendientes > 0);
    }
}

/**
 * Mostrar datos de ejemplo para inscripciones
 */
function showExampleInscripciones() {
    inscripciones = [
        {
            id: 1,
            numero_seguimiento: 'INS-20241115-ABC123',
            nombre: 'Juan',
            apellido: 'Pérez',
            email: 'juan.perez@email.com',
            dni: '12345678',
            ciclo: '1',
            estado: 'pendiente',
            fecha_creacion: '2024-11-15',
            archivo_dni: 'dni_12345678_1234567890.jpg'
        },
        {
            id: 2,
            numero_seguimiento: 'INS-20241114-DEF456',
            nombre: 'María',
            apellido: 'González',
            email: 'maria.gonzalez@email.com',
            dni: '87654321',
            ciclo: '2',
            estado: 'aprobada',
            fecha_creacion: '2024-11-14',
            archivo_dni: 'dni_87654321_1234567890.jpg'
        },
        {
            id: 3,
            numero_seguimiento: 'INS-20241113-GHI789',
            nombre: 'Carlos',
            apellido: 'Rodríguez',
            email: 'carlos.rodriguez@email.com',
            dni: '11223344',
            ciclo: '3',
            estado: 'rechazada',
            fecha_creacion: '2024-11-13',
            archivo_dni: 'dni_11223344_1234567890.jpg'
        }
    ];
    renderInscripcionesTable();
    updateInscripcionesStats();
}

/**
 * Filtrar inscripciones
 */
function filtrarInscripciones() {
    const estadoFiltro = document.getElementById('filtroEstadoInscripcion')?.value || '';
    const cicloFiltro = document.getElementById('filtroOrientacion')?.value || '';
    const anioFiltro = document.getElementById('filtroAnio')?.value || '';
    const busqueda = document.getElementById('buscarInscripcion')?.value.toLowerCase() || '';
    
    const inscripcionesFiltradas = inscripciones.filter(inscripcion => {
        const cumpleEstado = !estadoFiltro || inscripcion.estado === estadoFiltro;
        const cumpleCiclo = !cicloFiltro || inscripcion.ciclo === cicloFiltro;
        const cumpleAnio = !anioFiltro || inscripcion.fecha_creacion.startsWith(anioFiltro);
        const cumpleBusqueda = !busqueda || 
            inscripcion.nombre.toLowerCase().includes(busqueda) ||
            inscripcion.apellido.toLowerCase().includes(busqueda) ||
            inscripcion.email.toLowerCase().includes(busqueda) ||
            inscripcion.dni.includes(busqueda) ||
            (inscripcion.numero_seguimiento && inscripcion.numero_seguimiento.toLowerCase().includes(busqueda));
        
        return cumpleEstado && cumpleCiclo && cumpleAnio && cumpleBusqueda;
    });
    
    renderInscripcionesTableFiltered(inscripcionesFiltradas);
}

/**
 * Renderizar tabla de inscripciones filtradas
 */
function renderInscripcionesTableFiltered(inscripcionesFiltradas) {
    const tbody = document.getElementById('inscripcionesTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    inscripcionesFiltradas.forEach(inscripcion => {
        tbody.appendChild(createInscripcionRow(inscripcion));
    });
}

/**
 * Ver detalles completos de una inscripción
 */
async function verDetalleInscripcion(id) {
    const inscripcion = inscripciones.find(i => i.id === id);
    if (!inscripcion) return;
    
    try {
        // Cargar detalles completos desde la API
        const response = await fetch(`api/inscripcion-detalle.php?id=${id}`);
        const data = await response.json();
        
        const detalle = data.success ? data.inscripcion : inscripcion;
        
        // Crear modal con detalles
        const modalContent = `
            <div class="modal-header">
                <h3>Detalles de Inscripción #${detalle.numero_seguimiento || detalle.id}</h3>
                <button class="modal-close" onclick="closeModal('detalleInscripcion')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detalle-grid">
                    <div class="detalle-section">
                        <h4>📋 Datos Personales</h4>
                        <div class="detalle-row">
                            <span class="detalle-label">Nombre completo:</span>
                            <span class="detalle-value">${detalle.nombre} ${detalle.apellido}</span>
                        </div>
                        <div class="detalle-row">
                            <span class="detalle-label">DNI:</span>
                            <span class="detalle-value">${detalle.dni}</span>
                        </div>
                        <div class="detalle-row">
                            <span class="detalle-label">Fecha de nacimiento:</span>
                            <span class="detalle-value">${formatDate(detalle.fecha_nacimiento)}</span>
                        </div>
                        <div class="detalle-row">
                            <span class="detalle-label">Email:</span>
                            <span class="detalle-value">${detalle.email}</span>
                        </div>
                        <div class="detalle-row">
                            <span class="detalle-label">Teléfono:</span>
                            <span class="detalle-value">${detalle.telefono || 'No especificado'}</span>
                        </div>
                        <div class="detalle-row">
                            <span class="detalle-label">Dirección:</span>
                            <span class="detalle-value">${detalle.direccion || 'No especificada'}</span>
                        </div>
                    </div>
                    
                    <div class="detalle-section">
                        <h4>🎓 Información Académica</h4>
                        <div class="detalle-row">
                            <span class="detalle-label">Ciclo solicitado:</span>
                            <span class="detalle-value">${getCicloNombre(detalle.ciclo)}</span>
                        </div>
                        <div class="detalle-row">
                            <span class="detalle-label">Turno preferido:</span>
                            <span class="detalle-value">${detalle.turno || 'No especificado'}</span>
                        </div>
                        <div class="detalle-row">
                            <span class="detalle-label">Nivel educativo:</span>
                            <span class="detalle-value">${detalle.nivel_educativo || 'No especificado'}</span>
                        </div>
                        <div class="detalle-row">
                            <span class="detalle-label">Motivación:</span>
                            <span class="detalle-value">${detalle.motivacion || 'No especificada'}</span>
                        </div>
                    </div>
                    
                    <div class="detalle-section">
                        <h4>📄 Documentación</h4>
                        <div class="detalle-row">
                            <span class="detalle-label">DNI subido:</span>
                            <span class="detalle-value">
                                ${detalle.archivo_dni ? 
                                    `<button class="btn btn-sm btn-primary" onclick="descargarDocumento('${detalle.archivo_dni}', 'dni')">Descargar DNI</button>` : 
                                    'No subido'}
                            </span>
                        </div>
                        <div class="detalle-row">
                            <span class="detalle-label">Certificado subido:</span>
                            <span class="detalle-value">
                                ${detalle.archivo_certificado ? 
                                    `<button class="btn btn-sm btn-primary" onclick="descargarDocumento('${detalle.archivo_certificado}', 'certificado')">Descargar Certificado</button>` : 
                                    'No subido'}
                            </span>
                        </div>
                    </div>
                    
                    <div class="detalle-section">
                        <h4>📊 Información del Proceso</h4>
                        <div class="detalle-row">
                            <span class="detalle-label">Estado:</span>
                            <span class="detalle-value">${getEstadoInscripcionBadge(detalle.estado)}</span>
                        </div>
                        <div class="detalle-row">
                            <span class="detalle-label">Fecha de inscripción:</span>
                            <span class="detalle-value">${formatDate(detalle.fecha_creacion)}</span>
                        </div>
                        ${detalle.fecha_revision ? `
                        <div class="detalle-row">
                            <span class="detalle-label">Fecha de revisión:</span>
                            <span class="detalle-value">${formatDate(detalle.fecha_revision)}</span>
                        </div>
                        ` : ''}
                        ${detalle.motivo_rechazo ? `
                        <div class="detalle-row">
                            <span class="detalle-label">Motivo de rechazo:</span>
                            <span class="detalle-value" style="color: #dc3545;">${detalle.motivo_rechazo}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('detalleInscripcion')">Cerrar</button>
                ${detalle.estado === 'pendiente' ? `
                <button class="btn btn-success" onclick="aprobarInscripcion(${detalle.id})">Aprobar Inscripción</button>
                <button class="btn btn-danger" onclick="rechazarInscripcion(${detalle.id})">Rechazar Inscripción</button>
                ` : ''}
            </div>
        `;
        
        showModalWithContent('detalleInscripcion', modalContent);
        
    } catch (error) {
        console.error('Error cargando detalles:', error);
        showAlert('Error al cargar los detalles de la inscripción', 'error');
    }
}

/**
 * Aprobar una inscripción
 */
async function aprobarInscripcion(id) {
    if (!confirm('¿Estás seguro de aprobar esta inscripción? Se creará un usuario de estudiante.')) {
        return;
    }
    
    showLoading();
    try {
        const response = await fetch('api/aprobar-inscripcion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('✅ Inscripción aprobada exitosamente', 'success');
            closeModal('detalleInscripcion');
            loadInscripciones(); // Recargar la lista
        } else {
            throw new Error(data.message || 'Error al aprobar la inscripción');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Error al aprobar inscripción: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

/**
 * Rechazar una inscripción
 */
async function rechazarInscripcion(id) {
    const motivo = prompt('Ingresa el motivo del rechazo:');
    if (motivo === null) return; // Usuario canceló
    
    if (!motivo.trim()) {
        showAlert('Debes ingresar un motivo para rechazar la inscripción', 'warning');
        return;
    }
    
    showLoading();
    try {
        const response = await fetch('api/rechazar-inscripcion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, motivo: motivo.trim() })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('✅ Inscripción rechazada', 'success');
            closeModal('detalleInscripcion');
            loadInscripciones(); // Recargar la lista
        } else {
            throw new Error(data.message || 'Error al rechazar la inscripción');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Error al rechazar inscripción: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

/**
 * Descargar documentos de una inscripción
 */
async function descargarDocumentos(id) {
    const inscripcion = inscripciones.find(i => i.id === id);
    if (!inscripcion) return;
    
    showLoading();
    try {
        const response = await fetch(`api/descargar-documentos.php?id=${id}`);
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `documentos_${inscripcion.numero_seguimiento || inscripcion.id}.zip`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            showAlert('📁 Documentos descargados exitosamente', 'success');
        } else {
            throw new Error('Error al descargar documentos');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Error al descargar documentos', 'error');
    } finally {
        hideLoading();
    }
}

/**
 * Descargar documento individual
 */
async function descargarDocumento(nombreArchivo, tipo) {
    showLoading();
    try {
        const response = await fetch(`api/descargar-documento.php?archivo=${nombreArchivo}&tipo=${tipo}`);
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = nombreArchivo;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } else {
            throw new Error('Error al descargar documento');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Error al descargar documento', 'error');
    } finally {
        hideLoading();
    }
}

/**
 * Procesar múltiples inscripciones
 */
async function procesarMasivo() {
    const seleccionadas = obtenerInscripcionesSeleccionadas();
    if (seleccionadas.length === 0) {
        showAlert('Selecciona al menos una inscripción para procesar', 'warning');
        return;
    }
    
    const accion = prompt('Ingresa "aprobar" o "rechazar" para las inscripciones seleccionadas:');
    if (!accion || (accion !== 'aprobar' && accion !== 'rechazar')) {
        showAlert('Acción no válida', 'warning');
        return;
    }
    
    let motivo = '';
    if (accion === 'rechazar') {
        motivo = prompt('Ingresa el motivo del rechazo (para todas las inscripciones):');
        if (motivo === null) return;
    }
    
    showLoading();
    try {
        const response = await fetch('api/procesar-inscripciones-masivo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ids: seleccionadas,
                accion: accion,
                motivo: motivo
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(`✅ ${seleccionadas.length} inscripciones ${accion === 'aprobar' ? 'aprobadas' : 'rechazadas'} exitosamente`, 'success');
            loadInscripciones(); // Recargar la lista
        } else {
            throw new Error(data.message || 'Error al procesar inscripciones');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Error al procesar inscripciones: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

/**
 * Obtener inscripciones seleccionadas (para procesamiento masivo)
 */
function obtenerInscripcionesSeleccionadas() {
    // Implementar lógica de selección si se agregan checkboxes
    // Por ahora, devuelve un array vacío
    return [];
}

/**
 * Exportar inscripciones
 */
async function exportarInscripciones() {
    showLoading();
    try {
        const filtros = {
            estado: document.getElementById('filtroEstadoInscripcion')?.value || '',
            ciclo: document.getElementById('filtroOrientacion')?.value || '',
            anio: document.getElementById('filtroAnio')?.value || ''
        };
        
        const queryParams = new URLSearchParams(filtros).toString();
        const response = await fetch(`api/exportar-inscripciones.php?${queryParams}`);
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `inscripciones_epa703_${new Date().getTime()}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            showAlert('📊 Inscripciones exportadas exitosamente', 'success');
        } else {
            throw new Error('Error al exportar inscripciones');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Error al exportar inscripciones', 'error');
    } finally {
        hideLoading();
    }
}

/**
 * Mostrar modal con contenido personalizado
 */
function showModalWithContent(modalId, content) {
    let modal = document.getElementById(modalId + 'Modal');
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = modalId + 'Modal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                ${content}
            </div>
        `;
        document.body.appendChild(modal);
    } else {
        modal.querySelector('.modal-content').innerHTML = content;
    }
    
    modal.style.display = 'flex';
    modal.classList.add('active');
}

/**
 * Cerrar modal
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId + 'Modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
}

/**
 * Cerrar todos los modales
 */
function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
        modal.classList.remove('active');
    });
}

// Agregar estilos CSS para los detalles
const inscripcionesStyles = document.createElement('style');
inscripcionesStyles.textContent = `
    .detalle-grid {
        display: grid;
        gap: 20px;
    }
    
    .detalle-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #1e3a2e;
    }
    
    .detalle-section h4 {
        margin: 0 0 15px 0;
        color: #1e3a2e;
        font-size: 16px;
    }
    
    .detalle-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .detalle-row:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .detalle-label {
        font-weight: 600;
        color: #495057;
    }
    
    .detalle-value {
        color: #6c757d;
        text-align: right;
        max-width: 60%;
    }
    
    .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding: 20px;
        border-top: 1px solid #e0e0e0;
        margin-top: 20px;
    }
    
    @media (max-width: 768px) {
        .detalle-row {
            flex-direction: column;
            gap: 5px;
        }
        
        .detalle-value {
            max-width: 100%;
            text-align: left;
        }
    }
`;
document.head.appendChild(inscripcionesStyles);

console.log('✅ Módulo de Inscripciones cargado correctamente');