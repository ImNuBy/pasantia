/**
 * Sistema de Gestión de Consultas EPA 703
 * JavaScript para funcionalidades del panel administrativo
 */

// ========================================
// CONFIGURACIÓN GLOBAL
// ========================================
const CONFIG = {
    autoRefreshInterval: 30000, // 30 segundos
    animationDuration: 300,
    confirmationMessages: {
        aprobar: '¿Estás seguro de aprobar esta inscripción? Se creará automáticamente un usuario y se enviarán las credenciales por email.',
        rechazar: '¿Estás seguro de rechazar esta inscripción? Se enviará un email de notificación al solicitante.',
        estado: '¿Confirmas el cambio de estado de esta consulta?'
    }
};

// ========================================
// INICIALIZACIÓN DEL SISTEMA
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando Sistema de Gestión de Consultas EPA 703...');
    
    // Inicializar componentes
    initializeEventHandlers();
    initializeFormValidation();
    initializeTooltips();
    initializeAutoRefresh();
    initializeKeyboardShortcuts();
    initializeTableEnhancements();
    
    // Mostrar mensaje de bienvenida
    showWelcomeMessage();
    
    console.log('✅ Sistema inicializado correctamente');
});

// ========================================
// GESTIÓN DE FORMULARIOS DE PROCESAMIENTO
// ========================================
function mostrarFormRechazo(consultaId) {
    console.log(`📝 Mostrando formulario de rechazo para consulta #${consultaId}`);
    
    // Ocultar formulario de aprobación con animación
    const formAprobar = document.getElementById(`formAprobar${consultaId}`);
    if (formAprobar) {
        fadeOut(formAprobar, () => {
            formAprobar.style.display = 'none';
        });
    }
    
    // Mostrar formulario de rechazo con animación
    const formRechazar = document.getElementById(`formRechazar${consultaId}`);
    if (formRechazar) {
        formRechazar.style.display = 'block';
        fadeIn(formRechazar);
    }
    
    // Gestionar botones del modal
    const modal = document.getElementById(`modalProcesar${consultaId}`);
    if (modal) {
        const botones = modal.querySelectorAll('.modal-footer .btn');
        
        // Ocultar botones de aprobar y rechazar
        if (botones[1]) botones[1].style.display = 'none'; // Botón rechazar
        if (botones[2]) botones[2].style.display = 'none'; // Botón aprobar
        
        // Mostrar botón de confirmar rechazo
        const btnConfirmar = document.getElementById(`btnConfirmarRechazo${consultaId}`);
        if (btnConfirmar) {
            btnConfirmar.style.display = 'inline-block';
            fadeIn(btnConfirmar);
        }
    }
    
    // Enfocar el textarea del motivo
    const textareaMotivo = formRechazar.querySelector('textarea[name="motivo"]');
    if (textareaMotivo) {
        setTimeout(() => textareaMotivo.focus(), 200);
    }
}

function volverAFormAprobacion(consultaId) {
    console.log(`🔄 Volviendo al formulario de aprobación para consulta #${consultaId}`);
    
    // Mostrar formulario de aprobación
    const formAprobar = document.getElementById(`formAprobar${consultaId}`);
    if (formAprobar) {
        formAprobar.style.display = 'block';
        fadeIn(formAprobar);
    }
    
    // Ocultar formulario de rechazo
    const formRechazar = document.getElementById(`formRechazar${consultaId}`);
    if (formRechazar) {
        fadeOut(formRechazar, () => {
            formRechazar.style.display = 'none';
        });
    }
    
    // Restaurar botones originales
    const modal = document.getElementById(`modalProcesar${consultaId}`);
    if (modal) {
        const botones = modal.querySelectorAll('.modal-footer .btn');
        if (botones[1]) botones[1].style.display = 'inline-block'; // Botón rechazar
        if (botones[2]) botones[2].style.display = 'inline-block'; // Botón aprobar
        
        const btnConfirmar = document.getElementById(`btnConfirmarRechazo${consultaId}`);
        if (btnConfirmar) btnConfirmar.style.display = 'none';
    }
}

// ========================================
// MANEJADORES DE EVENTOS
// ========================================
function initializeEventHandlers() {
    // Confirmaciones para formularios de aprobación
    const formsAprobar = document.querySelectorAll('[id^="formAprobar"]');
    formsAprobar.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm(CONFIG.confirmationMessages.aprobar)) {
                e.preventDefault();
                return;
            }
            
            // Agregar estado de carga
            showLoadingState(form);
        });
    });

    // Confirmaciones para formularios de rechazo
    const formsRechazar = document.querySelectorAll('[id^="formRechazar"]');
    formsRechazar.forEach(form => {
        form.addEventListener('submit', function(e) {
            const motivo = form.querySelector('textarea[name="motivo"]').value.trim();
            
            if (!motivo) {
                e.preventDefault();
                showAlert('Por favor, especifica el motivo del rechazo.', 'warning');
                return;
            }
            
            if (!confirm(CONFIG.confirmationMessages.rechazar)) {
                e.preventDefault();
                return;
            }
            
            // Agregar estado de carga
            showLoadingState(form);
        });
    });

    // Confirmaciones para cambios de estado
    const formsEstado = document.querySelectorAll('form:has(input[name="action"][value="cambiar_estado"])');
    formsEstado.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm(CONFIG.confirmationMessages.estado)) {
                e.preventDefault();
                return;
            }
            
            showLoadingState(form);
        });
    });

    // Manejo de clics en filas de la tabla
    const filasConsulta = document.querySelectorAll('.consulta-row');
    filasConsulta.forEach(fila => {
        fila.addEventListener('click', function(e) {
            // Solo abrir detalles si no se hizo clic en un botón
            if (!e.target.closest('button')) {
                const consultaId = fila.cells[0].textContent.trim();
                const modalDetalle = document.getElementById(`modalDetalle${consultaId}`);
                if (modalDetalle) {
                    const modal = new bootstrap.Modal(modalDetalle);
                    modal.show();
                }
            }
        });
        
        // Efecto hover mejorado
        fila.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.002)';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
        });
        
        fila.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = 'none';
        });
    });

    // Manejo de modales
    const modales = document.querySelectorAll('.modal');
    modales.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            // Enfocar el primer campo editable
            const primerInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
            if (primerInput) {
                setTimeout(() => primerInput.focus(), 100);
            }
        });
        
        modal.addEventListener('hidden.bs.modal', function() {
            // Resetear formularios al cerrar
            const forms = modal.querySelectorAll('form');
            forms.forEach(form => {
                if (form.id.includes('Rechazar')) {
                    const consultaId = form.id.replace('formRechazar', '');
                    volverAFormAprobacion(consultaId);
                }
            });
        });
    });
}

// ========================================
// VALIDACIÓN DE FORMULARIOS
// ========================================
function initializeFormValidation() {
    // Validación en tiempo real para selects de curso
    const selectsCurso = document.querySelectorAll('select[name="curso_id"]');
    selectsCurso.forEach(select => {
        select.addEventListener('change', function() {
            if (this.value) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    });

    // Validación para textareas de motivo
    const textareasMotivo = document.querySelectorAll('textarea[name="motivo"]');
    textareasMotivo.forEach(textarea => {
        // Contador de caracteres
        const maxLength = 500;
        const container = textarea.parentElement;
        
        // Crear contador si no existe
        let contador = container.querySelector('.char-counter');
        if (!contador) {
            contador = document.createElement('small');
            contador.className = 'char-counter text-muted float-end';
            container.appendChild(contador);
        }
        
        // Actualizar contador
        function updateCounter() {
            const remaining = maxLength - textarea.value.length;
            contador.textContent = `${remaining} caracteres restantes`;
            
            if (remaining < 50) {
                contador.className = 'char-counter text-warning float-end';
            } else if (remaining < 0) {
                contador.className = 'char-counter text-danger float-end';
                textarea.classList.add('is-invalid');
            } else {
                contador.className = 'char-counter text-muted float-end';
                textarea.classList.remove('is-invalid');
            }
        }
        
        textarea.addEventListener('input', updateCounter);
        textarea.setAttribute('maxlength', maxLength);
        updateCounter();
    });
}

// ========================================
// TOOLTIPS Y AYUDA CONTEXTUAL
// ========================================
function initializeTooltips() {
    // Inicializar tooltips de Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Agregar tooltips dinámicos a badges
    const badges = document.querySelectorAll('.badge');
    badges.forEach(badge => {
        let tooltipText = '';
        
        if (badge.textContent.includes('Pendiente')) {
            tooltipText = 'Esta consulta está esperando revisión';
        } else if (badge.textContent.includes('Aprobada')) {
            tooltipText = 'Inscripción aprobada - Usuario creado automáticamente';
        } else if (badge.textContent.includes('Rechazada')) {
            tooltipText = 'Inscripción rechazada - Se envió notificación al solicitante';
        } else if (badge.textContent.includes('Respondido')) {
            tooltipText = 'Consulta respondida por el administrador';
        }
        
        if (tooltipText) {
            badge.setAttribute('data-bs-toggle', 'tooltip');
            badge.setAttribute('title', tooltipText);
            new bootstrap.Tooltip(badge);
        }
    });
}

// ========================================
// AUTO-REFRESH INTELIGENTE
// ========================================
function initializeAutoRefresh() {
    // Solo auto-refresh si estamos viendo consultas pendientes o sin filtro
    const urlParams = new URLSearchParams(window.location.search);
    const estado = urlParams.get('estado');
    
    if (!estado || estado === 'pendiente') {
        console.log('🔄 Activando auto-refresh cada 30 segundos');
        
        let refreshTimer = setTimeout(function() {
            console.log('🔄 Actualizando página...');
            
            // Mostrar indicador de actualización
            showRefreshIndicator();
            
            // Recargar después de un breve delay para mostrar el indicador
            setTimeout(() => {
                window.location.reload();
            }, 1000);
            
        }, CONFIG.autoRefreshInterval);

        // Pausar auto-refresh si hay modales abiertos
        const modales = document.querySelectorAll('.modal');
        modales.forEach(modal => {
            modal.addEventListener('shown.bs.modal', () => {
                console.log('⏸️ Pausando auto-refresh (modal abierto)');
                clearTimeout(refreshTimer);
            });
            
            modal.addEventListener('hidden.bs.modal', () => {
                console.log('▶️ Reanudando auto-refresh');
                refreshTimer = setTimeout(() => window.location.reload(), CONFIG.autoRefreshInterval);
            });
        });

        // Pausar auto-refresh si el usuario está interactuando
        let userActive = true;
        let inactivityTimer;

        document.addEventListener('mousemove', () => {
            userActive = true;
            clearTimeout(inactivityTimer);
            
            inactivityTimer = setTimeout(() => {
                userActive = false;
            }, 60000); // 1 minuto de inactividad
        });
    }
}

// ========================================
// ATAJOS DE TECLADO
// ========================================
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + R: Actualizar página
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            showRefreshIndicator();
            setTimeout(() => window.location.reload(), 500);
            return;
        }

        // Escape: Cerrar modales
        if (e.key === 'Escape') {
            const modalAbierto = document.querySelector('.modal.show');
            if (modalAbierto) {
                const modal = bootstrap.Modal.getInstance(modalAbierto);
                if (modal) modal.hide();
            }
        }

        // Ctrl/Cmd + F: Enfocar filtros
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const primerFiltro = document.querySelector('select[name="estado"]');
            if (primerFiltro) primerFiltro.focus();
        }
    });
}

// ========================================
// MEJORAS DE TABLA
// ========================================
function initializeTableEnhancements() {
    const tabla = document.querySelector('.table');
    if (!tabla) return;

    // Hacer encabezados sticky en scroll
    const encabezados = tabla.querySelectorAll('th');
    encabezados.forEach(th => {
        th.style.position = 'sticky';
        th.style.top = '0';
        th.style.zIndex = '100';
    });

    // Agregar indicadores de ordenamiento (visual solamente)
    encabezados.forEach(th => {
        if (th.textContent.trim() !== 'Acciones') {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                // Remover indicadores existentes
                encabezados.forEach(header => {
                    header.classList.remove('sort-asc', 'sort-desc');
                });
                
                // Agregar indicador visual (sin funcionalidad real de ordenamiento)
                th.classList.add('sort-asc');
                
                // Efecto visual de ordenamiento
                const filas = Array.from(tabla.querySelectorAll('tbody tr'));
                filas.forEach((fila, index) => {
                    setTimeout(() => {
                        fila.style.transform = 'translateX(5px)';
                        setTimeout(() => {
                            fila.style.transform = 'translateX(0)';
                        }, 50);
                    }, index * 20);
                });
            });
        }
    });

    // Mejorar scrolling horizontal en móviles
    const contenedorTabla = document.querySelector('.table-responsive');
    if (contenedorTabla) {
        let isScrolling = false;
        
        contenedorTabla.addEventListener('scroll', function() {
            if (!isScrolling) {
                isScrolling = true;
                this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
                
                setTimeout(() => {
                    isScrolling = false;
                    this.style.boxShadow = '0 0.5rem 1rem rgba(0, 0, 0, 0.15)';
                }, 150);
            }
        });
    }
}

// ========================================
// UTILIDADES DE UI
// ========================================
function showLoadingState(element) {
    element.classList.add('loading');
    
    // Deshabilitar botones de submit
    const submitButtons = element.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    });
}

function showAlert(message, type = 'info', duration = 5000) {
    const alertContainer = document.querySelector('.container');
    if (!alertContainer) return;

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <strong>${type === 'danger' ? '❌' : type === 'success' ? '✅' : type === 'warning' ? '⚠️' : 'ℹ️'}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    // Insertar al principio del container
    alertContainer.insertBefore(alertDiv, alertContainer.firstChild);

    // Auto-dismiss después del tiempo especificado
    setTimeout(() => {
        if (alertDiv.parentNode) {
            fadeOut(alertDiv, () => alertDiv.remove());
        }
    }, duration);
}

function showRefreshIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'position-fixed top-0 end-0 m-3';
    indicator.style.zIndex = '9999';
    indicator.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-sync fa-spin"></i> Actualizando datos...
        </div>
    `;
    
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        if (indicator.parentNode) {
            fadeOut(indicator, () => indicator.remove());
        }
    }, 2000);
}

function showWelcomeMessage() {
    const totalConsultas = document.querySelectorAll('.consulta-row').length;
    const pendientes = document.querySelector('.stats-card h3.text-warning')?.textContent || 0;
    
    if (pendientes > 0) {
        console.log(`📋 ${totalConsultas} consultas cargadas, ${pendientes} pendientes de revisión`);
    }
}

// ========================================
// ANIMACIONES AUXILIARES
// ========================================
function fadeIn(element, callback) {
    element.style.opacity = '0';
    element.style.display = 'block';
    
    let opacity = 0;
    const timer = setInterval(() => {
        if (opacity >= 1) {
            clearInterval(timer);
            if (callback) callback();
        }
        element.style.opacity = opacity;
        opacity += 0.1;
    }, CONFIG.animationDuration / 10);
}

function fadeOut(element, callback) {
    let opacity = 1;
    const timer = setInterval(() => {
        if (opacity <= 0) {
            clearInterval(timer);
            element.style.display = 'none';
            if (callback) callback();
        }
        element.style.opacity = opacity;
        opacity -= 0.1;
    }, CONFIG.animationDuration / 10);
}

// ========================================
// MANEJO DE ERRORES GLOBAL
// ========================================
window.addEventListener('error', function(e) {
    console.error('❌ Error en el sistema:', e.error);
    showAlert('Ha ocurrido un error inesperado. Por favor, recarga la página.', 'danger');
});

// ========================================
// FUNCIONES PARA LLAMADAS DESDE HTML
// ========================================
window.mostrarFormRechazo = mostrarFormRechazo;
window.volverAFormAprobacion = volverAFormAprobacion;
window.showAlert = showAlert;

// ========================================
// LOGS DE DESARROLLO
// ========================================
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    console.log(`
    🎓 EPA 703 - Sistema de Gestión de Consultas
    ============================================
    
    Atajos de teclado disponibles:
    • Ctrl/Cmd + R: Actualizar página
    • Escape: Cerrar modales
    • Ctrl/Cmd + F: Enfocar filtros
    
    Funcionalidades activas:
    • ✅ Auto-refresh cada 30 segundos
    • ✅ Validación de formularios en tiempo real
    • ✅ Tooltips contextuales
    • ✅ Animaciones fluidas
    • ✅ Atajos de teclado
    • ✅ Manejo de errores
    
    Versión: 1.0.0
    `);
}