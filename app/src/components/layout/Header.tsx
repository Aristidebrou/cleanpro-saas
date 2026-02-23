import { Menu, Bell, Search } from 'lucide-react';
import { useAuth } from '@/context/AuthContext';
import { cn } from '@/lib/utils';

interface HeaderProps {
  onMenuClick: () => void;
  isSidebarCollapsed: boolean;
}

export function Header({ onMenuClick, isSidebarCollapsed }: HeaderProps) {
  const { user } = useAuth();

  return (
    <header
      className={cn(
        "fixed top-0 right-0 z-20 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 transition-all duration-300",
        isSidebarCollapsed ? "left-16" : "left-64"
      )}
    >
      <div className="flex items-center justify-between h-16 px-4 lg:px-6">
        {/* Left side */}
        <div className="flex items-center gap-4">
          <button
            onClick={onMenuClick}
            className="lg:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <Menu className="w-5 h-5 text-gray-600 dark:text-gray-400" />
          </button>

          {/* Search */}
          <div className="hidden md:flex items-center gap-2 bg-gray-100 dark:bg-gray-700 rounded-lg px-3 py-2">
            <Search className="w-4 h-4 text-gray-400" />
            <input
              type="text"
              placeholder="Rechercher..."
              className="bg-transparent border-none outline-none text-sm text-gray-700 dark:text-gray-300 w-48 lg:w-64"
            />
          </div>
        </div>

        {/* Right side */}
        <div className="flex items-center gap-4">
          {/* Notifications */}
          <button className="relative p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <Bell className="w-5 h-5 text-gray-600 dark:text-gray-400" />
            <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
          </button>

          {/* User avatar (mobile) */}
          <div className="lg:hidden flex items-center gap-2">
            <div className="w-8 h-8 bg-teal-100 dark:bg-teal-900 rounded-full flex items-center justify-center">
              <span className="text-sm font-medium text-teal-600 dark:text-teal-400">
                {user?.first_name[0]}{user?.last_name[0]}
              </span>
            </div>
          </div>
        </div>
      </div>
    </header>
  );
}
