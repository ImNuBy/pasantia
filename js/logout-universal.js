/**
 * EPA 703 - Función de Logout Universal Corregida
 * Esta función debe incluirse en todos los paneles para que el cierre de sesión funcione correctamente
 */

/**
 * Función de logout mejorada y universal
 */
function logout() {
    // Prevenir múltiples clicks
    if (window.isLoggingOut) {
        console.log('Logout ya en progreso...');
        return;
    }
    
    window.isLoggingOut = true;
    
    // Confirmar cierre de sesión
    if (!confirm('¿Estás seguro de que deseas cerrar sesión?')) {
        window.isLoggingOut = false;
        return;
    }
    
    console.log('🚪 Iniciando cierre de sesión...');
    
    // Mostrar indicador de carga
    mostrarIndicadorLogout();
    
    // Intentar cerrar sesión en el servidor
    fetch('api/logout.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Respuesta del servidor recibida');
        return response.text(); // Cambiar a text() primero para ver qué devuelve
    })
    .then(text => {
        console.log('Respuesta del servidor:', text);
        // Intentar parsear como JSON
        try {
            const data = JSON.parse(text);
            console.log('Logout exitoso:', data);
        } catch (e) {
            console.log('Respuesta no es JSON, probablemente redirección');
        }
    })
    .catch(error => {
        console.error('Error en logout:', error);
    })
    .finally(() => {
        // Limpiar datos locales independientemente del resultado
        limpiarDatosLocales();
        
        // Redirigir al login
        console.log('Redirigiendo al login...');
        window.location.href = 'login.html?logout=success';
    });
}

/**
 * Limpiar todos los datos locales de sesión
 */
function limpiarDatosLocales() {
    console.log('Limpiando datos locales...');
    
    // Limpiar sessionStorage
    try {
        sessionStorage.clear();
        console.log('✅ SessionStorage limpiado');
    } catch (e) {
        console.error('Error limpiando sessionStorage:', e);
    }
    
    // Limpiar localStorage relacionado con sesión
    try {
        const keysToRemove = ['sessionToken', 'userRole', 'userName', 'userInfo', 'userType'];
        keysToRemove.forEach(key => {
            localStorage.removeItem(key);
        });
        console.log('✅ LocalStorage limpiado');
    } catch (e) {
        console.error('Error limpiando localStorage:', e);
    }
    
    // Limpiar cookies de sesión
    try {
        document.cookie.split(";").forEach(function(c) { 
            document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/"); 
        });
        console.log('✅ Cookies limpiadas');
    } catch (e) {
        console.error('Error limpiando cookies:', e);
    }
}

/**
 * Mostrar indicador visual de logout
 */
function mostrarIndicadorLogout() {
    // Crear overlay de carga
    const overlay = document.createElement('div');
    overlay.id = 'logoutOverlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 99999;
        color: white;
        font-size: 18px;
        font-family: Arial, sans-serif;
    `;
    
    overlay.innerHTML = `
        <div style="text-align: center;">
            <div style="border: 4px solid #f3f3f3; border-top: 4px solid #1e3a2e; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p>Cerrando sesión...</p>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    // Agregar animación de spin si no existe
    if (!document.getElementById('logout-spin-animation')) {
        const style = document.createElement('style');
        style.id = 'logout-spin-animation';
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * Función alternativa de logout para enlaces
 */
function handleLogoutLink(event) {
    if (event) {
        event.preventDefault();
    }
    logout();
}

/**
 * Verificar sesión al cargar la página
 */
async function verificarSesionActiva() {
    try {
        const response = await fetch('api/check-session.php');
        const data = await response.json();
        
        if (!data.authenticated) {
            console.warn('Sesión no válida, redirigiendo al login...');
            window.location.href = 'login.html?session=expired';
        }
        
        return data.authenticated;
    } catch (error) {
        console.error('Error verificando sesión:', error);
        return false;
    }
}

/**
 * Inicializar verificación de sesión periódica
 */
function inicializarVerificacionSesion() {
    // Verificar sesión cada 5 minutos
    setInterval(verificarSesionActiva, 5 * 60 * 1000);
    
    // Verificar sesión al volver a la pestaña
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            verificarSesionActiva();
        }
    });
}

// Inicializar al cargar el documento
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarVerificacionSesion);
} else {
    inicializarVerificacionSesion();
}

// Exponer funciones globalmente
window.logout = logout;
window.handleLogoutLink = handleLogoutLink;
window.verificarSesionActiva = verificarSesionActiva;

console.log('✅ Sistema de logout universal cargado correctamente');