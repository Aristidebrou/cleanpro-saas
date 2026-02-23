// Types pour l'application CleanPro SaaS

export interface User {
  id: number;
  email: string;
  first_name: string;
  last_name: string;
  phone?: string;
  role: 'admin' | 'agent' | 'client';
  is_active: boolean;
  last_login?: string;
  created_at: string;
}

export interface Client {
  id: number;
  user_id?: number;
  company_name: string;
  contact_name: string;
  contact_email: string;
  contact_phone?: string;
  address?: string;
  postal_code?: string;
  city?: string;
  siret?: string;
  vat_number?: string;
  billing_type: 'one_time' | 'monthly' | 'annual';
  monthly_quota: number;
  quota_used: number;
  monthly_amount: number;
  notes?: string;
  status: 'active' | 'inactive' | 'suspended';
  created_at: string;
  updated_at: string;
}

export interface Service {
  id: number;
  name: string;
  description?: string;
  category: 'cleaning' | 'gardening' | 'maintenance';
  base_price: number;
  unit: string;
  estimated_duration: number;
  status: 'active' | 'inactive';
}

export interface InterventionService {
  id: number;
  service_id: number;
  name: string;
  quantity: number;
  unit_price: number;
  total_price: number;
  notes?: string;
}

export interface Intervention {
  id: number;
  reference: string;
  client_id: number;
  agent_id: number;
  scheduled_date: string;
  scheduled_time: string;
  estimated_end_time?: string;
  actual_start_time?: string;
  actual_end_time?: string;
  type: 'one_time' | 'recurring';
  status: 'scheduled' | 'in_progress' | 'completed' | 'validated' | 'cancelled';
  priority: 'low' | 'normal' | 'high' | 'urgent';
  total_amount: number;
  notes?: string;
  agent_signature?: string;
  client_signature?: string;
  client_feedback?: string;
  client_rating?: number;
  validated_at?: string;
  // Relations
  client_company?: string;
  client_address?: string;
  client_city?: string;
  client_postal_code?: string;
  agent_name?: string;
  agent_phone?: string;
  services?: InterventionService[];
}

export interface InvoiceItem {
  id: number;
  description: string;
  quantity: number;
  unit_price: number;
  total_price: number;
  service_id?: number;
}

export interface Invoice {
  id: number;
  invoice_number: string;
  client_id: number;
  intervention_id?: number;
  type: 'one_time' | 'recurring';
  status: 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';
  issue_date: string;
  due_date: string;
  paid_at?: string;
  payment_method?: string;
  transaction_id?: string;
  subtotal: number;
  discount_amount: number;
  discount_reason?: string;
  tax_amount: number;
  total_amount: number;
  notes?: string;
  sent_at?: string;
  // Relations
  client_company?: string;
  client_address?: string;
  client_city?: string;
  client_postal_code?: string;
  client_contact?: string;
  client_email?: string;
  items?: InvoiceItem[];
  interventions?: Intervention[];
}

export interface PromoCode {
  id: number;
  code: string;
  description?: string;
  discount_type: 'percentage' | 'fixed';
  discount_value: number;
  max_uses?: number;
  used_count: number;
  valid_from?: string;
  valid_until?: string;
  is_active: boolean;
}

export interface DashboardKPI {
  value: number;
  change?: {
    value: number;
    direction: 'up' | 'down';
  };
  currency?: string;
}

export interface DashboardData {
  kpis: {
    revenue: DashboardKPI;
    interventions: DashboardKPI & { completed: number };
    clients: { total: number; active: number; retention_rate: number };
    profitability: DashboardKPI & { margin: number };
    outstanding: DashboardKPI & { count: number };
  };
  charts: {
    revenue_evolution: ChartData;
    interventions_by_type: ChartData;
    agent_workload: AgentWorkload[];
    client_retention: ClientRetentionData;
    monthly_comparison: ChartData;
  };
  recent_activity: {
    interventions: Intervention[];
    invoices: Invoice[];
  };
  alerts: Alert[];
}

export interface ChartData {
  labels: string[];
  datasets: {
    label?: string;
    data: number[];
    borderColor?: string;
    backgroundColor?: string | string[];
    fill?: boolean;
    yAxisID?: string;
  }[];
}

export interface AgentWorkload {
  name: string;
  total_interventions: number;
  total_revenue: number;
  daily_data: Record<string, { count: number; revenue: number }>;
}

export interface ClientRetentionData {
  retention_rate: number;
  active_clients: number;
  total_clients: number;
  new_clients_by_month: { month: string; new_clients: number }[];
}

export interface Alert {
  type: 'success' | 'warning' | 'info' | 'error';
  title: string;
  message: string;
  action?: string;
}

export interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data?: T;
  errors?: Record<string, string>;
}
