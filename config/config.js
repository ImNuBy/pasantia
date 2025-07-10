// Configuración del sitio web E.E.S.T N°2
const CONFIG = {
    // Información de la institución
    school: {
        name: "E.E.S.T N°2",
        fullName: "Escuela de Educación Secundaria Técnica N°2",
        specialty: "Técnica Electrónica",
        founded: 1970,
        description: "Formando profesionales técnicos en electrónica con excelencia académica y preparación para el mundo laboral del siglo XXI."
    },

    // Datos de contacto
    contact: {
        address: {
            street: "Av. Tecnológica 1234",
            city: "Ciudad Técnica",
            province: "Buenos Aires",
            country: "Argentina",
            zipCode: "1234"
        },
        phone: {
            main: "+54 11 1234-5678",
            secondary: "+54 11 8765-4321"
        },
        email: {
            info: "info@eest2.edu.ar",
            secretaria: "secretaria@eest2.edu.ar",
            inscripciones: "inscripciones@eest2.edu.ar"
        },
        hours: {
            weekdays: "Lunes a Viernes: 7:00 - 18:00",
            saturday: "Sábados: 8:00 - 12:00",
            sunday: "Cerrado"
        },
        socialMedia: {
            facebook: "https://facebook.com/eest2",
            instagram: "https://instagram.com/eest2oficial",
            twitter: "https://twitter.com/eest2",
            youtube: "https://youtube.com/eest2"
        }
    },

    // Orientaciones técnicas
    orientaciones: [
        {
            id: "electronica",
            name: "Electrónica",
            description: "Formación integral en sistemas electrónicos, microcontroladores, automatización y control industrial.",
            materias: [
                "Circuitos Analógicos y Digitales",
                "Microcontroladores y Microprocesadores",
                "Sistemas de Control",
                "Instrumentación Electrónica",
                "Robótica y Automatización",
                "Energías Renovables"
            ],
            image: "img/orientaciones/electronica.jpg",
            duration: "6 años",
            title: "Técnico en Electrónica"
        },
        {
            id: "computacion",
            name: "Computación",
            description: "Especialización en programación, redes, bases de datos y desarrollo de software.",
            materias: [
                "Programación en Múltiples Lenguajes",
                "Administración de Redes",
                "Bases de Datos",
                "Desarrollo Web y Móvil",
                "Ciberseguridad",
                "Inteligencia Artificial"
            ],
            image: "img/orientaciones/computacion.jpg",
            duration: "6 años",
            title: "Técnico en Computación"
        },
        {
            id: "comunicaciones",
            name: "Comunicaciones",
            description: "Tecnologías de la información y comunicación, telecomunicaciones y sistemas de transmisión.",
            materias: [
                "Sistemas de Telecomunicaciones",
                "Redes de Comunicación",
                "Fibra Óptica",
                "Sistemas Inalámbricos",
                "Radiofrecuencia",
                "Comunicaciones Satelitales"
            ],
            image: "img/orientaciones/comunicaciones.jpg",
            duration: "6 años",
            title: "Técnico en Comunicaciones"
        }
    ],

    // Proyectos destacados
    projects: [
        {
            id: "proyecto1",
            title: "Sistema de Monitoreo Ambiental",
            description: "Desarrollo de sensores IoT para monitoreo de calidad del aire y condiciones ambientales urbanas.",
            tags: ["IoT", "Sensores", "Arduino", "Medio Ambiente"],
            image: "img/proyectos/proyecto1.jpg",
            year: 2024,
            participants: 15,
            status: "Completado"
        },
        {
            id: "proyecto2",
            title: "Brazo Robótico Educativo",
            description: "Construcción de brazo robótico controlado por microcontroladores para aplicaciones educativas.",
            tags: ["Robótica", "Control", "Educación", "Automatización"],
            image: "img/proyectos/proyecto2.jpg",
            year: 2024,
            participants: 12,
            status: "En desarrollo"
        },
        {
            id: "proyecto3",
            title: "App de Gestión Escolar",
            description: "Aplicación móvil para gestión de calificaciones, asistencia y comunicación escolar.",
            tags: ["Mobile", "Backend", "Database", "Gestión"],
            image: "img/proyectos/proyecto3.jpg",
            year: 2023,
            participants: 20,
            status: "Implementado"
        }
    ],

    // Configuración del sitio web
    website: {
        title: "E.E.S.T N°2 - Técnica Electrónica",
        description: "Escuela de Educación Secundaria Técnica especializada en Electrónica, Computación y Comunicaciones",
        keywords: ["escuela técnica", "electrónica", "computación", "comunicaciones", "Buenos Aires", "educación"],
        author: "E.E.S.T N°2",
        language: "es",
        favicon: "img/favicon.ico",
        logo: "img/logo.png",
        ogImage: "img/og-image.jpg"
    },

    // Configuración de formularios
    forms: {
        contact: {
            endpoint: "/api/contact", // Cambiar por la URL real del backend
            subjects: [
                { value: "inscripcion", label: "Información sobre inscripción" },
                { value: "orientaciones", label: "Consulta sobre orientaciones" },
                { value: "proyectos", label: "Información sobre proyectos" },
                { value: "becas", label: "Consulta sobre becas" },
                { value: "general", label: "Consulta general" }
            ],
            maxFileSize: "5MB",
            allowedFileTypes: [".pdf", ".doc", ".docx", ".jpg", ".png"]
        },
        newsletter: {
            endpoint: "/api/newsletter",
            enabled: true
        }
    },

    // Configuración de APIs
    apis: {
        googleMaps: {
            apiKey: "YOUR_GOOGLE_MAPS_API_KEY",
            coordinates: {
                lat: -34.6037,
                lng: -58.3816
            }
        },
        analytics: {
            googleAnalyticsId: "GA_MEASUREMENT_ID",
            facebookPixelId: "FACEBOOK_PIXEL_ID"
        }
    },

    // Configuración de características
    features: {
        darkMode: true,
        animations: true,
        parallax: true,
        lazyLoading: true,
        serviceWorker: false, // PWA
        newsletter: true,
        chat: false, // Chat en vivo
        multiLanguage: false
    },

    // Configuración de rendimiento
    performance: {
        imageOptimization: true,
        cssMinification: true,
        jsMinification: true,
        caching: {
            enabled: true,
            duration: "7d"
        }
    },

    // Configuración de accesibilidad
    accessibility: {
        highContrast: true,
        fontSize: {
            min: 14,
            max: 24,
            default: 16
        },
        reducedMotion: true,
        screenReader: true
    },

    // Menú de navegación
    navigation: [
        { id: "inicio", label: "INICIO", href: "#inicio" },
        { id: "nosotros", label: "NOSOTROS", href: "#nosotros" },
        { id: "orientaciones", label: "ORIENTACIONES", href: "#orientaciones" },
        { id: "proyectos", label: "PROYECTOS", href: "#proyectos" },
        { id: "contacto", label: "CONTACTO", href: "#contacto" }
    ],

    // Configuración de login
    auth: {
        enabled: false, // Cambiar a true cuando se implemente
        loginUrl: "/login",
        roles: ["estudiante", "profesor", "admin"],
        sessionTimeout: 30 // minutos
    },

    // Versión del sitio
    version: "1.0.0",
    lastUpdated: "2024-07-10"
};

// Exportar configuración para uso en otros archivos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CONFIG;
}

// Hacer disponible globalmente en el navegador
if (typeof window !== 'undefined') {
    window.CONFIG = CONFIG;
}