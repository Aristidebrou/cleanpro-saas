import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js';
import { Line, Bar, Doughnut } from 'react-chartjs-2';
import type { ChartData } from '@/types';

// Enregistrement des composants Chart.js
ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

// Options communes
const commonOptions: any = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12,
        },
      },
    },
  },
};

interface LineChartProps {
  data: ChartData;
  height?: number;
}

export function RevenueChart({ data, height = 300 }: LineChartProps) {
  const options: any = {
    ...commonOptions,
    plugins: {
      ...commonOptions.plugins,
      title: {
        display: true,
        text: 'Évolution du chiffre d\'affaires',
        font: { size: 14, weight: 'bold' },
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: (value: any) =>
            new Intl.NumberFormat('fr-FR', {
              style: 'currency',
              currency: 'EUR',
              notation: 'compact',
            }).format(value),
        },
      },
    },
  };

  return (
    <div style={{ height }}>
      <Line data={data} options={options} />
    </div>
  );
}

interface BarChartProps {
  data: ChartData;
  height?: number;
  title?: string;
}

export function MonthlyComparisonChart({ data, height = 300, title = 'Comparaison mensuelle' }: BarChartProps) {
  const options: any = {
    ...commonOptions,
    plugins: {
      ...commonOptions.plugins,
      title: {
        display: true,
        text: title,
        font: { size: 14, weight: 'bold' },
      },
    },
    scales: {
      y: {
        type: 'linear',
        display: true,
        position: 'left',
        ticks: {
          callback: (value: any) =>
            new Intl.NumberFormat('fr-FR', {
              notation: 'compact',
            }).format(value),
        },
      },
      y1: {
        type: 'linear',
        display: true,
        position: 'right',
        grid: {
          drawOnChartArea: false,
        },
      },
    },
  };

  return (
    <div style={{ height }}>
      <Bar data={data} options={options} />
    </div>
  );
}

interface DoughnutChartProps {
  data: ChartData;
  height?: number;
  title?: string;
}

export function InterventionsByTypeChart({ data, height = 250, title = 'Interventions par type' }: DoughnutChartProps) {
  const options: any = {
    ...commonOptions,
    plugins: {
      ...commonOptions.plugins,
      title: {
        display: true,
        text: title,
        font: { size: 14, weight: 'bold' },
      },
    },
  };

  return (
    <div style={{ height }}>
      <Doughnut data={data} options={options} />
    </div>
  );
}

interface AgentWorkloadChartProps {
  data: {
    name: string;
    total_interventions: number;
    total_revenue: number;
  }[];
}

export function AgentWorkloadChart({ data }: AgentWorkloadChartProps) {
  const chartData = {
    labels: data.map(agent => agent.name),
    datasets: [
      {
        label: 'Interventions',
        data: data.map(agent => agent.total_interventions),
        backgroundColor: 'rgba(13, 148, 136, 0.8)',
        borderColor: 'rgba(13, 148, 136, 1)',
        borderWidth: 1,
      },
      {
        label: 'Revenu (€)',
        data: data.map(agent => agent.total_revenue),
        backgroundColor: 'rgba(59, 130, 246, 0.8)',
        borderColor: 'rgba(59, 130, 246, 1)',
        borderWidth: 1,
        yAxisID: 'y1',
      },
    ],
  };

  const options: any = {
    ...commonOptions,
    plugins: {
      ...commonOptions.plugins,
      title: {
        display: true,
        text: 'Charge de travail par agent',
        font: { size: 14, weight: 'bold' },
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        title: {
          display: true,
          text: 'Nombre d\'interventions',
        },
      },
      y1: {
        beginAtZero: true,
        position: 'right',
        grid: {
          drawOnChartArea: false,
        },
        ticks: {
          callback: (value: any) =>
            new Intl.NumberFormat('fr-FR', {
              style: 'currency',
              currency: 'EUR',
              notation: 'compact',
            }).format(value),
        },
      },
    },
  };

  return (
    <div style={{ height: 300 }}>
      <Bar data={chartData} options={options} />
    </div>
  );
}
