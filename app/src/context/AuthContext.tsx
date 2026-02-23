import { createContext, useContext, useState, useEffect, type ReactNode } from 'react';
import { authService } from '@/services/api';
import type { User, AuthState } from '@/types';

interface AuthContextType extends AuthState {
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
  updateUser: (user: User) => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({
    user: null,
    token: localStorage.getItem('token'),
    isAuthenticated: false,
    isLoading: true,
  });

  useEffect(() => {
    const initAuth = async () => {
      const token = localStorage.getItem('token');
      const savedUser = localStorage.getItem('user');

      if (token && savedUser) {
        try {
          setState(prev => ({
            ...prev,
            token,
            user: JSON.parse(savedUser),
            isAuthenticated: true,
          }));
          
          // Vérifier que le token est toujours valide
          await authService.getProfile();
        } catch (error) {
          // Token invalide, déconnexion
          logout();
        }
      }

      setState(prev => ({ ...prev, isLoading: false }));
    };

    initAuth();
  }, []);

  const login = async (email: string, password: string) => {
    const response = await authService.login(email, password);
    const { user, token } = response.data.data;

    localStorage.setItem('token', token);
    localStorage.setItem('user', JSON.stringify(user));

    setState({
      user,
      token,
      isAuthenticated: true,
      isLoading: false,
    });
  };

  const logout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    
    setState({
      user: null,
      token: null,
      isAuthenticated: false,
      isLoading: false,
    });
  };

  const updateUser = (user: User) => {
    localStorage.setItem('user', JSON.stringify(user));
    setState(prev => ({ ...prev, user }));
  };

  return (
    <AuthContext.Provider value={{ ...state, login, logout, updateUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
