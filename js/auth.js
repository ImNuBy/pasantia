
class AuthManager {
    constructor() {
        this.userData = null;
        this.init();
    }
    
    init() {
        this.checkSession();
    }
    
    async checkSession() {
        try {
            const response = await fetch('api/verify-session.php');
            const result = await response.json();
            
            if (result.success && result.logged_in) {
                this.userData = result.user;
                this.updateUserInfo();
                return true;
            } else {
                // Redirect to login if not authenticated
                window.location.href = 'login.html';
                return false;
            }
        } catch (error) {
            console.error('Error verificando sesión:', error);
            window.location.href = 'login.html';
            return false;
        }
    }
    
    updateUserInfo() {
        if (!this.userData) return;
        
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
    }
    
    async logout() {
        try {
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