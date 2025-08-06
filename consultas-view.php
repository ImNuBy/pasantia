import React, { useState } from 'react';
import { Users, BookOpen, Calendar, BarChart3, Settings, Bell, Search, Menu, X, GraduationCap, FileText, UserCheck, TrendingUp } from 'lucide-react';

const Dashboard = () => {
  const [activeSection, setActiveSection] = useState('overview');
  const [sidebarOpen, setSidebarOpen] = useState(false);

  // Datos simulados
  const stats = {
    totalStudents: 1247,
    totalTeachers: 68,
    totalClasses: 42,
    attendance: 94.2
  };

  const recentActivities = [
    { id: 1, action: 'Nuevo estudiante registrado', user: 'María González', time: '2 min ago' },
    { id: 2, action: 'Reporte de calificaciones generado', user: 'Prof. Rodríguez', time: '15 min ago' },
    { id: 3, action: 'Evento escolar programado', user: 'Coordinación', time: '1 hora ago' },
    { id: 4, action: 'Reunión de padres confirmada', user: 'Secretaría', time: '2 horas ago' }
  ];

  const upcomingEvents = [
    { id: 1, title: 'Reunión de Padres 1er Año', date: '2025-08-02', time: '14:00' },
    { id: 2, title: 'Examen Final Matemáticas', date: '2025-08-05', time: '08:00' },
    { id: 3, title: 'Acto de Fin de Año', date: '2025-08-15', time: '10:00' },
    { id: 4, title: 'Inscripciones Ciclo 2026', date: '2025-08-20', time: '09:00' }
  ];

  const menuItems = [
    { id: 'overview', label: 'Panel General', icon: BarChart3 },
    { id: 'students', label: 'Estudiantes', icon: Users },
    { id: 'teachers', label: 'Profesores', icon: GraduationCap },
    { id: 'classes', label: 'Clases', icon: BookOpen },
    { id: 'reports', label: 'Reportes', icon: FileText },
    { id: 'events', label: 'Eventos', icon: Calendar },
    { id: 'settings', label: 'Configuración', icon: Settings }
  ];

  const renderContent = () => {
    switch (activeSection) {
      case 'overview':
        return (
          <div className="space-y-6">
            {/* Estadísticas principales */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <div className="bg-white p-6 rounded-lg shadow-sm border">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Total Estudiantes</p>
                    <p className="text-2xl font-bold text-gray-900">{stats.totalStudents}</p>
                  </div>
                  <Users className="h-8 w-8 text-blue-600" />
                </div>
                <p className="text-xs text-green-600 mt-2">+5.2% vs mes anterior</p>
              </div>

              <div className="bg-white p-6 rounded-lg shadow-sm border">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Profesores</p>
                    <p className="text-2xl font-bold text-gray-900">{stats.totalTeachers}</p>
                  </div>
                  <GraduationCap className="h-8 w-8 text-green-600" />
                </div>
                <p className="text-xs text-green-600 mt-2">+2 nuevos este mes</p>
              </div>

              <div className="bg-white p-6 rounded-lg shadow-sm border">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Clases Activas</p>
                    <p className="text-2xl font-bold text-gray-900">{stats.totalClasses}</p>
                  </div>
                  <BookOpen className="h-8 w-8 text-purple-600" />
                </div>
                <p className="text-xs text-blue-600 mt-2">3 turnos disponibles</p>
              </div>

              <div className="bg-white p-6 rounded-lg shadow-sm border">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Asistencia</p>
                    <p className="text-2xl font-bold text-gray-900">{stats.attendance}%</p>
                  </div>
                  <UserCheck className="h-8 w-8 text-orange-600" />
                </div>
                <p className="text-xs text-green-600 mt-2">+1.2% vs semana pasada</p>
              </div>
            </div>

            {/* Actividades recientes y eventos */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div className="bg-white p-6 rounded-lg shadow-sm border">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Actividades Recientes</h3>
                <div className="space-y-4">
                  {recentActivities.map(activity => (
                    <div key={activity.id} className="flex items-start space-x-3">
                      <div className="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                      <div className="flex-1">
                        <p className="text-sm font-medium text-gray-900">{activity.action}</p>
                        <p className="text-xs text-gray-500">{activity.user} • {activity.time}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div className="bg-white p-6 rounded-lg shadow-sm border">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Próximos Eventos</h3>
                <div className="space-y-4">
                  {upcomingEvents.map(event => (
                    <div key={event.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                      <div>
                        <p className="text-sm font-medium text-gray-900">{event.title}</p>
                        <p className="text-xs text-gray-500">{event.time}</p>
                      </div>
                      <div className="text-right">
                        <p className="text-xs font-medium text-blue-600">{event.date}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        );

      case 'students':
        return (
          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Gestión de Estudiantes</h3>
            <div className="mb-4">
              <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                Agregar Estudiante
              </button>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Año</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DNI</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  <tr>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Ana María Pérez</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">3ro</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">45.123.456</td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Activo</span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <button className="text-blue-600 hover:text-blue-900 mr-3">Editar</button>
                      <button className="text-red-600 hover:text-red-900">Eliminar</button>
                    </td>
                  </tr>
                  <tr>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Carlos González</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">5to</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">44.987.654</td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Activo</span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <button className="text-blue-600 hover:text-blue-900 mr-3">Editar</button>
                      <button className="text-red-600 hover:text-red-900">Eliminar</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        );

      case 'teachers':
        return (
          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Gestión de Profesores</h3>
            <div className="mb-4">
              <button className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                Agregar Profesor
              </button>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div className="border rounded-lg p-4">
                <h4 className="font-semibold text-gray-900">Prof. María Rodríguez</h4>
                <p className="text-sm text-gray-600">Matemáticas</p>
                <p className="text-xs text-gray-500 mt-2">15 años de experiencia</p>
                <div className="mt-3">
                  <span className="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">Titular</span>
                </div>
              </div>
              <div className="border rounded-lg p-4">
                <h4 className="font-semibold text-gray-900">Prof. Juan López</h4>
                <p className="text-sm text-gray-600">Historia</p>
                <p className="text-xs text-gray-500 mt-2">8 años de experiencia</p>
                <div className="mt-3">
                  <span className="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Activo</span>
                </div>
              </div>
              <div className="border rounded-lg p-4">
                <h4 className="font-semibold text-gray-900">Prof. Ana García</h4>
                <p className="text-sm text-gray-600">Lengua y Literatura</p>
                <p className="text-xs text-gray-500 mt-2">12 años de experiencia</p>
                <div className="mt-3">
                  <span className="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">Titular</span>
                </div>
              </div>
            </div>
          </div>
        );

      case 'reports':
        return (
          <div className="space-y-6">
            <div className="bg-white p-6 rounded-lg shadow-sm border">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Generar Reportes</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <button className="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-colors">
                  <FileText className="h-8 w-8 text-gray-400 mx-auto mb-2" />
                  <p className="text-sm font-medium text-gray-900">Reporte de Asistencia</p>
                  <p className="text-xs text-gray-500">Mensual/Semanal</p>
                </button>
                <button className="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 transition-colors">
                  <TrendingUp className="h-8 w-8 text-gray-400 mx-auto mb-2" />
                  <p className="text-sm font-medium text-gray-900">Rendimiento Académico</p>
                  <p className="text-xs text-gray-500">Por materia/año</p>
                </button>
                <button className="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-colors">
                  <Users className="h-8 w-8 text-gray-400 mx-auto mb-2" />
                  <p className="text-sm font-medium text-gray-900">Estadísticas de Matrícula</p>
                  <p className="text-xs text-gray-500">Inscripciones</p>
                </button>
              </div>
            </div>
          </div>
        );

      default:
        return (
          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">
              {menuItems.find(item => item.id === activeSection)?.label}
            </h3>
            <p className="text-gray-600">Sección en desarrollo...</p>
          </div>
        );
    }
  };

  return (
    <div className="flex h-screen bg-gray-100">
      {/* Sidebar */}
      <div className={`${sidebarOpen ? 'translate-x-0' : '-translate-x-full'} fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0`}>
        <div className="flex items-center justify-between h-16 px-6 border-b">
          <h1 className="text-xl font-bold text-gray-900">EPA 703</h1>
          <button
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden"
          >
            <X className="h-6 w-6" />
          </button>
        </div>
        
        <nav className="mt-6">
          {menuItems.map((item) => {
            const Icon = item.icon;
            return (
              <button
                key={item.id}
                onClick={() => {
                  setActiveSection(item.id);
                  setSidebarOpen(false);
                }}
                className={`w-full flex items-center px-6 py-3 text-left hover:bg-gray-50 transition-colors ${
                  activeSection === item.id ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700' : 'text-gray-700'
                }`}
              >
                <Icon className="h-5 w-5 mr-3" />
                {item.label}
              </button>
            );
          })}
        </nav>
      </div>

      {/* Main content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Header */}
        <header className="bg-white shadow-sm border-b">
          <div className="flex items-center justify-between h-16 px-6">
            <div className="flex items-center">
              <button
                onClick={() => setSidebarOpen(true)}
                className="lg:hidden mr-4"
              >
                <Menu className="h-6 w-6" />
              </button>
              <h2 className="text-xl font-semibold text-gray-900">
                Panel de Administración
              </h2>
            </div>
            
            <div className="flex items-center space-x-4">
              <div className="relative">
                <Search className="h-5 w-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                <input
                  type="text"
                  placeholder="Buscar..."
                  className="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <button className="relative p-2 text-gray-400 hover:text-gray-600">
                <Bell className="h-6 w-6" />
                <span className="absolute top-0 right-0 h-2 w-2 bg-red-500 rounded-full"></span>
              </button>
              <div className="h-8 w-8 bg-blue-600 rounded-full flex items-center justify-center">
                <span className="text-sm font-medium text-white">AD</span>
              </div>
            </div>
          </div>
        </header>

        {/* Main content area */}
        <main className="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
          {renderContent()}
        </main>
      </div>

      {/* Overlay for mobile */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        ></div>
      )}
    </div>
  );
};

export default Dashboard;