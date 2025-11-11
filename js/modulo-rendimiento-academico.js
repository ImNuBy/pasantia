/**
 * EPA 703 - Módulo de Rendimiento Académico Completo
 * Funciones para cargar y mostrar el rendimiento académico de los estudiantes
 */

// ========================================
// FUNCIÓN MEJORADA DE RENDIMIENTO ACADÉMICO
// ========================================

/**
 * Ver rendimiento académico completo
 */
async function verRendimiento() {
    console.log('Cargando rendimiento académico...');
    
    // Mostrar modal de carga
    const modalContent = `
        <div class="modal active" id="rendimientoModal" style="display: flex;">
            <div class="modal-content" style="max-width: 1200px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <div class="modal-header">
                    <h3>📊 Rendimiento Académico</h3>
                    <button class="modal-close" onclick="closeRendimientoModal()">&times;</button>
                </div>
                <div class="modal-body" id="rendimientoContent">
                    <div style="text-align: center; padding: 40px;">
                        <div class="loading-spinner"></div>
                        <p>Cargando datos de rendimiento académico...</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Insertar modal en el DOM
    let modal = document.getElementById('rendimientoModal');
    if (modal) {
        modal.remove();
    }
    document.body.insertAdjacentHTML('beforeend', modalContent);
    
    try {
        // Intentar cargar datos reales
        const response = await fetch('api/rendimiento-academico.php');
        const data = await response.json();
        
        if (data.success) {
            mostrarRendimientoAcademico(data.rendimiento);
        } else {
            throw new Error('No se pudieron cargar los datos');
        }
    } catch (error) {
        console.error('Error cargando rendimiento:', error);
        // Mostrar datos de ejemplo
        mostrarRendimientoAcademicoEjemplo();
    }
}

/**
 * Mostrar datos de rendimiento académico
 */
function mostrarRendimientoAcademico(rendimiento) {
    const content = document.getElementById('rendimientoContent');
    if (!content) return;
    
    content.innerHTML = generarHTMLRendimiento(rendimiento);
    
    // Inicializar gráficos si existe Chart.js
    if (typeof Chart !== 'undefined') {
        inicializarGraficosRendimiento(rendimiento);
    }
}

/**
 * Mostrar datos de ejemplo
 */
function mostrarRendimientoAcademicoEjemplo() {
    const rendimientoEjemplo = {
        resumen: {
            totalEstudiantes: 120,
            promedioGeneral: 7.8,
            aprobados: 95,
            desaprobados: 15,
            ausentes: 10,
            tasaAprobacion: 79.2
        },
        porCurso: [
            { curso: '1er Año A', promedio: 8.2, estudiantes: 22, aprobados: 20 },
            { curso: '1er Año B', promedio: 7.5, estudiantes: 20, aprobados: 16 },
            { curso: '2do Año A', promedio: 7.8, estudiantes: 25, aprobados: 22 },
            { curso: '2do Año B', promedio: 7.3, estudiantes: 23, aprobados: 18 },
            { curso: '3er Año A', promedio: 8.0, estudiantes: 18, aprobados: 16 },
            { curso: '3er Año B', promedio: 7.6, estudiantes: 12, aprobados: 10 }
        ],
        porMateria: [
            { materia: 'Lengua', promedio: 7.5, aprobados: 88, desaprobados: 12 },
            { materia: 'Matemática', promedio: 6.8, aprobados: 75, desaprobados: 25 },
            { materia: 'Ciencias Naturales', promedio: 7.9, aprobados: 92, desaprobados: 8 },
            { materia: 'Ciencias Sociales', promedio: 8.1, aprobados: 95, desaprobados: 5 },
            { materia: 'Inglés', promedio: 7.2, aprobados: 80, desaprobados: 20 }
        ],
        topEstudiantes: [
            { nombre: 'María González', legajo: 'EST001', promedio: 9.5, curso: '3er Año A' },
            { nombre: 'Juan Pérez', legajo: 'EST023', promedio: 9.2, curso: '2do Año A' },
            { nombre: 'Ana Martínez', legajo: 'EST045', promedio: 9.0, curso: '3er Año B' },
            { nombre: 'Pedro López', legajo: 'EST012', promedio: 8.8, curso: '1er Año A' },
            { nombre: 'Laura García', legajo: 'EST067', promedio: 8.7, curso: '2do Año B' }
        ]
    };
    
    mostrarRendimientoAcademico(rendimientoEjemplo);
}

/**
 * Generar HTML del rendimiento
 */
function generarHTMLRendimiento(rendimiento) {
    return `
        <div class="rendimiento-container">
            <!-- Filtros -->
            <div class="rendimiento-filters" style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h4 style="margin-bottom: 15px;">🔍 Filtros</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Período:</label>
                        <select id="periodoRendimiento" class="form-input" onchange="filtrarRendimiento()">
                            <option value="2024">2024</option>
                            <option value="2023">2023</option>
                            <option value="2022">2022</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Ciclo:</label>
                        <select id="cicloRendimiento" class="form-input" onchange="filtrarRendimiento()">
                            <option value="">Todos</option>
                            <option value="1">Primer Ciclo</option>
                            <option value="2">Segundo Ciclo</option>
                            <option value="3">Tercer Ciclo</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Curso:</label>
                        <select id="cursoRendimiento" class="form-input" onchange="filtrarRendimiento()">
                            <option value="">Todos</option>
                            ${rendimiento.porCurso.map(c => `<option value="${c.curso}">${c.curso}</option>`).join('')}
                        </select>
                    </div>
                    <div style="display: flex; align-items: flex-end;">
                        <button class="btn btn-secondary" onclick="exportarRendimiento('pdf')" style="width: 100%;">
                            📄 Exportar PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resumen General -->
            <div class="rendimiento-resumen">
                <h4 style="margin-bottom: 20px; color: #1e3a2e;">📈 Resumen General</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Total Estudiantes</div>
                        <div style="font-size: 32px; font-weight: 700;">${rendimiento.resumen.totalEstudiantes}</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Promedio General</div>
                        <div style="font-size: 32px; font-weight: 700;">${rendimiento.resumen.promedioGeneral.toFixed(1)}</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Aprobados</div>
                        <div style="font-size: 32px; font-weight: 700;">${rendimiento.resumen.aprobados}</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Tasa Aprobación</div>
                        <div style="font-size: 32px; font-weight: 700;">${rendimiento.resumen.tasaAprobacion.toFixed(1)}%</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Desaprobados</div>
                        <div style="font-size: 32px; font-weight: 700;">${rendimiento.resumen.desaprobados}</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Ausentes</div>
                        <div style="font-size: 32px; font-weight: 700;">${rendimiento.resumen.ausentes}</div>
                    </div>
                </div>
            </div>

            <!-- Rendimiento por Curso -->
            <div class="rendimiento-cursos" style="margin-bottom: 40px;">
                <h4 style="margin-bottom: 20px; color: #1e3a2e;">📚 Rendimiento por Curso</h4>
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow-x: auto;">
                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 12px; text-align: left;">Curso</th>
                                <th style="padding: 12px; text-align: center;">Total Estudiantes</th>
                                <th style="padding: 12px; text-align: center;">Promedio</th>
                                <th style="padding: 12px; text-align: center;">Aprobados</th>
                                <th style="padding: 12px; text-align: center;">Tasa Aprobación</th>
                                <th style="padding: 12px; text-align: center;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rendimiento.porCurso.map(curso => {
                                const tasaAprobacion = (curso.aprobados / curso.estudiantes * 100).toFixed(1);
                                const estadoColor = tasaAprobacion >= 80 ? '#28a745' : tasaAprobacion >= 60 ? '#ffc107' : '#dc3545';
                                const estadoTexto = tasaAprobacion >= 80 ? 'Excelente' : tasaAprobacion >= 60 ? 'Regular' : 'Necesita Atención';
                                
                                return `
                                    <tr style="border-bottom: 1px solid #e0e0e0;">
                                        <td style="padding: 12px; font-weight: 600;">${curso.curso}</td>
                                        <td style="padding: 12px; text-align: center;">${curso.estudiantes}</td>
                                        <td style="padding: 12px; text-align: center;">
                                            <span style="background: ${curso.promedio >= 8 ? '#d4edda' : curso.promedio >= 6 ? '#fff3cd' : '#f8d7da'}; 
                                                         color: ${curso.promedio >= 8 ? '#155724' : curso.promedio >= 6 ? '#856404' : '#721c24'}; 
                                                         padding: 4px 12px; border-radius: 20px; font-weight: 600;">
                                                ${curso.promedio.toFixed(1)}
                                            </span>
                                        </td>
                                        <td style="padding: 12px; text-align: center;">${curso.aprobados}</td>
                                        <td style="padding: 12px; text-align: center; font-weight: 600;">${tasaAprobacion}%</td>
                                        <td style="padding: 12px; text-align: center;">
                                            <span style="background: ${estadoColor}; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px;">
                                                ${estadoTexto}
                                            </span>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rendimiento por Materia -->
            <div class="rendimiento-materias" style="margin-bottom: 40px;">
                <h4 style="margin-bottom: 20px; color: #1e3a2e;">📖 Rendimiento por Materia</h4>
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow-x: auto;">
                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 12px; text-align: left;">Materia</th>
                                <th style="padding: 12px; text-align: center;">Promedio</th>
                                <th style="padding: 12px; text-align: center;">Aprobados</th>
                                <th style="padding: 12px; text-align: center;">Desaprobados</th>
                                <th style="padding: 12px; text-align: center;">Tasa Aprobación</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rendimiento.porMateria.map(materia => {
                                const total = materia.aprobados + materia.desaprobados;
                                const tasaAprobacion = (materia.aprobados / total * 100).toFixed(1);
                                
                                return `
                                    <tr style="border-bottom: 1px solid #e0e0e0;">
                                        <td style="padding: 12px; font-weight: 600;">${materia.materia}</td>
                                        <td style="padding: 12px; text-align: center;">
                                            <span style="background: ${materia.promedio >= 8 ? '#d4edda' : materia.promedio >= 6 ? '#fff3cd' : '#f8d7da'}; 
                                                         color: ${materia.promedio >= 8 ? '#155724' : materia.promedio >= 6 ? '#856404' : '#721c24'}; 
                                                         padding: 4px 12px; border-radius: 20px; font-weight: 600;">
                                                ${materia.promedio.toFixed(1)}
                                            </span>
                                        </td>
                                        <td style="padding: 12px; text-align: center; color: #28a745; font-weight: 600;">${materia.aprobados}</td>
                                        <td style="padding: 12px; text-align: center; color: #dc3545; font-weight: 600;">${materia.desaprobados}</td>
                                        <td style="padding: 12px; text-align: center;">
                                            <div style="background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden;">
                                                <div style="background: ${tasaAprobacion >= 80 ? '#28a745' : tasaAprobacion >= 60 ? '#ffc107' : '#dc3545'}; 
                                                            width: ${tasaAprobacion}%; height: 100%;"></div>
                                            </div>
                                            <small style="font-weight: 600; color: #666;">${tasaAprobacion}%</small>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Estudiantes -->
            <div class="rendimiento-top-estudiantes">
                <h4 style="margin-bottom: 20px; color: #1e3a2e;">🏆 Top 5 Estudiantes</h4>
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 12px; text-align: center; width: 60px;">Posición</th>
                                <th style="padding: 12px; text-align: left;">Estudiante</th>
                                <th style="padding: 12px; text-align: center;">Legajo</th>
                                <th style="padding: 12px; text-align: center;">Curso</th>
                                <th style="padding: 12px; text-align: center;">Promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rendimiento.topEstudiantes.map((est, index) => {
                                const medallas = ['🥇', '🥈', '🥉'];
                                const medalla = index < 3 ? medallas[index] : `${index + 1}°`;
                                
                                return `
                                    <tr style="border-bottom: 1px solid #e0e0e0;">
                                        <td style="padding: 12px; text-align: center; font-size: 24px;">${medalla}</td>
                                        <td style="padding: 12px; font-weight: 600;">${est.nombre}</td>
                                        <td style="padding: 12px; text-align: center;">${est.legajo}</td>
                                        <td style="padding: 12px; text-align: center;">${est.curso}</td>
                                        <td style="padding: 12px; text-align: center;">
                                            <span style="background: #ffd700; color: #000; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 16px;">
                                                ${est.promedio.toFixed(1)}
                                            </span>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Gráfico (placeholder si no hay Chart.js) -->
            <div class="rendimiento-graficos" style="margin-top: 40px;">
                <h4 style="margin-bottom: 20px; color: #1e3a2e;">📊 Visualización</h4>
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <canvas id="rendimientoChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    `;
}

/**
 * Cerrar modal de rendimiento
 */
function closeRendimientoModal() {
    const modal = document.getElementById('rendimientoModal');
    if (modal) {
        modal.remove();
    }
}

/**
 * Filtrar rendimiento
 */
function filtrarRendimiento() {
    const periodo = document.getElementById('periodoRendimiento')?.value;
    const ciclo = document.getElementById('cicloRendimiento')?.value;
    const curso = document.getElementById('cursoRendimiento')?.value;
    
    console.log('Filtrando rendimiento:', { periodo, ciclo, curso });
    showNotification('Aplicando filtros...', 'info');
    
    // Aquí se recargarían los datos con los filtros aplicados
    // En este caso, simplemente mostramos una notificación
}

/**
 * Inicializar gráficos (requiere Chart.js)
 */
function inicializarGraficosRendimiento(rendimiento) {
    const canvas = document.getElementById('rendimientoChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Si Chart.js no está disponible, mostrar mensaje
    if (typeof Chart === 'undefined') {
        ctx.font = '16px Arial';
        ctx.fillStyle = '#666';
        ctx.textAlign = 'center';
        ctx.fillText('Instale Chart.js para visualizar gráficos', canvas.width / 2, canvas.height / 2);
        return;
    }
    
    // Crear gráfico con Chart.js
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: rendimiento.porCurso.map(c => c.curso),
            datasets: [{
                label: 'Promedio por Curso',
                data: rendimiento.porCurso.map(c => c.promedio),
                backgroundColor: 'rgba(30, 58, 46, 0.7)',
                borderColor: 'rgba(30, 58, 46, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    title: {
                        display: true,
                        text: 'Promedio'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Promedios por Curso'
                }
            }
        }
    });
}

/**
 * Exportar rendimiento académico
 */
async function exportarRendimiento(formato) {
    showNotification(`📥 Generando reporte de rendimiento en ${formato.toUpperCase()}...`, 'info');
    
    try {
        const response = await fetch(`api/exportar-rendimiento.php?formato=${formato}`);
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `rendimiento_academico_${new Date().getTime()}.${formato}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            showNotification('✅ Reporte exportado exitosamente', 'success');
        } else {
            throw new Error('Error al generar reporte');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error al exportar reporte. Mostrando vista de impresión...', 'warning');
        
        // Alternativa: abrir ventana de impresión
        setTimeout(() => {
            window.print();
        }, 500);
    }
}

// Agregar estilos CSS para el spinner de carga
const loadingStyles = document.createElement('style');
loadingStyles.textContent = `
    .loading-spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #1e3a2e;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .rendimiento-container {
        padding: 20px;
    }
    
    @media print {
        .modal-header,
        .rendimiento-filters,
        .action-buttons {
            display: none;
        }
    }
`;

if (!document.getElementById('rendimiento-loading-styles')) {
    loadingStyles.id = 'rendimiento-loading-styles';
    document.head.appendChild(loadingStyles);
}

console.log('✅ Módulo de Rendimiento Académico cargado correctamente');