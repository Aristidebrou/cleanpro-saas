import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import {
  LayoutDashboard,
  Users,
  Calendar,
  FileText,
  Settings,
  ChevronLeft,
  ChevronRight,
  LogOut,
  Briefcase,
  BarChart3,
  X
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface SidebarProps {
  isCollapsed: boolean;
  onToggle: () => void;
  isMobileOpen: boolean;
  onMobileClose: () => void;
}

const menuItems = [
  { path: '/', icon: LayoutDashboard, label: 'Dashboard', roles: ['admin', 'agent'] },
  { path: '/clients', icon: Users, label: 'Clients', roles: ['admin'] },
  { path: '/interventions', icon: Briefcase, label: 'Interventions', roles: ['admin', 'agent'] },
  { path: '/planning', icon: Calendar, label: 'Planning', roles: ['admin', 'agent'] },
  { path: '/invoices', icon: FileText, label: 'Factures', roles: ['admin'] },
  { path: '/statistics', icon: BarChart3, label: 'Statistiques', roles: ['admin'] },
  { path: '/settings', icon: Settings, label: 'Paramètres', roles: ['admin'] },
];

const clientMenuItems = [
  { path: '/client/dashboard', icon: LayoutDashboard, label: 'Mon espace', roles: ['client'] },
  { path: '/client/interventions', icon: Briefcase, label: 'Mes interventions', roles: ['client'] },
  { path: '/client/invoices', icon: FileText, label: 'Mes factures', roles: ['client'] },
  { path: '/client/quote', icon: Calendar, label: 'Demander un devis', roles: ['client'] },
];

export function Sidebar({ isCollapsed, onToggle, isMobileOpen, onMobileClose }: SidebarProps) {
  const location = useLocation();
  const { user, logout } = useAuth();
  const [isDarkMode, setIsDarkMode] = useState(false);

  const isActive = (path: string) => location.pathname === path;

  const toggleDarkMode = () => {
    setIsDarkMode(!isDarkMode);
    document.documentElement.classList.toggle('dark');
  };

  const handleLogout = () => {
    logout();
  };

  const items = user?.role === 'client' ? clientMenuItems : menuItems;

  const SidebarContent = () => (
    <div className="flex flex-col h-full">
      {/* Logo */}
      <div className={cn(
        "flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700",
        isCollapsed && "justify-center"
      )}>
        <Link to="/" className="flex items-center gap-2">
          <div className="w-8 h-8 bg-teal-500 rounded-lg flex items-center justify-center">
            <Briefcase className="w-5 h-5 text-white" />
          </div>
          {!isCollapsed && (
            <span className="font-bold text-xl text-gray-900 dark:text-white">
              CleanPro
            </span>
          )}
        </Link>
        {!isCollapsed && (
          <button
            onClick={onToggle}
            className="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 hidden lg:block"
          >
            <ChevronLeft className="w-5 h-5 text-gray-500" />
          </button>
        )}
        {isCollapsed && (
          <button
            onClick={onToggle}
            className="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 hidden lg:block absolute -right-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-full"
          >
            <ChevronRight className="w-4 h-4 text-gray-500" />
          </button>
        )}
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto p-4 space-y-1">
        {items
          .filter(item => item.roles.includes(user?.role || ''))
          .map((item) => (
            <Link
              key={item.path}
              to={item.path}
              onClick={onMobileClose}
              className={cn(
                "flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors",
                isActive(item.path)
                  ? "bg-teal-50 dark:bg-teal-900/20 text-teal-600 dark:text-teal-400"
                  : "text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700",
                isCollapsed && "justify-center px-2"
              )}
              title={isCollapsed ? item.label : undefined}
            >
              <item.icon className={cn(
                "w-5 h-5 flex-shrink-0",
                isActive(item.path) && "text-teal-600 dark:text-teal-400"
              )} />
              {!isCollapsed && <span className="font-medium">{item.label}</span>}
            </Link>
          ))}
      </nav>

      {/* Footer */}
      <div className="p-4 border-t border-gray-200 dark:border-gray-700 space-y-2">
        {/* Dark mode toggle */}
        <button
          onClick={toggleDarkMode}
          className={cn(
            "flex items-center gap-3 px-3 py-2 w-full rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors",
            isCollapsed && "justify-center px-2"
          )}
          title={isCollapsed ? 'Mode sombre/clair' : undefined}
        >
          <div className="w-5 h-5 flex items-center justify-center">
            {isDarkMode ? (
              <span className="text-yellow-500">☀</span>
            ) : (
              <span className="text-gray-600">🌙</span>
            )}
          </div>
          {!isCollapsed && <span className="font-medium">{isDarkMode ? 'Mode clair' : 'Mode sombre'}</span>}
        </button>

        {/* Logout */}
        <button
          onClick={handleLogout}
          className={cn(
            "flex items-center gap-3 px-3 py-2 w-full rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors",
            isCollapsed && "justify-center px-2"
          )}
          title={isCollapsed ? 'Déconnexion' : undefined}
        >
          <LogOut className="w-5 h-5 flex-shrink-0" />
          {!isCollapsed && <span className="font-medium">Déconnexion</span>}
        </button>

        {/* User info */}
        {!isCollapsed && user && (
          <div className="flex items-center gap-3 px-3 py-2 mt-2">
            <div className="w-8 h-8 bg-teal-100 dark:bg-teal-900 rounded-full flex items-center justify-center">
              <span className="text-sm font-medium text-teal-600 dark:text-teal-400">
                {user.first_name[0]}{user.last_name[0]}
              </span>
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                {user.first_name} {user.last_name}
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400 capitalize">
                {user.role}
              </p>
            </div>
          </div>
        )}
      </div>
    </div>
  );

  return (
    <>
      {/* Mobile overlay */}
      {isMobileOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={onMobileClose}
        />
      )}

      {/* Mobile sidebar */}
      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-gray-800 shadow-xl transform transition-transform duration-300 lg:hidden",
          isMobileOpen ? "translate-x-0" : "-translate-x-full"
        )}
      >
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <span className="font-bold text-xl text-gray-900 dark:text-white">CleanPro</span>
          <button onClick={onMobileClose} className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <X className="w-5 h-5 text-gray-500" />
          </button>
        </div>
        <SidebarContent />
      </aside>

      {/* Desktop sidebar */}
      <aside
        className={cn(
          "hidden lg:block fixed inset-y-0 left-0 z-30 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transition-all duration-300",
          isCollapsed ? "w-16" : "w-64"
        )}
      >
        <SidebarContent />
      </aside>
    </>
  );
}
