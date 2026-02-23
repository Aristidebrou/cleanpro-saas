import { useState, useCallback } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import frLocale from '@fullcalendar/core/locales/fr';
import { interventionService } from '@/services/api';
import { useAuth } from '@/context/AuthContext';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Calendar, Clock, MapPin, User, AlertTriangle } from 'lucide-react';
import type { Intervention } from '@/types';

interface PlanningCalendarProps {
  agentId?: number;
  readOnly?: boolean;
  onEventClick?: (intervention: Intervention) => void;
  onDateSelect?: (date: Date) => void;
}

const statusColors = {
  scheduled: '#3b82f6',    // blue
  in_progress: '#f59e0b',  // orange
  completed: '#10b981',    // green
  validated: '#059669',    // dark green
  cancelled: '#ef4444',    // red
};

const statusLabels = {
  scheduled: 'Planifiée',
  in_progress: 'En cours',
  completed: 'Terminée',
  validated: 'Validée',
  cancelled: 'Annulée',
};

export function PlanningCalendar({
  agentId,
  readOnly = false,
  onEventClick,
  onDateSelect,
}: PlanningCalendarProps) {
  const { user } = useAuth();
  const [events, setEvents] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [selectedIntervention, setSelectedIntervention] = useState<Intervention | null>(null);
  const [isDialogOpen, setIsDialogOpen] = useState(false);

  const fetchEvents = useCallback(async (start: Date, end: Date) => {
    try {
      setLoading(true);
      const response = await interventionService.getSchedule({
        agent_id: agentId || user?.id,
        date_from: start.toISOString().split('T')[0],
        date_to: end.toISOString().split('T')[0],
      });

      const interventions = response.data.data.schedule;
      
      const calendarEvents = interventions.map((intervention: Intervention) => ({
        id: intervention.id.toString(),
        title: intervention.client_company || 'Client',
        start: `${intervention.scheduled_date}T${intervention.scheduled_time}`,
        end: intervention.estimated_end_time
          ? `${intervention.scheduled_date}T${intervention.estimated_end_time}`
          : undefined,
        backgroundColor: statusColors[intervention.status],
        borderColor: statusColors[intervention.status],
        extendedProps: { intervention },
      }));

      setEvents(calendarEvents);
    } catch (error) {
      console.error('Error fetching schedule:', error);
    } finally {
      setLoading(false);
    }
  }, [agentId, user?.id]);

  const handleEventClick = (info: any) => {
    const intervention = info.event.extendedProps.intervention;
    
    if (onEventClick) {
      onEventClick(intervention);
    } else {
      setSelectedIntervention(intervention);
      setIsDialogOpen(true);
    }
  };

  const handleDateSelect = (info: any) => {
    if (onDateSelect && !readOnly) {
      onDateSelect(info.start);
    }
  };

  const handleDatesSet = (info: any) => {
    fetchEvents(info.start, info.end);
  };

  return (
    <div className="h-full">
      <FullCalendar
        plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
        initialView="timeGridWeek"
        locale={frLocale}
        headerToolbar={{
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay',
        }}
        buttonText={{
          today: 'Aujourd\'hui',
          month: 'Mois',
          week: 'Semaine',
          day: 'Jour',
        }}
        slotMinTime="06:00:00"
        slotMaxTime="22:00:00"
        allDaySlot={false}
        events={events}
        eventClick={handleEventClick}
        select={handleDateSelect}
        datesSet={handleDatesSet}
        selectable={!readOnly}
        selectMirror={!readOnly}
        editable={!readOnly}
        eventDurationEditable={!readOnly}
        eventStartEditable={!readOnly}
        height="100%"
      />

      {/* Dialog de détails */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Calendar className="w-5 h-5 text-teal-500" />
              Détails de l'intervention
            </DialogTitle>
          </DialogHeader>
          
          {selectedIntervention && (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-500">Référence</span>
                <span className="font-mono font-medium">{selectedIntervention.reference}</span>
              </div>

              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-500">Statut</span>
                <Badge
                  style={{
                    backgroundColor: statusColors[selectedIntervention.status],
                  }}
                >
                  {statusLabels[selectedIntervention.status]}
                </Badge>
              </div>

              <div className="flex items-center gap-2">
                <User className="w-4 h-4 text-gray-400" />
                <span className="text-sm text-gray-500">Client:</span>
                <span className="font-medium">{selectedIntervention.client_company}</span>
              </div>

              <div className="flex items-center gap-2">
                <MapPin className="w-4 h-4 text-gray-400" />
                <span className="text-sm text-gray-500">Adresse:</span>
                <span className="font-medium">
                  {selectedIntervention.client_address}, {selectedIntervention.client_postal_code} {selectedIntervention.client_city}
                </span>
              </div>

              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-gray-400" />
                <span className="text-sm text-gray-500">Horaire:</span>
                <span className="font-medium">
                  {selectedIntervention.scheduled_date} à {selectedIntervention.scheduled_time.substring(0, 5)}
                </span>
              </div>

              {selectedIntervention.agent_name && (
                <div className="flex items-center gap-2">
                  <User className="w-4 h-4 text-gray-400" />
                  <span className="text-sm text-gray-500">Agent:</span>
                  <span className="font-medium">{selectedIntervention.agent_name}</span>
                </div>
              )}

              {selectedIntervention.notes && (
                <div className="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">
                  <span className="text-sm text-gray-500">Notes:</span>
                  <p className="text-sm mt-1">{selectedIntervention.notes}</p>
                </div>
              )}

              <div className="flex gap-2 pt-4">
                <Button
                  variant="outline"
                  onClick={() => setIsDialogOpen(false)}
                  className="flex-1"
                >
                  Fermer
                </Button>
                <Button
                  onClick={() => {
                    setIsDialogOpen(false);
                    // Navigation vers la page de détails
                  }}
                  className="flex-1 bg-teal-500 hover:bg-teal-600"
                >
                  Voir détails
                </Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Indicateur de chargement */}
      {loading && (
        <div className="absolute inset-0 bg-white/50 dark:bg-gray-900/50 flex items-center justify-center z-10">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500" />
        </div>
      )}
    </div>
  );
}

// Composant pour afficher les conflits
export function ConflictWarning({ conflicts }: { conflicts: any[] }) {
  if (conflicts.length === 0) return null;

  return (
    <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
      <div className="flex items-start gap-3">
        <AlertTriangle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
        <div>
          <h4 className="font-medium text-red-800 dark:text-red-200">
            Conflit de planning détecté
          </h4>
          <p className="text-sm text-red-600 dark:text-red-300 mt-1">
            Cet agent est déjà assigné à une autre intervention sur ce créneau:
          </p>
          <ul className="mt-2 space-y-1">
            {conflicts.map((conflict) => (
              <li key={conflict.id} className="text-sm text-red-600 dark:text-red-300">
                • {conflict.client_name} - {conflict.scheduled_time.substring(0, 5)}
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  );
}
