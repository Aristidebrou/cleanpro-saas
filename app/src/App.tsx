import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from '@/context/AuthContext';
import { Sidebar } from '@/components/layout/Sidebar';
import { Header } from '@/components/layout/Header';
import { Login } from '@/pages/Login';
import { Dashboard } from '@/pages/Dashboard';
import { Interventions } from '@/pages/Interventions';
import { Planning } from '@/pages/Planning';
import { useState } from 'react';
import { cn } from '@/lib/utils';

// Layout protégé avec sidebar
function ProtectedLayout({ children }: { children: React.ReactNode }) {
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <Sidebar
        isCollapsed={isSidebarCollapsed}
        onToggle={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
        isMobileOpen={isMobileMenuOpen}
        onMobileClose={() => setIsMobileMenuOpen(false)}
      />
      <Header
        onMenuClick={() => setIsMobileMenuOpen(true)}
        isSidebarCollapsed={isSidebarCollapsed}
      />
      <main
        className={cn(
          "pt-16 min-h-screen transition-all duration-300",
          isSidebarCollapsed ? "lg:pl-16" : "lg:pl-64"
        )}
      >
        <div className="p-4 lg:p-6">
          {children}
        </div>
      </main>
    </div>
  );
}

// Route protégée
function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <ProtectedLayout>{children}</ProtectedLayout>;
}

// Route publique (redirige si déjà connecté)
function PublicRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500" />
      </div>
    );
  }

  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  return <>{children}</>;
}

function AppRoutes() {
  return (
    <Routes>
      <Route
        path="/login"
        element={
          <PublicRoute>
            <Login />
          </PublicRoute>
        }
      />
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <Dashboard />
          </ProtectedRoute>
        }
      />
      <Route
        path="/interventions"
        element={
          <ProtectedRoute>
            <Interventions />
          </ProtectedRoute>
        }
      />
      <Route
        path="/planning"
        element={
          <ProtectedRoute>
            <Planning />
          </ProtectedRoute>
        }
      />
      {/* Routes à implémenter */}
      <Route
        path="/clients"
        element={
          <ProtectedRoute>
            <div className="text-center py-12">
              <h2 className="text-xl font-semibold text-gray-600">Page Clients en développement</h2>
            </div>
          </ProtectedRoute>
        }
      />
      <Route
        path="/invoices"
        element={
          <ProtectedRoute>
            <div className="text-center py-12">
              <h2 className="text-xl font-semibold text-gray-600">Page Factures en développement</h2>
            </div>
          </ProtectedRoute>
        }
      />
      <Route
        path="/statistics"
        element={
          <ProtectedRoute>
            <div className="text-center py-12">
              <h2 className="text-xl font-semibold text-gray-600">Page Statistiques en développement</h2>
            </div>
          </ProtectedRoute>
        }
      />
      <Route
        path="/settings"
        element={
          <ProtectedRoute>
            <div className="text-center py-12">
              <h2 className="text-xl font-semibold text-gray-600">Page Paramètres en développement</h2>
            </div>
          </ProtectedRoute>
        }
      />
      {/* Espace client */}
      <Route
        path="/client/dashboard"
        element={
          <ProtectedRoute>
            <div className="text-center py-12">
              <h2 className="text-xl font-semibold text-gray-600">Espace Client en développement</h2>
            </div>
          </ProtectedRoute>
        }
      />
      <Route
        path="*"
        element={<Navigate to="/" replace />}
      />
    </Routes>
  );
}

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <AppRoutes />
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
