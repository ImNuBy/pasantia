
class AuthManager {
    constructor() {
        this.userData = null;
        this.init();
    }
    
    init() {
        console.log('🔐 AuthManager inicializando...');
        this.checkSession();
    }
    
    async checkSession() {
        try {
            console.log('🔍 Verificando sesión...');
            const response = await fetch('api/verify-session.php');
            
            if (!response.ok) {
                console.log('⚠️ Error en verify-session:', response.status);
                return false;
            }
            
            const responseText = await response.text();
            console.log('📄 Respuesta verify-session:', responseText);
            
            const result = JSON.parse(responseText);
            
            if (result.success && result.logged_in) {
                this.userData = result.user;
                console.log('✅ Sesión válida:', this.userData);
                this.updateUserInfo();
                return true;
            } else {
                console.log('ℹ️ No hay sesión activa');
                // Solo redirigir si estamos en un panel
                if (window.location.pathname.includes('panel-')) {
                    window.location.href = 'login.html';
                }
                return false;
            }
        } catch (error) {
            console.error('❌ Error verificando sesión:', error);
            if (window.location.pathname.includes('panel-')) {
                window.location.href = 'login.html';
            }
            return false;
        }
    }
    
    updateUserInfo() {
        if (!this.userData) return;
        
        console.log('🔄 Actualizando info de usuario en UI');
        
        // Update welcome message
        const welcomeElement = document.getElementById('user-welcome');
        if (welcomeElement) {
            welcomeElement.textContent = `Bienvenido/a, ${this.userData.nombre} ${this.userData.apellido}`;
        }
        
        // Update user details based on user type
        if (this.userData.legajo) {
            const legajoElement = document.getElementById('user-legajo');
            if (legajoElement) {
                legajoElement.textContent = this.userData.legajo;
            }
        }
        
        console.log('✅ Info de usuario actualizada');
    }
    
    async logout() {
        try {
            console.log('🚪 Cerrando sesión...');
            await fetch('api/logout.php', { method: 'POST' });
        } catch (error) {
            console.error('Error en logout:', error);
        } finally {
            window.location.href = 'login.html';
        }
    }
    
    getUserData() {
        return this.userData;
    }
    
    hasRole(roles) {
        if (!this.userData) return false;
        if (typeof roles === 'string') roles = [roles];
        return roles.includes(this.userData.tipo_usuario);
    }
}

// Global auth manager instance
window.authManager = new AuthManager();

// Global logout function
function logout() {
    window.authManager.logout();
}

// Global function to get user data
function getUserData() {
    return window.authManager.getUserData();
}

console.log('✅ auth.js cargado correctamente');