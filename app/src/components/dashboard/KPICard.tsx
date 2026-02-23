import { TrendingUp, TrendingDown, type LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

interface KPICardProps {
  title: string;
  value: number | string;
  subtitle?: string;
  change?: {
    value: number;
    direction: 'up' | 'down';
  };
  icon: LucideIcon;
  color?: 'teal' | 'blue' | 'green' | 'orange' | 'red' | 'purple';
  currency?: string;
  loading?: boolean;
}

const colorClasses = {
  teal: 'bg-teal-50 dark:bg-teal-900/20 text-teal-600 dark:text-teal-400',
  blue: 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400',
  green: 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400',
  orange: 'bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400',
  red: 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400',
  purple: 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400',
};

export function KPICard({
  title,
  value,
  subtitle,
  change,
  icon: Icon,
  color = 'teal',
  currency,
  loading = false,
}: KPICardProps) {
  const formatValue = (val: number | string) => {
    if (typeof val === 'number') {
      if (currency) {
        return new Intl.NumberFormat('fr-FR', {
          style: 'currency',
          currency: 'EUR',
        }).format(val);
      }
      return new Intl.NumberFormat('fr-FR').format(val);
    }
    return val;
  };

  if (loading) {
    return (
      <div className="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 animate-pulse">
        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/3 mb-4" />
        <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-2/3 mb-2" />
        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2" />
      </div>
    );
  }

  return (
    <div className="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md transition-shadow">
      <div className="flex items-start justify-between">
        <div className="flex-1">
          <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
            {title}
          </p>
          <h3 className="text-2xl lg:text-3xl font-bold text-gray-900 dark:text-white mt-2">
            {formatValue(value)}
          </h3>
          {subtitle && (
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
              {subtitle}
            </p>
          )}
          {change && (
            <div className="flex items-center gap-1 mt-3">
              {change.direction === 'up' ? (
                <TrendingUp className="w-4 h-4 text-green-500" />
              ) : (
                <TrendingDown className="w-4 h-4 text-red-500" />
              )}
              <span
                className={cn(
                  'text-sm font-medium',
                  change.direction === 'up' ? 'text-green-600' : 'text-red-600'
                )}
              >
                {change.value > 0 ? '+' : ''}{change.value}%
              </span>
              <span className="text-sm text-gray-400">vs période précédente</span>
            </div>
          )}
        </div>
        <div
          className={cn(
            'w-12 h-12 rounded-xl flex items-center justify-center',
            colorClasses[color]
          )}
        >
          <Icon className="w-6 h-6" />
        </div>
      </div>
    </div>
  );
}
