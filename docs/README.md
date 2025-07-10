# E.E.S.T N°2 - Sitio Web Oficial

## 📋 Descripción
Sitio web oficial de la Escuela de Educación Secundaria Técnica N°2, especializada en Electrónica, Computación y Comunicaciones. El sitio incluye información institucional, orientaciones técnicas, proyectos destacados y formulario de contacto.

## 🚀 Características Principales

### ✨ Diseño y UX
- Diseño moderno y responsive
- Animaciones suaves y efectos parallax
- Navegación sticky con scroll suave
- Tema oscuro automático según preferencias del usuario
- Optimizado para accesibilidad (WCAG 2.1)

### 📱 Responsive Design
- Compatible con todos los dispositivos
- Menú móvil hamburguesa
- Grids adaptables
- Tipografía escalable

### 🎯 Funcionalidades
- Formulario de contacto con validación
- Destacado automático de sección activa
- Notificaciones de éxito/error
- Carga lazy de imágenes
- SEO optimizado

## 🛠️ Tecnologías Utilizadas

- **HTML5**: Estructura semántica
- **CSS3**: Estilos avanzados con Flexbox y Grid
- **JavaScript (ES6+)**: Interactividad y animaciones
- **Responsive Design**: Mobile-first approach
- **Intersection Observer API**: Animaciones al scroll
- **CSS Custom Properties**: Variables CSS para fácil personalización

## 📁 Estructura del Proyecto

```
eest-n2/
├── index.html              # Página principal
├── css/
│   ├── styles.css          # Estilos principales
│   └── responsive.css      # Estilos responsive
├── js/
│   └── main.js            # JavaScript principal
├── img/                   # Imágenes del sitio
│   ├── orientaciones/     # Imágenes de orientaciones
│   └── proyectos/         # Imágenes de proyectos
├── config/
│   └── config.js          # Configuración del sitio
└── docs/
    └── README.md          # Documentación
```

## 🔧 Instalación y Configuración

### Prerrequisitos
- Servidor web (Apache, Nginx, o servidor local)
- Navegador moderno (Chrome 90+, Firefox 88+, Safari 14+)

### Instalación
1. Clona o descarga el proyecto
2. Copia los archivos a tu servidor web
3. Configura las variables en `config/config.js`
4. Agrega las imágenes necesarias en la carpeta `img/`
5. Abre `index.html` en tu navegador

### Configuración Básica
1. **Editar información de contacto** en `config/config.js`
2. **Personalizar colores** en `css/styles.css` (variables CSS)
3. **Agregar imágenes** en las carpetas correspondientes
4. **Configurar formularios** (endpoint del backend)

## 📝 Personalización

### Cambiar Colores
Edita las variables CSS en `css/styles.css`:
```css
:root {
    --primary-color: #1a365d;
    --secondary-color: #2d5aa0;
    --accent-color: #4299e1;
}
```

### Agregar Nueva Sección
1. Agregar HTML en `index.html`
2. Agregar estilos en `css/styles.css`
3. Agregar enlace en el menú de navegación
4. Actualizar configuración en `config/config.js`

### Modificar Contenido
- **Textos**: Editar directamente en `index.html`
- **Imágenes**: Reemplazar archivos en carpeta `img/`
- **Configuración**: Editar `config/config.js`

## 🎨 Guía de Estilos

### Tipografía
- **Principal**: Segoe UI, Tahoma, Geneva, Verdana, sans-serif
- **Tamaños**: 14px - 64px (escalables)
- **Pesos**: 300, 400, 500, 600, 700, 800

### Colores
- **Primario**: #1a365d (Azul oscuro)
- **Secundario**: #2d5aa0 (Azul medio)
- **Acento**: #4299e1 (Azul claro)
- **Texto**: #1a202c (Oscuro), #718096 (Claro)

### Espaciado
- **Secciones**: 5rem padding vertical
- **Tarjetas**: 2rem padding interno
- **Elementos**: Sistema de espaciado basado en rem

## 📱 Responsive Breakpoints

```css
/* Tablet */
@media screen and (max-width: 1024px) { }

/* Mobile Landscape */
@media screen and (max-width: 768px) { }

/* Mobile Portrait */
@media screen and (max-width: 480px) { }

/* Extra Small */
@media screen and (max-width: 320px) { }
```

## 🔒 Seguridad

### Headers de Seguridad Recomendados
```apache
Header always set X-Frame-Options DENY
Header always set X-Content-Type-Options nosniff
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

### Validación de Formularios
- Validación del lado del cliente (JavaScript)
- Sanitización de inputs
- Protección contra XSS
- Validación del lado del servidor (requerida)

## 📊 SEO y Analytics

### Meta Tags Incluidos
- Title y Description optimizados
- Open Graph para redes sociales
- Structured Data (Schema.org)
- Canonical URLs

### Analytics
- Google Analytics 4 (configurar en `config.js`)
- Facebook Pixel (opcional)
- Eventos personalizados de interacción

## 🚀 Performance

### Optimizaciones Implementadas
- CSS y JS minimizados para producción
- Imágenes optimizadas y lazy loading
- Fonts preloaded
- Critical CSS inlined
- Caching headers configurados

### Métricas Target
- **First Contentful Paint**: < 1.5s
- **Largest Contentful Paint**: < 2.5s
- **Cumulative Layout Shift**: < 0.1
- **First Input Delay**: < 100ms

## ♿ Accesibilidad

### Características de Accesibilidad
- Navegación por teclado completa
- Alto contraste disponible
- Screen reader friendly
- ARIA labels y roles
- Texto alternativo en imágenes
- Tamaños de fuente escalables

### Estándares Cumplidos
- WCAG 2.1 Level AA
- Section 508 compatible
- Pruebas con lectores de pantalla

## 🧪 Testing

### Navegadores Probados
- Chrome 90+ ✅
- Firefox 88+ ✅
- Safari 14+ ✅
- Edge 90+ ✅

### Dispositivos Probados
- Desktop (1920x1080, 1366x768) ✅
- Tablet (768x1024, 1024x768) ✅
- Mobile (375x667, 414x896, 360x640) ✅

### Herramientas de Testing
- Lighthouse (Performance, SEO, Accessibility)
- BrowserStack (Cross-browser testing)
- axe DevTools (Accessibility testing)
- GTmetrix (Performance testing)

## 🔄 Versionado

### Changelog
- **v1.0.0** (2024-07-10): Lanzamiento inicial
  - Diseño completo responsive
  - Formulario de contacto funcional
  - Animaciones y efectos
  - SEO optimizado

### Próximas Versiones
- **v1.1.0**: Sistema de login
- **v1.2.0**: Panel de administración
- **v1.3.0**: Sistema de noticias
- **v2.0.0**: PWA y funcionalidades avanzadas

## 🐛 Troubleshooting

### Problemas Comunes

**Las animaciones no funcionan:**
- Verificar que JavaScript esté habilitado
- Comprobar consola por errores
- Verificar que los archivos CSS y JS estén cargando

**El formulario no envía:**
- Configurar endpoint del backend en `config.js`
- Verificar validación de campos
- Comprobar conexión a internet

**Imágenes no cargan:**
- Verificar rutas de archivos
- Comprobar que las imágenes existan
- Verificar permisos de archivos

### Logs y Debugging
- Usar DevTools del navegador
- Verificar consola de errores
- Usar Lighthouse para auditorías
- Probar en modo incógnito

## 📞 Soporte

### Contacto para Soporte Técnico
- **Email**: soporte@eest2.edu.ar
- **Teléfono**: +54 11 1234-5678
- **Horario**: Lunes a Viernes 9:00-17:00

### Documentación Adicional
- Guía de administrador: `/docs/admin-guide.md`
- API Documentation: `/docs/api.md`
- Deployment Guide: `/docs/deployment.md`

### Contributing
Para contribuir al proyecto:
1. Fork del repositorio
2. Crear branch para la feature
3. Commit de cambios
4. Push al branch
5. Crear Pull Request

## 📄 Licencia
Este proyecto está bajo la Licencia MIT. Ver `LICENSE` para más detalles.

---

**Desarrollado con ❤️ para E.E.S.T N°2**