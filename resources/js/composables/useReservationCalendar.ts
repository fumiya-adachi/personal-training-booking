import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { Calendar } from '@/types';
import { addDays } from './useFormat';

export function useReservationCalendar(getCalendar: () => Calendar, getTrainer: () => number | null, today: string, maxDate: string, onChange: () => void) {
    const isLoading = ref(false);
    const canPrev = computed(() => getCalendar().start > today);
    const canNext = computed(() => getCalendar().end < maxDate);
    function load(start: string, trainerId: number | null) {
        onChange();
        router.get('/reservations/create', { start, ...(trainerId === null ? {} : { trainer_id: trainerId }) }, {
            preserveState: true, preserveScroll: true, only: ['calendar', 'selectedTrainerId'],
            onStart: () => { isLoading.value = true; }, onFinish: () => { isLoading.value = false; },
        });
    }
    function shiftRange(days: number) { const start = addDays(getCalendar().start, days); load(start < today ? today : start > maxDate ? maxDate : start, getTrainer()); }
    function changeTrainer(id: number | null) { load(getCalendar().start, id); }
    function goToday() { load(today, getTrainer()); }
    return { isLoading, canPrev, canNext, shiftRange, changeTrainer, goToday };
}
