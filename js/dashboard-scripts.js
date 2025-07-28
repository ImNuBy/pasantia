/**
 * Dashboard Administrativo EPA 703
 * JavaScript separado para funcionalidades del panel principal
 */

// ========================================
// CONFIGURACIÓN GLOBAL
// ========================================
const DASHBOARD_CONFIG = {
    refreshInterval: 300000, // 5 minutos
    animationDuration: 300,
    chartColors: {
        primary: '#1e3a2e',
        secondary: '#2d5a42',
        success: '#28a745',
        warning: '#ffc107',
        danger: '#dc3545',
        info: '#17a2b8'
    }
};

// ========================================
// CLASE PRINCIPAL DEL DASHBOARD
// ========================================
class AdminDashboard {
    constructor() {
        this.isCollapsed = false;
        this.refreshTimer = null;
        this.init();
    }

    init() {
        console.log('🚀 Iniciando Dashboard Administrativo EPA 703...');
        
        this.setupEventListeners();
        this.initializeSidebar();
        this.startAutoRefresh();
        this.animateCounters();
        this.setupTooltips();
        this.setupKeyboardShortcuts();
        
        console.log('✅ Dashboard inicializado correctamente');
    }

    setupEventListeners() {
        // Toggle sidebar
        const sidebarToggle = document.getElementById('sidebar-toggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }

        // Responsive sidebar para móviles
        this.setupMobileSidebar();

        // Refresh button
        const refreshBtn = document.querySelector('[onclick="refreshActivity()"]');
        if (refreshBtn) {
            refreshBtn.removeAttribute('onclick');
            refreshBtn.addEventListener('click', () => this.refreshActivity());
        }

        // Click fuera del sidebar en móviles
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const sidebarToggle = document.getElementById('sidebar-toggle');
                
                if (sidebar && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });

        // Resize window
        window.addEventListener('resize', () => this.handleResize());

        // Setup hover effects
        this.setupHoverEffects();
    }

    setupMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const links = sidebar.querySelectorAll('.nav-link');
        
        links.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                }
            });
        });
    }

    setupHoverEffects() {
        // Hover effects para navigation
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            if (!link.classList.contains('active')) {
                link.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#2d5a42';
                    this.style.transform = 'translateX(5px)';
                });
                
                link.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'transparent';
                    this.style.transform = 'translateX(0)';
                });
            }
        });

        // Hover effects para stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 1rem 3rem rgba(0, 0, 0, 0.175)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 0.5rem 1rem rgba(0, 0, 0, 0.15)';
            });
        });

        // Hover effects para items interactivos
        const interactiveItems = document.querySelectorAll('.consulta-item, .usuario-item');
        interactiveItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
                this.style.borderColor = '#1e3a2e';
                this.style.transform = 'translateX(5px)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.backgroundColor = 'transparent';
                this.style.borderColor = '#e9ecef';
                this.style.transform = 'translateX(0)';
            });
        });
    }

    initializeSidebar() {
        // Marcar enlace activo
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });

        // Si no hay activo, marcar dashboard
        if (!document.querySelector('.nav-link.active')) {
            const dashboardLink = document.querySelector('a[href="dashboard.php"]');
            if (dashboardLink) {
                dashboardLink.classList.add('active');
            }
        }

        // Restaurar estado del sidebar desde localStorage
        const savedState = localStorage.getItem('sidebar-collapsed');
        if (savedState === 'true' && window.innerWidth > 768) {
            this.isCollapsed = true;
            document.getElementById('sidebar').classList.add('collapsed');
        }
    }

    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        
        if (window.innerWidth > 768) {
            // Desktop: colapsar/expandir
            this.isCollapsed = !this.isCollapsed;
            sidebar.classList.toggle('collapsed', this.isCollapsed);
            
            // Guardar preferencia
            localStorage.setItem('sidebar-collapsed', this.isCollapsed);
        } else {
            // Móvil: mostrar/ocultar
            sidebar.classList.toggle('open');
        }
    }

    handleResize() {
        const sidebar = document.getElementById('sidebar');
        
        if (window.innerWidth > 768) {
            sidebar.classList.remove('open');
            
            // Restaurar estado colapsado en desktop
            const savedState = localStorage.getItem('sidebar-collapsed');
            if (savedState === 'true') {
                this.isCollapsed = true;
                sidebar.classList.add('collapsed');
            }
        } else {
            sidebar.classList.remove('collapsed');
            this.isCollapsed = false;
        }
    }

    // ========================================
    // ANIMACIONES Y EFECTOS
    // ========================================
    animateCounters() {
        const counters = document.querySelectorAll('.stat-content h3, .stat-value');
        
        counters.forEach(counter => {
            const target = parseInt(counter.textContent.replace(/\D/g, ''));
            if (isNaN(target)) return;
            
            let current = 0;
            const increment = target / 60; // 60 frames para animación suave
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(current).toLocaleString();
                }
            }, 16); // ~60fps
        });
    }

    setupTooltips() {
        // Inicializar tooltips de Bootstrap si están disponibles
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Tooltips personalizados para badges
        this.addCustomTooltips();
    }

    addCustomTooltips() {
        const badges = document.querySelectorAll('.badge');
        badges.forEach(badge => {
            let tooltipText = '';
            
            if (badge.textContent.includes('inscripcion')) {
                tooltipText = 'Consultas relacionadas con inscripciones';
            } else if (badge.textContent.includes('estudiante')) {
                tooltipText = 'Usuario con rol de estudiante';
            } else if (badge.textContent.includes('profesor')) {
                tooltipText = 'Usuario con rol de profesor';
            }
            
            if (tooltipText) {
                badge.setAttribute('title', tooltipText);
                badge.style.cursor = 'help';
            }
        });
    }

    // ========================================
    // REFRESH Y DATOS EN TIEMPO REAL
    // ========================================
    startAutoRefresh() {
        // Auto-refresh cada 5 minutos
        this.refreshTimer = setInterval(() => {
            this.refreshData();
        }, DASHBOARD_CONFIG.refreshInterval);

        // Refresh cuando la página vuelve a ser visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.refreshData();
            }
        });
    }

    async refreshData() {
        try {
            console.log('🔄 Actualizando datos del dashboard...');
            
            // Mostrar indicador de carga
            this.showRefreshIndicator('loading');
            
            const response = await fetch('api/dashboard-data.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.updateDashboardData(data.data);
                this.showRefreshIndicator('success');
            } else {
                throw new Error(data.error || 'Error desconocido');
            }

        } catch (error) {
            console.error('❌ Error actualizando dashboard:', error);
            this.showRefreshIndicator('error');
        }
    }

    async refreshActivity() {
        const button = document.querySelector('[onclick*="refreshActivity"]');
        const originalContent = button ? button.innerHTML : '';
        
        try {
            if (button) {
                button.innerHTML = '<i class="fas fa-sync fa-spin"></i> Actualizando...';
                button.disabled = true;
            }

            // Simular refresh (en producción haría una llamada AJAX real)
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            this.showNotification('Actividad actualizada correctamente', 'success');

        } catch (error) {
            console.error('Error refreshing activity:', error);
            this.showNotification('Error al actualizar actividad', 'danger');
        } finally {
            if (button) {
                setTimeout(() => {
                    button.innerHTML = originalContent;
                    button.disabled = false;
                }, 500);
            }
        }
    }

    updateDashboardData(data) {
        // Actualizar contadores principales
        if (data.stats) {
            this.updateStatCounters(data.stats);
        }

        // Actualizar actividad reciente
        if (data.recent_activity) {
            this.updateRecentActivity(data.recent_activity);
        }

        // Actualizar consultas pendientes
        if (data.pending_consultations) {
            this.updatePendingConsultations(data.pending_consultations);
        }

        // Actualizar usuarios recientes
        if (data.recent_users) {
            this.updateRecentUsers(data.recent_users);
        }
    }

    updateStatCounters(stats) {
        // Actualizar números en las tarjetas de estadísticas
        const counters = {
            'total_usuarios': stats.total_users || 0,
            'total_estudiantes': stats.students || 0,
            'consultas_pendientes': stats.pending_consultations || 0,
            'inscripciones_pendientes': stats.pending_enrollments || 0
        };

        Object.entries(counters).forEach(([id, value]) => {
            const element = document.querySelector(`#${id}, .stat-content h3`);
            if (element) {
                // Animar cambio si el valor es diferente
                const currentValue = parseInt(element.textContent.replace(/\D/g, ''));
                if (currentValue !== value) {
                    this.animateValueChange(element, currentValue, value);
                }
            }
        });
    }

    animateValueChange(element, from, to) {
        const duration = 1000;
        const startTime = Date.now();
        
        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const current = Math.round(from + (to - from) * easeOutQuart);
            
            element.textContent = current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }

    // ========================================
    // NOTIFICACIONES Y UI
    // ========================================
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.style.maxWidth = '400px';
        
        const iconMap = {
            success: 'check-circle',
            danger: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };
        
        notification.innerHTML = `
            <i class="fas fa-${iconMap[type] || 'info-circle'}"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove después de 4 segundos
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 4000);
    }

    showRefreshIndicator(type = 'success') {
        const indicator = document.createElement('div');
        indicator.className = 'position-fixed top-0 end-0 m-3';
        indicator.style.zIndex = '9999';
        
        let content = '';
        switch (type) {
            case 'loading':
                content = `
                    <div class="alert alert-info">
                        <i class="fas fa-sync fa-spin"></i> Actualizando datos...
                    </div>
                `;
                break;
            case 'success':
                content = `
                    <div class="alert alert-success">
                        <i class="fas fa-check"></i> Datos actualizados
                    </div>
                `;
                break;
            case 'error':
                content = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error al actualizar
                    </div>
                `;
                break;
        }
        
        indicator.innerHTML = content;
        document.body.appendChild(indicator);
        
        // Auto-remove después de 3 segundos
        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.remove();
            }
        }, 3000);
    }

    // ========================================
    // KEYBOARD SHORTCUTS
    // ========================================
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + R: Refresh manual
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshData();
                return;
            }

            // Ctrl/Cmd + B: Toggle sidebar
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                this.toggleSidebar();
                return;
            }

            // Escape: Cerrar modales/overlays
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.show');
                if (activeModal && typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getInstance(activeModal);
                    if (modal) modal.hide();
                }
            }

            // Alt + 1-6: Navegación rápida
            if (e.altKey && e.key >= '1' && e.key <= '6') {
                e.preventDefault();
                const index = parseInt(e.key) - 1;
                const navLinks = document.querySelectorAll('.nav-link');
                if (navLinks[index]) {
                    navLinks[index].click();
                }
            }
        });
    }

    // ========================================
    // MANEJO DE NOTIFICACIONES DEL SISTEMA
    // ========================================
    checkNotifications() {
        // Verificar consultas urgentes
        const urgentConsultations = document.querySelectorAll('.badge.bg-danger');
        if (urgentConsultations.length > 0) {
            this.showSystemNotification(
                'Consultas Urgentes',
                `Hay ${urgentConsultations.length} consultas que requieren atención inmediata.`,
                'warning'
            );
        }

        // Verificar inscripciones pendientes
        const pendingEnrollments = document.querySelector('.stat-card.info .stat-content h3');
        if (pendingEnrollments) {
            const count = parseInt(pendingEnrollments.textContent.replace(/\D/g, ''));
            if (count > 5) {
                this.showSystemNotification(
                    'Muchas Inscripciones Pendientes',
                    `Hay ${count} inscripciones esperando tu revisión.`,
                    'info'
                );
            }
        }
    }

    showSystemNotification(title, message, type = 'info') {
        // Mostrar notificación del sistema si está soportado
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body: message,
                icon: '/favicon.ico',
                tag: 'epa703-admin',
                requireInteraction: type === 'warning'
            });

            // Auto-close después de 5 segundos para notificaciones informativas
            if (type === 'info') {
                setTimeout(() => notification.close(), 5000);
            }
        }
    }

    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    console.log('✅ Permisos de notificación concedidos');
                    this.showSystemNotification(
                        'Notificaciones Habilitadas',
                        'Recibirás alertas importantes del sistema.',
                        'info'
                    );
                }
            });
        }
    }

    // ========================================
    // PERFORMANCE MONITORING
    // ========================================
    monitorPerformance() {
        // Medir tiempo de carga inicial
        if ('performance' in window) {
            window.addEventListener('load', () => {
                const perfData = performance.getEntriesByType('navigation')[0];
                const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
                
                console.log(`📊 Dashboard cargado en ${loadTime}ms`);
                
                // Si el tiempo de carga es muy alto, mostrar advertencia
                if (loadTime > 3000) {
                    console.warn('⚠️ Tiempo de carga elevado, considerar optimizaciones');
                }
            });
        }

        // Monitor de memoria (si está disponible)
        if ('memory' in performance) {
            setInterval(() => {
                const memInfo = performance.memory;
                if (memInfo.usedJSHeapSize > memInfo.jsHeapSizeLimit * 0.9) {
                    console.warn('⚠️ Alto uso de memoria detectado');
                }
            }, 60000); // Check cada minuto
        }
    }

    // ========================================
    // UTILIDADES
    // ========================================
    formatNumber(num) {
        return num.toLocaleString('es-AR');
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-AR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    timeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'Hace un momento';
        if (diffInSeconds < 3600) return `Hace ${Math.floor(diffInSeconds / 60)} minutos`;
        if (diffInSeconds < 86400) return `Hace ${Math.floor(diffInSeconds / 3600)} horas`;
        if (diffInSeconds < 2592000) return `Hace ${Math.floor(diffInSeconds / 86400)} días`;
        
        return this.formatDate(dateString);
    }

    // ========================================
    // CLEANUP Y DESTRUCTOR
    // ========================================
    destroy() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }
        
        // Remover event listeners
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        window.removeEventListener('resize', this.handleResize);
        
        console.log('🧹 Dashboard cleanup completado');
    }
}

// ========================================
// INICIALIZACIÓN GLOBAL
// ========================================
let dashboardInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    dashboardInstance = new AdminDashboard();
    
    // Inicializar funcionalidades adicionales
    dashboardInstance.requestNotificationPermission();
    dashboardInstance.monitorPerformance();
    
    // Check for urgent notifications after load
    setTimeout(() => {
        dashboardInstance.checkNotifications();
    }, 2000);
});

// ========================================
// FUNCIONES GLOBALES PARA COMPATIBILIDAD
// ========================================
function refreshActivity() {
    if (dashboardInstance) {
        dashboardInstance.refreshActivity();
    }
}

function toggleSidebar() {
    if (dashboardInstance) {
        dashboardInstance.toggleSidebar();
    }
}

// ========================================
// MANEJO DE ERRORES GLOBAL
// ========================================
window.addEventListener('error', function(e) {
    console.error('❌ Error en dashboard:', e.error);
    
    if (dashboardInstance) {
        dashboardInstance.showNotification('Ha ocurrido un error inesperado', 'danger');
    }
});

// ========================================
// COMPATIBILIDAD CON VERSIONES ANTERIORES
// ========================================
window.AdminDashboard = AdminDashboard;

// ========================================
// LOGS DE DESARROLLO
// ========================================
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    console.log(`
    🎓 EPA 703 - Dashboard Administrativo
    =====================================
    
    Atajos de teclado:
    • Ctrl/Cmd + R: Actualizar datos
    • Ctrl/Cmd + B: Toggle sidebar
    • Alt + 1-6: Navegación rápida
    • Escape: Cerrar modales
    
    Funcionalidades activas:
    • ✅ Auto-refresh cada 5 minutos
    • ✅ Responsive design
    • ✅ Animaciones suaves
    • ✅ Notificaciones del sistema
    • ✅ Keyboard shortcuts
    • ✅ Performance monitoring
    • ✅ Hover effects
    • ✅ Error handling
    
    Versión: 1.0.0
    `);
}