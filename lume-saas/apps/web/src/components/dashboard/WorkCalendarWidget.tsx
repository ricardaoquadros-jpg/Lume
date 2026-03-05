'use client';

import * as React from 'react';
import { Calendar, CalendarEventType } from '@/components/ui/Calendar';
import { format, startOfMonth, endOfMonth, addDays } from 'date-fns';

export function WorkCalendarWidget() {
    const [currentDate, setCurrentDate] = React.useState(new Date());
    const [events, setEvents] = React.useState<Record<string, CalendarEventType>>({});
    const [loading, setLoading] = React.useState(false);

    const fetchEvents = React.useCallback(async () => {
        setLoading(true);
        try {
            const start = format(startOfMonth(currentDate), 'yyyy-MM-dd');
            const end = format(endOfMonth(currentDate), 'yyyy-MM-dd'); // Maybe fetch a bit more for buffer

            const res = await fetch(`/api/calendar?startDate=${start}&endDate=${end}`);
            if (!res.ok) throw new Error('Failed to fetch');

            const data = await res.json();

            const newEvents: Record<string, CalendarEventType> = {};
            data.forEach((event: any) => {
                // Assume API returns ISO strings for date
                const dateStr = format(new Date(event.date), 'yyyy-MM-dd');
                newEvents[dateStr] = event.type;
            });

            setEvents(newEvents);
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    }, [currentDate]);

    React.useEffect(() => {
        fetchEvents();
    }, [fetchEvents]);

    const handleDayClick = async (date: Date) => {
        const dateStr = format(date, 'yyyy-MM-dd');
        const currentType = events[dateStr];

        let nextType: CalendarEventType | null = null;

        // Cycle: None -> WORK -> VACATION -> HOLIDAY -> None
        if (!currentType) nextType = 'WORK';
        else if (currentType === 'WORK') nextType = 'VACATION';
        else if (currentType === 'VACATION') nextType = 'HOLIDAY';
        else if (currentType === 'HOLIDAY') nextType = null;

        // Optimistic Update
        const prevEvents = { ...events };
        setEvents(prev => {
            const next = { ...prev };
            if (nextType) {
                next[dateStr] = nextType;
            } else {
                delete next[dateStr];
            }
            return next;
        });

        try {
            const res = await fetch('/api/calendar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    date: dateStr, // date must be string for API
                    type: nextType
                }),
            });

            if (!res.ok) {
                // Revert on failure
                setEvents(prevEvents);
            }
        } catch (error) {
            console.error(error);
            setEvents(prevEvents);
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 dark:from-white dark:to-gray-300 bg-clip-text text-transparent">
                        Calendário de Trabalho
                    </h2>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Gerencie seus dias de trabalho, férias e feriados
                    </p>
                </div>
            </div>

            <Calendar
                currentDate={currentDate}
                onDateChange={setCurrentDate}
                events={events}
                onDayClick={handleDayClick}
            />
        </div>
    );
}
