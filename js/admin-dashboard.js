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
 * Cargar datos específicos de cada sección
 */
async function loadSectionData(sectionName) {
    switch (sectionName) {
        case 'dashboard':
            await loadDashboardData();
            break;
        case 'usuarios':
            await loadUsuarios();
            break;
        case 'consultas':
            await loadConsultas();
            break;
        case 'inscripciones':
            await loadInscripciones();
            break;
        case 'estudiantes':
            await loadEstudiantes();
            break;
        case 'profesores':
            await loadProfesores();
            break;
        case 'cursos':
            await loadCursos();
            break;
    }
}

/**
 * Cargar inscripciones
 */
async function loadInscripciones() {
    showLoading();
    
    try {
        const response = await fetch('api/inscripciones.php');
        const data = await response.json();
        
        if (data.success) {
            inscripciones = data.inscripciones;
            renderInscripcionesTable();
            
            // Cargar estadísticas
            const statsResponse = await fetch('api/inscripciones.php?stats=1');
            const statsData = await statsResponse.json();
            if (statsData.success) {
                updateInscripcionesStats(statsData.stats);
            }
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
        const row = createInscripcionRow(inscripcion);
        tbody.appendChild(row);
    });
}

/**
 * Crear fila de inscripción
 */
function createInscripcionRow(inscripcion) {
    const row = document.createElement('tr');
    
    const estadoBadge = getEstadoInscripcionBadge(inscripcion.estado_inscripcion || inscripcion.estado);
    const documentosBadge = inscripcion.documentos_completos ? 
        '<span class="docs-completos">✓ Completos</span>' : 
        '<span class="docs-incompletos">⚠ Pendientes</span>';
    
    row.innerHTML = `
        <td>${inscripcion.id}</td>
        <td>${inscripcion.nombre}</td>
        <td>${inscripcion.email}</td>
        <td>${inscripcion.dni || '-'}</td>
        <td>${inscripcion.orientacion_deseada || 'General'}</td>
        <td>${estadoBadge}</td>
        <td>${formatDate(inscripcion.created_at)}</td>
        <td>${documentosBadge}</td>
        <td>
            <div class="action-buttons">
                <button class="btn btn-sm btn-primary" onclick="verInscripcion(${inscripcion.id})" title="Ver">
                    👁️
                </button>
                <button class="btn btn-sm btn-success" onclick="aprobarInscripcion(${inscripcion.id})" title="Aprobar">
                    ✅
                </button>
                <button class="btn btn-sm btn-danger" onclick="rechazarInscripcion(${inscripcion.id})" title="Rechazar">
                    ❌
                </button>
            </div>
        </td>
    `;
    
    return row;
}

/**
 * Cargar estudiantes
 */
async function loadEstudiantes() {
    showLoading();
    
    try {
        const response = await fetch('api/estudiantes.php');
        const data = await response.json();
        
        if (data.success) {
            estudiantes = data.estudiantes;
            renderEstudiantesTable();
            
            // Cargar estadísticas
            const statsResponse = await fetch('api/estudiantes.php?stats=1');
            const statsData = await statsResponse.json();
            if (statsData.success) {
                updateEstudiantesStats(statsData.stats);
            }
        } else {
            console.error('Error cargando estudiantes:', data.error);
            showExampleEstudiantes();
        }
        
    } catch (error) {
        console.error('Error:', error);
        showExampleEstudiantes();
    } finally {
        hideLoading();
    }
}

/**
 * Renderizar tabla de estudiantes
 */
function renderEstudiantesTable() {
    const tbody = document.getElementById('estudiantesTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    estudiantes.forEach(estudiante => {
        const row = createEstudianteRow(estudiante);
        tbody.appendChild(row);
    });
}

/**
 * Crear fila de estudiante
 */
function createEstudianteRow(estudiante) {
    const row = document.createElement('tr');
    
    const estadoBadge = getEstadoEstudianteBadge(estudiante.estado);
    
    row.innerHTML = `
        <td>${estudiante.legajo}</td>
        <td>${estudiante.nombre} ${estudiante.apellido}</td>
        <td>${estudiante.email}</td>
        <td>${estudiante.curso_nombre || '-'}</td>
        <td>${estudiante.orientacion_nombre || '-'}</td>
        <td>${estadoBadge}</td>
        <td>${formatDate(estudiante.fecha_ingreso)}</td>
        <td>
            <div class="action-buttons">
                <button class="btn btn-sm btn-primary" onclick="verEstudiante(${estudiante.usuario_id})" title="Ver">
                    👁️
                </button>
                <button class="btn btn-sm btn-warning" onclick="editarEstudiante(${estudiante.usuario_id})" title="Editar">
                    ✏️
                </button>
                <button class="btn btn-sm btn-info" onclick="verNotas(${estudiante.usuario_id})" title="Notas">
                    📊
                </button>
            </div>
        </td>
    `;
    
    return row;
}

/**
 * Cargar profesores
 */
async function loadProfesores() {
    showLoading();
    
    try {
        const response = await fetch('api/profesores.php');
        const data = await response.json();
        
        if (data.success) {
            profesores = data.profesores;
            renderProfesoresTable();
            
            // Cargar estadísticas
            const statsResponse = await fetch('api/profesores.php?stats=1');
            const statsData = await statsResponse.json();
            if (statsData.success) {
                updateProfesoresStats(statsData.stats);
            }
        } else {
            console.error('Error cargando profesores:', data.error);
            showExampleProfesores();
        }
        
    } catch (error) {
        console.error('Error:', error);
        showExampleProfesores();
    } finally {
        hideLoading();
    }
}

/**
 * Renderizar tabla de profesores
 */
function renderProfesoresTable() {
    const tbody = document.getElementById('profesoresTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    profesores.forEach(profesor => {
        const row = createProfesorRow(profesor);
        tbody.appendChild(row);
    });
}

/**
 * Crear fila de profesor
 */
function createProfesorRow(profesor) {
    const row = document.createElement('tr');
    
    const estadoBadge = getEstadoProfesorBadge(profesor.estado);
    
    row.innerHTML = `
        <td>${profesor.legajo}</td>
        <td>${profesor.nombre} ${profesor.apellido}</td>
        <td>${profesor.email}</td>
        <td>${profesor.especialidad || '-'}</td>
        <td>${profesor.titulo || '-'}</td>
        <td>${estadoBadge}</td>
        <td>${formatDate(profesor.fecha_ingreso)}</td>
        <td>${profesor.carga_horaria || '-'} hs</td>
        <td>
            <div class="action-buttons">
                <button class="btn btn-sm btn-primary" onclick="verProfesor(${profesor.usuario_id})" title="Ver">
                    👁️
                </button>
                <button class="btn btn-sm btn-warning" onclick="editarProfesor(${profesor.usuario_id})" title="Editar">
                    ✏️
                </button>
                <button class="btn btn-sm btn-info" onclick="asignarMaterias(${profesor.usuario_id})" title="Materias">
                    📚
                </button>
            </div>
        </td>
    `;
    
    return row;
}

/**
 * Cargar cursos
 */
async function loadCursos() {
    showLoading();
    
    try {
        const response = await fetch('api/cursos.php');
        const data = await response.json();
        
        if (data.success) {
            cursos = data.cursos;
            renderCursosGrid();
        } else {
            console.error('Error cargando cursos:', data.error);
            showExampleCursos();
        }
        
    } catch (error) {
        console.error('Error:', error);
        showExampleCursos();
    } finally {
        hideLoading();
    }
}

/**
 * Renderizar grid de cursos
 */
function renderCursosGrid() {
    const grid = document.getElementById('cursosGrid');
    if (!grid) return;
    
    grid.innerHTML = '';
    
    cursos.forEach(curso => {
        const card = createCursoCard(curso);
        grid.appendChild(card);
    });
}

/**
 * Crear tarjeta de curso
 */
function createCursoCard(curso) {
    const card = document.createElement('div');
    card.className = 'curso-card';
    
    const estadoClass = curso.activo ? 'estado-activo' : 'estado-inactivo';
    
    card.innerHTML = `
        <div class="curso-header">
            <h3>${curso.nombre}</h3>
            <div class="curso-meta">
                <span>Año: ${curso.anio}</span>
                <span>División: ${curso.division}</span>
                <span>Turno: ${curso.turno}</span>
            </div>
        </div>
        <div class="curso-body">
            <div class="curso-info">
                <div class="curso-estudiantes">
                    <span>👥</span>
                    <span>${curso.cantidad_estudiantes} estudiantes</span>
                </div>
                <span class="badge ${estadoClass}">
                    ${curso.activo ? 'Activo' : 'Inactivo'}
                </span>
            </div>
            
            <div class="curso-profesor">
                <strong>Tutor:</strong>
                <span>${curso.tutor_nombre || 'Sin asignar'}</span>
            </div>
            
            <div class="curso-acciones">
                <button class="btn btn-sm btn-primary" onclick="verCurso(${curso.id})" title="Ver detalles">
                    👁️ Ver
                </button>
                <button class="btn btn-sm btn-warning" onclick="editarCurso(${curso.id})" title="Editar">
                    ✏️ Editar
                </button>
                <button class="btn btn-sm btn-info" onclick="gestionarMaterias(${curso.id})" title="Materias">
                    📚 Materias
                </button>
            </div>
        </div>
    `;
    
    return card;
}

/**
 * Actualizar estadísticas de inscripciones
 */
function updateInscripcionesStats(stats) {
    updateCounter('inscripcionesTotales', stats.total || 0);
    updateCounter('inscripcionesPendientesCount', stats.pendientes || 0);
    updateCounter('inscripcionesAprobadas', stats.aprobadas || 0);
    updateCounter('inscripcionesRechazadas', stats.rechazadas || 0);
    updateCounter('inscripcionesHoy', stats.hoy || 0);
}

/**
 * Actualizar estadísticas de estudiantes
 */
function updateEstudiantesStats(stats) {
    updateCounter('estudiantesTotales', stats.total || 0);
    updateCounter('estudiantesActivos', stats.activos || 0);
    updateCounter('estudiantesGraduados', stats.graduados || 0);
    updateCounter('estudiantesNuevos', stats.nuevos_mes || 0);
}

/**
 * Actualizar estadísticas de profesores
 */
function updateProfesoresStats(stats) {
    updateCounter('profesoresTotales', stats.total || 0);
    updateCounter('profesoresActivos', stats.activos || 0);
    updateCounter('profesoresLicencia', stats.licencia || 0);
    updateCounter('profesoresNuevos', stats.nuevos_mes || 0);
}

/**
 * Funciones para obtener badges de estado
 */
function getEstadoInscripcionBadge(estado) {
    const badges = {
        'pendiente': '<span class="badge estado-pendiente">Pendiente</span>',
        'aprobada': '<span class="badge estado-aprobada">Aprobada</span>',
        'rechazada': '<span class="badge estado-rechazada">Rechazada</span>',
        'en_revision': '<span class="badge estado-en_revision">En Revisión</span>'
    };
    
    return badges[estado] || '<span class="badge badge-secondary">Desconocido</span>';
}

function getEstadoEstudianteBadge(estado) {
    const badges = {
        'activo': '<span class="badge estado-activo">Activo</span>',
        'inactivo': '<span class="badge estado-inactivo">Inactivo</span>',
        'graduado': '<span class="badge estado-graduado">Graduado</span>',
        'desertor': '<span class="badge estado-desertor">Desertor</span>'
    };
    
    return badges[estado] || '<span class="badge badge-secondary">Desconocido</span>';
}

function getEstadoProfesorBadge(estado) {
    const badges = {
        'activo': '<span class="badge estado-activo">Activo</span>',
        'licencia': '<span class="badge estado-licencia">Licencia</span>',
        'inactivo': '<span class="badge estado-inactivo">Inactivo</span>'
    };
    
    return badges[estado] || '<span class="badge badge-secondary">Desconocido</span>';
}

/**
 * Funciones de filtrado
 */
function filtrarInscripciones() {
    const estadoFiltro = document.getElementById('filtroEstadoInscripcion').value;
    const orientacionFiltro = document.getElementById('filtroOrientacion').value;
    const anioFiltro = document.getElementById('filtroAnio').value;
    const busqueda = document.getElementById('buscarInscripcion').value.toLowerCase();
    
    const inscripcionesFiltradas = inscripciones.filter(inscripcion => {
        const cumpleEstado = !estadoFiltro || inscripcion.estado === estadoFiltro;
        const cumpleOrientacion = !orientacionFiltro || inscripcion.orientacion_deseada === orientacionFiltro;
        const cumpleAnio = !anioFiltro || inscripcion.año_ingreso == anioFiltro;
        const cumpleBusqueda = !busqueda || 
            inscripcion.nombre.toLowerCase().includes(busqueda) ||
            inscripcion.email.toLowerCase().includes(busqueda) ||
            (inscripcion.dni && inscripcion.dni.includes(busqueda));
        
        return cumpleEstado && cumpleOrientacion && cumpleAnio && cumpleBusqueda;
    });
    
    renderInscripcionesTableFiltered(inscripcionesFiltradas);
}

function filtrarEstudiantes() {
    const estadoFiltro = document.getElementById('filtroEstadoEstudiante').value;
    const cursoFiltro = document.getElementById('filtroCurso').value;
    const orientacionFiltro = document.getElementById('filtroOrientacionEst').value;
    const busqueda = document.getElementById('buscarEstudiante').value.toLowerCase();
    
    const estudiantesFiltrados = estudiantes.filter(estudiante => {
        const cumpleEstado = !estadoFiltro || estudiante.estado === estadoFiltro;
        const cumpleCurso = !cursoFiltro || estudiante.curso_id == cursoFiltro;
        const cumpleOrientacion = !orientacionFiltro || estudiante.orientacion_id == orientacionFiltro;
        const cumpleBusqueda = !busqueda || 
            estudiante.nombre.toLowerCase().includes(busqueda) ||
            estudiante.apellido.toLowerCase().includes(busqueda) ||
            estudiante.email.toLowerCase().includes(busqueda) ||
            estudiante.legajo.toLowerCase().includes(busqueda);
        
        return cumpleEstado && cumpleCurso && cumpleOrientacion && cumpleBusqueda;
    });
    
    renderEstudiantesTableFiltered(estudiantesFiltrados);
}

function filtrarProfesores() {
    const estadoFiltro = document.getElementById('filtroEstadoProfesor').value;
    const especialidadFiltro = document.getElementById('filtroEspecialidad').value;
    const busqueda = document.getElementById('buscarProfesor').value.toLowerCase();
    
    const profesoresFiltrados = profesores.filter(profesor => {
        const cumpleEstado = !estadoFiltro || profesor.estado === estadoFiltro;
        const cumpleEspecialidad = !especialidadFiltro || 
            (profesor.especialidad && profesor.especialidad.toLowerCase().includes(especialidadFiltro.toLowerCase()));
        const cumpleBusqueda = !busqueda || 
            profesor.nombre.toLowerCase().includes(busqueda) ||
            profesor.apellido.toLowerCase().includes(busqueda) ||
            profesor.legajo.toLowerCase().includes(busqueda) ||
            (profesor.especialidad && profesor.especialidad.toLowerCase().includes(busqueda));
        
        return cumpleEstado && cumpleEspecialidad && cumpleBusqueda;
    });
    
    renderProfesoresTableFiltered(profesoresFiltrados);
}

/**
 * Funciones de renderizado filtrado
 */
function renderInscripcionesTableFiltered(inscripcionesFiltradas) {
    const tbody = document.getElementById('inscripcionesTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    inscripcionesFiltradas.forEach(inscripcion => {
        const row = createInscripcionRow(inscripcion);
        tbody.appendChild(row);
    });
}

function renderEstudiantesTableFiltered(estudiantesFiltrados) {
    const tbody = document.getElementById('estudiantesTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    estudiantesFiltrados.forEach(estudiante => {
        const row = createEstudianteRow(estudiante);
        tbody.appendChild(row);
    });
}

function renderProfesoresTableFiltered(profesoresFiltrados) {
    const tbody = document.getElementById('profesoresTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    profesoresFiltrados.forEach(profesor => {
        const row = createProfesorRow(profesor);
        tbody.appendChild(row);
    });
}

/**
 * Funciones de datos de ejemplo
 */
function showExampleInscripciones() {
    inscripciones = [
        {
            id: 1,
            nombre: 'Ana García',
            email: 'ana.garcia@gmail.com',
            dni: '25123456',
            orientacion_deseada: 'primer_ciclo',
            estado: 'pendiente',
            documentos_completos: false,
            created_at: '2024-08-15',
            año_ingreso: 2024
        },
        {
            id: 2,
            nombre: 'Carlos López',
            email: 'carlos.lopez@hotmail.com',
            dni: '30987654',
            orientacion_deseada: 'segundo_ciclo',
            estado: 'aprobada',
            documentos_completos: true,
            created_at: '2024-08-10',
            año_ingreso: 2024
        }
    ];
    
    const stats = {
        total: 2,
        pendientes: 1,
        aprobadas: 1,
        rechazadas: 0,
        hoy: 0
    };
    
    updateInscripcionesStats(stats);
    renderInscripcionesTable();
}

function showExampleEstudiantes() {
    estudiantes = [
        {
            usuario_id: 1,
            legajo: 'EST2024001',
            nombre: 'Juan',
            apellido: 'Pérez',
            email: 'juan.perez@epa703.edu.ar',
            curso_nombre: 'Primer Ciclo - Turno Tarde',
            orientacion_nombre: 'Primer Ciclo',
            estado: 'activo',
            fecha_ingreso: '2024-03-01'
        },
        {
            usuario_id: 2,
            legajo: 'EST2024002',
            nombre: 'María',
            apellido: 'González',
            email: 'maria.gonzalez@epa703.edu.ar',
            curso_nombre: 'Segundo Ciclo - Turno Noche',
            orientacion_nombre: 'Segundo Ciclo',
            estado: 'activo',
            fecha_ingreso: '2024-03-15'
        }
    ];
    
    const stats = {
        total: 2,
        activos: 2,
        inactivos: 0,
        graduados: 0,
        nuevos_mes: 1
    };
    
    updateEstudiantesStats(stats);
    renderEstudiantesTable();
}

function showExampleProfesores() {
    profesores = [
        {
            usuario_id: 3,
            legajo: 'PROF2024001',
            nombre: 'Ana',
            apellido: 'Martínez',
            email: 'ana.martinez@epa703.edu.ar',
            especialidad: 'Lengua y Literatura',
            titulo: 'Profesora en Letras',
            estado: 'activo',
            fecha_ingreso: '2024-02-01',
            carga_horaria: 20
        },
        {
            usuario_id: 4,
            legajo: 'PROF2024002',
            nombre: 'Roberto',
            apellido: 'Silva',
            email: 'roberto.silva@epa703.edu.ar',
            especialidad: 'Matemática',
            titulo: 'Profesor en Matemática',
            estado: 'activo',
            fecha_ingreso: '2024-02-01',
            carga_horaria: 25
        }
    ];
    
    const stats = {
        total: 2,
        activos: 2,
        licencia: 0,
        inactivos: 0,
        nuevos_mes: 0
    };
    
    updateProfesoresStats(stats);
    renderProfesoresTable();
}

function showExampleCursos() {
    cursos = [
        {
            id: 1,
            nombre: 'Primer Ciclo - Turno Tarde',
            anio: 1,
            division: 'A',
            turno: 'tarde',
            orientacion_nombre: 'Primer Ciclo',
            tutor_nombre: 'Ana Martínez',
            cantidad_estudiantes: 15,
            activo: true
        },
        {
            id: 2,
            nombre: 'Segundo Ciclo - Turno Noche',
            anio: 1,
            division: 'B',
            turno: 'noche',
            orientacion_nombre: 'Segundo Ciclo',
            tutor_nombre: 'Roberto Silva',
            cantidad_estudiantes: 12,
            activo: true
        }
    ];
    
    renderCursosGrid();
}

/**
 * Funciones de acciones específicas
 */

// Inscripciones
function verInscripcion(id) {
    showAlert('Ver inscripción - Función en desarrollo', 'info');
}

function aprobarInscripcion(id) {
    if (confirm('¿Aprobar esta inscripción?')) {
        showAlert('Inscripción aprobada - Función en desarrollo', 'success');
    }
}

function rechazarInscripcion(id) {
    if (confirm('¿Rechazar esta inscripción?')) {
        showAlert('Inscripción rechazada - Función en desarrollo', 'warning');
    }
}

function exportarInscripciones() {
    showAlert('Exportar inscripciones - Función en desarrollo', 'info');
}

function procesarMasivo() {
    showAlert('Procesamiento masivo - Función en desarrollo', 'info');
}

// Estudiantes
function verEstudiante(id) {
    showAlert('Ver estudiante - Función en desarrollo', 'info');
}

function editarEstudiante(id) {
    showAlert('Editar estudiante - Función en desarrollo', 'info');
}

function verNotas(id) {
    showAlert('Ver notas - Función en desarrollo', 'info');
}

function exportarEstudiantes() {
    showAlert('Exportar estudiantes - Función en desarrollo', 'info');
}

// Profesores
function verProfesor(id) {
    showAlert('Ver profesor - Función en desarrollo', 'info');
}

function editarProfesor(id) {
    showAlert('Editar profesor - Función en desarrollo', 'info');
}

function asignarMaterias(id) {
    showAlert('Asignar materias - Función en desarrollo', 'info');
}

function exportarProfesores() {
    showAlert('Exportar profesores - Función en desarrollo', 'info');
}

// Cursos
function verCurso(id) {
    showAlert('Ver curso - Función en desarrollo', 'info');
}

function editarCurso(id) {
    showAlert('Editar curso - Función en desarrollo', 'info');
}

function gestionarMaterias(id) {
    showAlert('Gestionar materias - Función en desarrollo', 'info');
}/**
 * EPA 703 - Admin Dashboard JavaScript
 * Manejo de la interfaz de administración
 */

// Variables globales
let currentSection = 'dashboard';
let usuarios = [];
let consultas = [];
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
    
    // Mostrar sección inicial
    showSection('dashboard');
    
    // Configurar sidebar en móvil
    setupMobileSidebar();
    
    // Cargar información del usuario
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
        
        // Actualizar información del usuario en la interfaz
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
    // Información almacenada en sessionStorage o localStorage
    const userInfo = JSON.parse(sessionStorage.getItem('userInfo') || '{}');
    
    if (userInfo.nombre) {
        updateUserInterface(userInfo);
    }
}

/**
 * Actualizar interfaz con información del usuario
 */
function updateUserInterface(user) {
    const userName = document.getElementById('userName');
    const userInitials = document.getElementById('userInitials');
    
    if (userName) {
        userName.textContent = `${user.nombre} ${user.apellido}`;
    }
    
    if (userInitials) {
        const initials = (user.nombre?.charAt(0) || '') + (user.apellido?.charAt(0) || '');
        userInitials.textContent = initials.toUpperCase();
    }
}

/**
 * Configurar eventos
 */
function setupEventListeners() {
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Cerrar modales al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
    
    // Tecla ESC para cerrar modales
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

/**
 * Configurar sidebar móvil
 */
function setupMobileSidebar() {
    // Crear overlay para móvil
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.addEventListener('click', closeSidebar);
    document.body.appendChild(overlay);
}

/**
 * Mostrar/ocultar sidebar
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    sidebarOpen = !sidebarOpen;
    
    if (sidebarOpen) {
        sidebar.classList.add('show');
        overlay.classList.add('show');
    } else {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    }
}

/**
 * Cerrar sidebar
 */
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    sidebarOpen = false;
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
}

/**
 * Mostrar sección específica
 */
function showSection(sectionName) {
    // Ocultar todas las secciones
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remover clase active de todos los nav-links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Mostrar sección seleccionada
    const targetSection = document.getElementById(sectionName);
    if (targetSection) {
        targetSection.classList.add('active');
    }
    
    // Activar nav-link correspondiente
    const navLink = document.querySelector(`[onclick="showSection('${sectionName}')"]`);
    if (navLink) {
        navLink.classList.add('active');
    }
    
    currentSection = sectionName;
    
    // Cargar datos específicos de la sección
    loadSectionData(sectionName);
    
    // Cerrar sidebar en móvil
    if (window.innerWidth <= 1024) {
        closeSidebar();
    }
}

/**
 * Cargar datos específicos de cada sección
 */
async function loadSectionData(sectionName) {
    switch (sectionName) {
        case 'dashboard':
            await loadDashboardData();
            break;
        case 'usuarios':
            await loadUsuarios();
            break;
        case 'consultas':
            await loadConsultas();
            break;
        case 'inscripciones':
            // await loadInscripciones();
            break;
        case 'estudiantes':
            // await loadEstudiantes();
            break;
        case 'profesores':
            // await loadProfesores();
            break;
    }
}

/**
 * Cargar datos del dashboard
 */
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
            // Mostrar datos de ejemplo si hay error
            showExampleDashboardData();
        }
        
    } catch (error) {
        console.error('Error:', error);
        showExampleDashboardData();
    } finally {
        hideLoading();
    }
}

/**
 * Actualizar estadísticas del dashboard
 */
function updateDashboardStats(stats) {
    if (!stats) return;
    
    // Actualizar contadores
    updateCounter('totalUsuarios', stats.total_usuarios || 0);
    updateCounter('totalEstudiantes', stats.total_estudiantes || 0);
    updateCounter('consultasPendientes', stats.consultas_pendientes || 0);
    updateCounter('inscripcionesNuevas', stats.inscripciones_nuevas || 0);
    
    // Actualizar badges en el sidebar
    updateBadge('consultasBadge', stats.consultas_pendientes || 0);
    updateBadge('inscripcionesBadge', stats.inscripciones_nuevas || 0);
}

/**
 * Actualizar contador con animación
 */
function updateCounter(elementId, value) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const startValue = parseInt(element.textContent) || 0;
    const duration = 1000; // 1 segundo
    const startTime = performance.now();
    
    function animate(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function (ease out)
        const easedProgress = 1 - Math.pow(1 - progress, 3);
        
        const currentValue = Math.round(startValue + (value - startValue) * easedProgress);
        element.textContent = currentValue;
        
        if (progress < 1) {
            requestAnimationFrame(animate);
        }
    }
    
    requestAnimationFrame(animate);
}

/**
 * Actualizar badge
 */
function updateBadge(elementId, value) {
    const badge = document.getElementById(elementId);
    if (!badge) return;
    
    badge.textContent = value;
    
    if (value > 0) {
        badge.classList.add('show');
    } else {
        badge.classList.remove('show');
    }
}

/**
 * Mostrar datos de ejemplo del dashboard
 */
function showExampleDashboardData() {
    const exampleStats = {
        total_usuarios: 127,
        total_estudiantes: 98,
        consultas_pendientes: 5,
        inscripciones_nuevas: 12
    };
    
    updateDashboardStats(exampleStats);
}

/**
 * Cargar usuarios
 */
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

/**
 * Renderizar tabla de usuarios
 */
function renderUsuariosTable() {
    const tbody = document.getElementById('usuariosTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    usuarios.forEach(usuario => {
        const row = createUsuarioRow(usuario);
        tbody.appendChild(row);
    });
}

/**
 * Crear fila de usuario
 */
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
                <button class="btn btn-sm btn-primary" onclick="editarUsuario(${usuario.id})" title="Editar">
                    ✏️
                </button>
                <button class="btn btn-sm btn-warning" onclick="toggleUsuarioEstado(${usuario.id})" title="Activar/Desactivar">
                    ${usuario.activo ? '🔒' : '🔓'}
                </button>
                <button class="btn btn-sm btn-danger" onclick="eliminarUsuario(${usuario.id})" title="Eliminar">
                    🗑️
                </button>
            </div>
        </td>
    `;
    
    return row;
}

/**
 * Obtener badge del tipo de usuario
 */
function getTipoBadge(tipo) {
    const badges = {
        'admin': '<span class="badge badge-danger">Admin</span>',
        'profesor': '<span class="badge badge-info">Profesor</span>',
        'estudiante': '<span class="badge badge-success">Estudiante</span>',
        'secretario': '<span class="badge badge-warning">Secretario</span>'
    };
    
    return badges[tipo] || '<span class="badge badge-secondary">Desconocido</span>';
}

/**
 * Mostrar usuarios de ejemplo
 */
function showExampleUsuarios() {
    usuarios = [
        {
            id: 1,
            nombre: 'Juan',
            apellido: 'Pérez',
            email: 'juan.perez@epa703.edu.ar',
            tipo_usuario: 'estudiante',
            activo: 1,
            fecha_registro: '2024-01-15'
        },
        {
            id: 2,
            nombre: 'María',
            apellido: 'González',
            email: 'maria.gonzalez@epa703.edu.ar',
            tipo_usuario: 'profesor',
            activo: 1,
            fecha_registro: '2024-01-10'
        },
        {
            id: 3,
            nombre: 'Carlos',
            apellido: 'Rodríguez',
            email: 'carlos.rodriguez@epa703.edu.ar',
            tipo_usuario: 'admin',
            activo: 1,
            fecha_registro: '2024-01-05'
        }
    ];
    
    renderUsuariosTable();
}

/**
 * Filtrar usuarios
 */
function filtrarUsuarios() {
    const tipoFiltro = document.getElementById('filtroTipoUsuario').value;
    const estadoFiltro = document.getElementById('filtroEstado').value;
    const busqueda = document.getElementById('buscarUsuario').value.toLowerCase();
    
    const usuariosFiltrados = usuarios.filter(usuario => {
        const cumpleTipo = !tipoFiltro || usuario.tipo_usuario === tipoFiltro;
        const cumpleEstado = estadoFiltro === '' || usuario.activo.toString() === estadoFiltro;
        const cumpleBusqueda = !busqueda || 
            usuario.nombre.toLowerCase().includes(busqueda) ||
            usuario.apellido.toLowerCase().includes(busqueda) ||
            usuario.email.toLowerCase().includes(busqueda);
        
        return cumpleTipo && cumpleEstado && cumpleBusqueda;
    });
    
    renderUsuariosTableFiltered(usuariosFiltrados);
}

/**
 * Renderizar tabla filtrada
 */
function renderUsuariosTableFiltered(usuariosFiltrados) {
    const tbody = document.getElementById('usuariosTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    usuariosFiltrados.forEach(usuario => {
        const row = createUsuarioRow(usuario);
        tbody.appendChild(row);
    });
}

/**
 * Cargar consultas
 */
async function loadConsultas() {
    showLoading();
    
    try {
        const response = await fetch('api/consultas.php');
        const data = await response.json();
        
        if (data.success) {
            consultas = data.consultas;
            updateConsultasStats(data.stats);
            renderConsultasTable();
        } else {
            console.error('Error cargando consultas:', data.error);
            showExampleConsultas();
        }
        
    } catch (error) {
        console.error('Error:', error);
        showExampleConsultas();
    } finally {
        hideLoading();
    }
}

/**
 * Actualizar estadísticas de consultas
 */
function updateConsultasStats(stats) {
    if (!stats) return;
    
    document.getElementById('consultasTotales').textContent = stats.total || 0;
    document.getElementById('consultasPendientesCount').textContent = stats.pendientes || 0;
    document.getElementById('consultasRespondidas').textContent = stats.respondidas || 0;
    document.getElementById('consultasHoy').textContent = stats.hoy || 0;
}

/**
 * Renderizar tabla de consultas
 */
function renderConsultasTable() {
    const tbody = document.getElementById('consultasTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    consultas.forEach(consulta => {
        const row = createConsultaRow(consulta);
        tbody.appendChild(row);
    });
}

/**
 * Crear fila de consulta
 */
function createConsultaRow(consulta) {
    const row = document.createElement('tr');
    
    const estadoBadge = getEstadoBadge(consulta.estado);
    const prioridadBadge = getPrioridadBadge(consulta.prioridad);
    const tipoBadge = getTipoConsultaBadge(consulta.tipo_consulta);
    
    row.innerHTML = `
        <td>${consulta.id}</td>
        <td>${consulta.nombre}</td>
        <td>${consulta.email}</td>
        <td title="${consulta.mensaje}">${truncateText(consulta.asunto, 30)}</td>
        <td>${tipoBadge}</td>
        <td>${prioridadBadge}</td>
        <td>${estadoBadge}</td>
        <td>${formatDate(consulta.created_at)}</td>
        <td>
            <div class="action-buttons">
                <button class="btn btn-sm btn-primary" onclick="verConsulta(${consulta.id})" title="Ver">
                    👁️
                </button>
                <button class="btn btn-sm btn-success" onclick="responderConsulta(${consulta.id})" title="Responder">
                    💬
                </button>
                <button class="btn btn-sm btn-warning" onclick="cambiarPrioridad(${consulta.id})" title="Prioridad">
                    ⚡
                </button>
            </div>
        </td>
    `;
    
    return row;
}

/**
 * Obtener badge de estado
 */
function getEstadoBadge(estado) {
    const badges = {
        'pendiente': '<span class="badge badge-warning">Pendiente</span>',
        'respondida': '<span class="badge badge-success">Respondida</span>',
        'cerrada': '<span class="badge badge-secondary">Cerrada</span>'
    };
    
    return badges[estado] || '<span class="badge badge-secondary">Desconocido</span>';
}

/**
 * Obtener badge de prioridad
 */
function getPrioridadBadge(prioridad) {
    const badges = {
        'urgente': '<span class="badge badge-danger">Urgente</span>',
        'alta': '<span class="badge badge-warning">Alta</span>',
        'media': '<span class="badge badge-info">Media</span>',
        'baja': '<span class="badge badge-secondary">Baja</span>'
    };
    
    return badges[prioridad] || '<span class="badge badge-secondary">Normal</span>';
}

/**
 * Obtener badge de tipo de consulta
 */
function getTipoConsultaBadge(tipo) {
    const badges = {
        'general': '<span class="badge badge-info">General</span>',
        'inscripcion': '<span class="badge badge-success">Inscripción</span>',
        'academica': '<span class="badge badge-warning">Académica</span>',
        'administrativa': '<span class="badge badge-secondary">Admin</span>'
    };
    
    return badges[tipo] || '<span class="badge badge-secondary">General</span>';
}

/**
 * Mostrar consultas de ejemplo
 */
function showExampleConsultas() {
    consultas = [
        {
            id: 1,
            nombre: 'Ana Martínez',
            email: 'ana.martinez@gmail.com',
            asunto: 'Consulta sobre horarios de inscripción',
            mensaje: 'Hola, quisiera saber cuáles son los horarios disponibles para inscribirme...',
            tipo_consulta: 'inscripcion',
            estado: 'pendiente',
            prioridad: 'media',
            created_at: '2024-08-20 10:30:00'
        },
        {
            id: 2,
            nombre: 'Roberto Silva',
            email: 'roberto.silva@hotmail.com',
            asunto: 'Información sobre materias disponibles',
            mensaje: 'Buenos días, me gustaría conocer qué materias están disponibles...',
            tipo_consulta: 'academica',
            estado: 'respondida',
            prioridad: 'baja',
            created_at: '2024-08-19 15:45:00'
        },
        {
            id: 3,
            nombre: 'Laura Fernández',
            email: 'laura.fernandez@yahoo.com',
            asunto: 'Urgente: Problema con certificado',
            mensaje: 'Necesito urgentemente mi certificado para presentar en el trabajo...',
            tipo_consulta: 'administrativa',
            estado: 'pendiente',
            prioridad: 'urgente',
            created_at: '2024-08-20 09:15:00'
        }
    ];
    
    const stats = {
        total: 3,
        pendientes: 2,
        respondidas: 1,
        hoy: 2
    };
    
    updateConsultasStats(stats);
    renderConsultasTable();
}

/**
 * Filtrar consultas
 */
function filtrarConsultas() {
    const estadoFiltro = document.getElementById('filtroEstadoConsulta').value;
    const tipoFiltro = document.getElementById('filtroTipoConsulta').value;
    const prioridadFiltro = document.getElementById('filtroPrioridad').value;
    
    const consultasFiltradas = consultas.filter(consulta => {
        const cumpleEstado = !estadoFiltro || consulta.estado === estadoFiltro;
        const cumpleTipo = !tipoFiltro || consulta.tipo_consulta === tipoFiltro;
        const cumplePrioridad = !prioridadFiltro || consulta.prioridad === prioridadFiltro;
        
        return cumpleEstado && cumpleTipo && cumplePrioridad;
    });
    
    renderConsultasTableFiltered(consultasFiltradas);
}

/**
 * Renderizar tabla de consultas filtrada
 */
function renderConsultasTableFiltered(consultasFiltradas) {
    const tbody = document.getElementById('consultasTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    consultasFiltradas.forEach(consulta => {
        const row = createConsultaRow(consulta);
        tbody.appendChild(row);
    });
}

/**
 * Modales
 */
function showModal(modalName) {
    const modal = document.getElementById(modalName + 'Modal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalName) {
    const modal = document.getElementById(modalName + 'Modal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Limpiar formularios
        const forms = modal.querySelectorAll('form');
        forms.forEach(form => form.reset());
    }
}

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.remove('show');
    });
    document.body.style.overflow = '';
}

/**
 * Crear usuario
 */
async function crearUsuario(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    showLoading();
    
    try {
        const response = await fetch('api/crear-usuario.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Usuario creado exitosamente', 'success');
            closeModal('nuevoUsuario');
            loadUsuarios(); // Recargar usuarios
        } else {
            showAlert(data.message || 'Error al crear usuario', 'error');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error de conexión', 'error');
    } finally {
        hideLoading();
    }
}

/**
 * Funciones de usuario
 */
function editarUsuario(id) {
    console.log('Editando usuario:', id);
    showAlert('Función de edición en desarrollo', 'info');
}

function toggleUsuarioEstado(id) {
    console.log('Cambiando estado de usuario:', id);
    showAlert('Función de estado en desarrollo', 'info');
}

function eliminarUsuario(id) {
    if (confirm('¿Está seguro de que desea eliminar este usuario?')) {
        console.log('Eliminando usuario:', id);
        showAlert('Función de eliminación en desarrollo', 'info');
    }
}

/**
 * Funciones de consultas
 */
function verConsulta(id) {
    console.log('Viendo consulta:', id);
    showAlert('Función de visualización en desarrollo', 'info');
}

function responderConsulta(id) {
    console.log('Respondiendo consulta:', id);
    showAlert('Función de respuesta en desarrollo', 'info');
}

function cambiarPrioridad(id) {
    console.log('Cambiando prioridad de consulta:', id);
    showAlert('Función de prioridad en desarrollo', 'info');
}

function exportarConsultas() {
    console.log('Exportando consultas');
    showAlert('Función de exportación en desarrollo', 'info');
}

function responderMasivo() {
    console.log('Respuesta masiva');
    showAlert('Función de respuesta masiva en desarrollo', 'info');
}

/**
 * Funciones de perfil y configuración
 */
function showProfile() {
    console.log('Mostrando perfil');
    showAlert('Función de perfil en desarrollo', 'info');
}

function showSettings() {
    console.log('Mostrando configuración');
    showAlert('Función de configuración en desarrollo', 'info');
}

/**
 * Logout
 */
async function logout() {
    if (confirm('¿Está seguro de que desea cerrar sesión?')) {
        try {
            await fetch('api/logout.php', { method: 'POST' });
            window.location.href = 'login.html?logout=success';
        } catch (error) {
            console.error('Error en logout:', error);
            window.location.href = 'login.html';
        }
    }
}

/**
 * Funciones de utilidad
 */
function showLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading) {
        loading.classList.add('show');
    }
}

function hideLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading) {
        loading.classList.remove('show');
    }
}

function showAlert(message, type = 'info') {
    // Crear elemento de alerta
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 2000;
        max-width: 300px;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideInRight 0.3s ease;
    `;
    
    // Estilos según el tipo
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
    
    // Auto-remove después de 5 segundos
    setTimeout(() => {
        if (alert.parentNode) {
            alert.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }
    }, 5000);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    
    const date = new Date(dateString);
    return date.toLocaleDateString('es-AR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
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
        if (count > 0) {
            notificationCount.classList.add('show');
        } else {
            notificationCount.classList.remove('show');
        }
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
            </div>
        `;
        activityList.appendChild(item);
    });
}

// Agregar estilos de animación
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { opacity: 0; transform: translateX(100%); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(100%); }
    }
`;
document.head.appendChild(style);