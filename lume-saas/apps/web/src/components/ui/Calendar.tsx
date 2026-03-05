'use client';

import * as React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import {
    format,
    startOfMonth,
    endOfMonth,
    eachDayOfInterval,
    startOfWeek,
    endOfWeek,
    isSameMonth,
    isSameDay,
    isToday,
    addMonths,
    subMonths
} from 'date-fns';
import { ptBR } from 'date-fns/locale';

export type CalendarEventType = 'WORK' | 'VACATION' | 'HOLIDAY';

interface CalendarProps {
    currentDate: Date;
    onDateChange: (date: Date) => void;
    events: Record<string, CalendarEventType>;
    onDayClick: (date: Date) => void;
}

export function Calendar({ currentDate, onDateChange, events, onDayClick }: CalendarProps) {
    const monthStart = startOfMonth(currentDate);
    const monthEnd = endOfMonth(monthStart);
    const startDate = startOfWeek(monthStart);
    const endDate = endOfWeek(monthEnd);

    const days = eachDayOfInterval({
        start: startDate,
        end: endDate,
    });

    const weekDays = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];

    const getDayStyle = (day: Date) => {
        const dateStr = format(day, 'yyyy-MM-dd');
        const type = events[dateStr];

        if (type === 'WORK') return 'bg-emerald-500 text-white hover:bg-emerald-600';
        if (type === 'VACATION') return 'bg-blue-500 text-white hover:bg-blue-600';
        if (type === 'HOLIDAY') return 'bg-rose-500 text-white hover:bg-rose-600';

        return 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300';
    };

    const getDayLabel = (day: Date) => {
        const dateStr = format(day, 'yyyy-MM-dd');
        const type = events[dateStr];
        if (!type) return null;
        if (type === 'WORK') return 'Trabalho';
        if (type === 'VACATION') return 'Férias';
        if (type === 'HOLIDAY') return 'Feriado';
        return null;
    };

    return (
        <div className="w-full bg-white dark:bg-gray-900 rounded-xl shadow-lg border border-gray-100 dark:border-gray-800 overflow-hidden">
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-800">
                <button
                    onClick={() => onDateChange(subMonths(currentDate, 1))}
                    className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                >
                    <ChevronLeft className="w-5 h-5 text-gray-600 dark:text-gray-400" />
                </button>

                <h2 className="text-lg font-semibold text-gray-800 dark:text-gray-100 capitalize">
                    {format(currentDate, 'MMMM yyyy', { locale: ptBR })}
                </h2>

                <button
                    onClick={() => onDateChange(addMonths(currentDate, 1))}
                    className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                >
                    <ChevronRight className="w-5 h-5 text-gray-600 dark:text-gray-400" />
                </button>
            </div>

            {/* Week days */}
            <div className="grid grid-cols-7 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/50">
                {weekDays.map((day, i) => (
                    <div key={i} className="py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400">
                        {day}
                    </div>
                ))}
            </div>

            {/* Calendar Grid */}
            <div className="grid grid-cols-7">
                {days.map((day, dayIdx) => {
                    const isCurrentMonth = isSameMonth(day, monthStart);
                    const isSelectedDay = isSameDay(day, new Date()); // Highlight current day maybe?

                    return (
                        <div
                            key={day.toString()}
                            onClick={() => onDayClick(day)}
                            className={`
                min-h-[50px] sm:min-h-[80px] p-1 sm:p-2 border-b border-r border-gray-100 dark:border-gray-800 cursor-pointer transition-colors relative group
                ${!isCurrentMonth ? 'bg-gray-50/30 dark:bg-gray-900/50' : 'bg-white dark:bg-gray-900'}
                ${dayIdx % 7 === 6 ? 'border-r-0' : ''}
              `}
                        >
                            <div className="flex flex-col items-center justify-between h-full">
                                <span
                                    className={`
                    text-xs sm:text-sm font-medium w-6 h-6 flex items-center justify-center rounded-full mb-1
                    ${isToday(day) ? 'bg-indigo-600 text-white' : isCurrentMonth ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600'}
                  `}
                                >
                                    {format(day, 'd')}
                                </span>

                                {/* Event Indicator */}
                                <div className={`
                    w-full flex-1 rounded-md flex items-center justify-center text-[10px] font-medium transition-all
                    ${getDayStyle(day)}
                    ${!events[format(day, 'yyyy-MM-dd')] ? 'opacity-0 group-hover:opacity-100 hover:!bg-gray-100 dark:hover:!bg-gray-800' : 'opacity-100'}
                `}>
                                    <span className="truncate px-1 hidden sm:block">
                                        {getDayLabel(day) || 'Selecionar'}
                                    </span>
                                    <span className="sm:hidden block w-2 h-2 rounded-full bg-current"></span>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Legend */}
            <div className="flex flex-wrap gap-4 p-4 text-xs text-gray-600 dark:text-gray-400 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                <div className="flex items-center gap-2">
                    <span className="w-3 h-3 rounded-full bg-emerald-500"></span>
                    <span>Trabalho</span>
                </div>
                <div className="flex items-center gap-2">
                    <span className="w-3 h-3 rounded-full bg-blue-500"></span>
                    <span>Férias</span>
                </div>
                <div className="flex items-center gap-2">
                    <span className="w-3 h-3 rounded-full bg-rose-500"></span>
                    <span>Feriado</span>
                </div>
            </div>
        </div>
    );
}
