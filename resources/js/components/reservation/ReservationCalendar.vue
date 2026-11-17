<script setup lang="ts">
import { Coffee } from 'lucide-vue-next';
import TimeSlotCell from './TimeSlotCell.vue';
import AvailabilityLegend from './AvailabilityLegend.vue';
import type { Calendar } from '@/types';
import { weekdays } from '@/composables/useFormat';
defineProps<{ calendar: Calendar; selected: { date: string; time: string } | null; loading: boolean }>();
const emit = defineEmits<{ select: [date: string, time: string] }>();
</script>
<template>
    <div class="calendar-wrap" :class="{ 'calendar-loading': loading }" :aria-busy="loading">
        <div class="calendar-scroll" tabindex="0" role="region" aria-label="予約カレンダー。横にスクロールできます">
            <table class="reservation-calendar">
                <caption class="sr-only">{{ calendar.start }}から{{ calendar.end }}までの予約可能な開始時間</caption>
                <thead><tr><th class="time-heading" scope="col"><span class="text-[11px] tracking-widest">TIME</span></th><th v-for="(day, i) in calendar.days" :key="day.date" scope="col" :class="{ saturday: day.weekday === 6, sunday: day.weekday === 0, 'closed-heading': day.closed }"><span class="month-label">{{ i === 0 || day.day === 1 ? `${Number(day.date.slice(5, 7))}月` : '' }}</span><span class="day-number">{{ day.day }}</span><span class="day-weekday">{{ weekdays[day.weekday] }}</span></th></tr></thead>
                <tbody><tr v-for="(time, timeIndex) in calendar.times" :key="time"><th scope="row" class="time-label" :class="{ 'half-hour': time.endsWith(':30') }">{{ time }}</th><template v-for="day in calendar.days" :key="day.date"><td v-if="day.closed && timeIndex === 0" :rowspan="calendar.times.length" class="closed-day"><div class="closed-content"><Coffee :size="16" :stroke-width="1.3" /><span>休業日</span></div></td><td v-else-if="!day.closed" :class="{ 'hour-border': time.endsWith(':00') }"><TimeSlotCell :slot="calendar.slots[day.date]?.[time]" :date="day.date" :time="time" :selected="selected?.date === day.date && selected.time === time" :loading="loading" @select="emit('select', day.date, time)" /></td></template></tr></tbody>
            </table>
        </div>
        <AvailabilityLegend />
    </div>
</template>
<style scoped>
.calendar-wrap { transition: opacity .15s; }
.calendar-loading { opacity: .45; pointer-events: none; }
.calendar-scroll { width: 100%; overflow-x: auto; scrollbar-width: thin; scrollbar-color: #d5dcca #f7f8f3; }
.reservation-calendar { width: 100%; min-width: 670px; border-collapse: separate; border-spacing: 0; table-layout: fixed; text-align: center; }
.reservation-calendar thead th { background: #fbfcf8; border-top: 1px solid #e8ebe2; border-bottom: 1px solid #e0e5d6; padding: 6px 0 11px; color: #58624d; font-weight: 500; }
.reservation-calendar thead th.time-heading { width: 63px; color: #6f7b63; }
.month-label { display: block; height: 15px; font-size: 11px; color: #6f7b63; }
.day-number { display: block; font-size: 16px; line-height: 1.3; font-weight: 650; letter-spacing: .01em; }
.day-weekday { display: block; font-size: 11px; margin-top: 4px; }
.reservation-calendar thead th.saturday { color: #536f96; }
.reservation-calendar thead th.sunday { color: #a85b4e; }
.reservation-calendar thead th.closed-heading { color: #78836b; background: #f2f4ec; }
.reservation-calendar tbody th, .reservation-calendar tbody td { border-right: 1px solid #eef0e8; border-bottom: 1px solid #edf0e7; }
.reservation-calendar tbody td:last-child { border-right: 0; }
.time-label { font-size: 11px; font-weight: 500; color: #6d7b61; background: #fbfcf8; font-variant-numeric: tabular-nums; }
.time-label.half-hour { font-size: 11px; color: #6f7b63; }
.time-label, .time-heading { position: sticky; left: 0; z-index: 1; }
.reservation-calendar tbody td { height: 37px; padding: 0; }
.reservation-calendar tbody td.closed-day { background: #f2f4ed; }
.closed-content { display: flex; flex-direction: column; gap: 13px; align-items: center; justify-content: center; color: #78836b; font-size: 11px; }
.closed-content span { writing-mode: vertical-rl; letter-spacing: .35em; }
</style>
