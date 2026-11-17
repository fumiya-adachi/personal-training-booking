<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, Plus, Trash2, Save, LoaderCircle, CalendarX2, Check, X } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Trainer, TrainerScheduleView } from '@/types';
import { formatDate, weekdays } from '@/composables/useFormat';

const props = defineProps<{ closedWeekdays: number[]; schedules: TrainerScheduleView[]; trainers: Trainer[]; closedDates: { id: number; date: string; reason: string }[] }>();
const selectedTrainer = ref<number>(props.trainers[0]?.id ?? 0);
const selectedTrainerName = computed(() => props.trainers.find(t => t.id === Number(selectedTrainer.value))?.name ?? '');
const weeklyOrder = [1, 2, 3, 4, 5, 6, 0];
function createWeek() {
    return weeklyOrder.map(day => {
        const saved = props.schedules.find(s => s.trainer_id === Number(selectedTrainer.value) && s.day_of_week === day);
        return { day_of_week: day, start_time: saved?.start_time?.slice(0, 5) ?? '09:00', end_time: saved?.end_time?.slice(0, 5) ?? '19:00', is_working: saved?.is_working ?? day !== 1 };
    });
}
const scheduleForm = useForm({ schedules: createWeek() });
const weekdaysForm = useForm({ closed_weekdays: [...props.closedWeekdays] });
watch(() => props.closedWeekdays, days => { weekdaysForm.closed_weekdays = [...days]; });
function saveClosedWeekdays() { weekdaysForm.put('/admin/closed-weekdays', { preserveScroll: true }); }
const closedForm = useForm({ date: '', reason: '' });
const deleting = ref<number | null>(null);
const confirmDelete = ref<number | null>(null);
watch(selectedTrainer, () => { scheduleForm.schedules = createWeek(); scheduleForm.clearErrors(); });
watch(() => props.schedules, () => { scheduleForm.schedules = createWeek(); });
function scheduleError(key: string): string | undefined { return (scheduleForm.errors as Record<string, string>)[key]; }
function saveSchedules() { if (selectedTrainer.value) scheduleForm.put(`/admin/schedules/${selectedTrainer.value}`, { preserveScroll: true }); }
function addClosedDate() { closedForm.post('/admin/closed-dates', { preserveScroll: true, onSuccess: () => closedForm.reset() }); }
function deleteClosedDate(id: number) {
    deleting.value = id;
    router.delete(`/admin/closed-dates/${id}`, { preserveScroll: true, onFinish: () => { deleting.value = null; confirmDelete.value = null; } });
}
</script>

<template>
    <Head title="スケジュール管理" />
    <AdminLayout>
        <div class="mb-8"><p class="page-kicker">SCHEDULES</p><h1 class="page-title">スケジュール管理</h1><p class="page-description">トレーナーの勤務時間と、スタジオの休業日を設定します。</p></div>
        <section class="card mb-6 p-6" aria-labelledby="closed-weekdays-title">
            <h2 id="closed-weekdays-title" class="section-title">毎週の定休日</h2>
            <p class="mt-3 text-sm leading-7 text-stone-500">休業する曜日にチェックを入れて保存してください。すべて外すと定休日なしになります。営業日に変更した曜日は、トレーナーの勤務スケジュールも設定してください。</p>
            <form class="mt-5 space-y-4" @submit.prevent="saveClosedWeekdays">
                <div class="flex flex-wrap gap-4">
                    <label v-for="day in weeklyOrder" :key="day" class="inline-flex items-center gap-2 text-sm">
                        <input v-model="weekdaysForm.closed_weekdays" type="checkbox" :value="day" :disabled="weekdaysForm.processing" class="h-4 w-4 accent-[#c76655]" />
                        {{ weekdays[day] }}曜日
                    </label>
                </div>
                <p v-for="(error, key) in weekdaysForm.errors" :key="key" class="input-error" role="alert">{{ error }}</p>
                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="btn btn-primary" :disabled="weekdaysForm.processing"><Save :size="16" />{{ weekdaysForm.processing ? '保存中…' : '定休日を保存' }}</button>
                    <p v-if="weekdaysForm.recentlySuccessful" class="text-sm text-stone-500" aria-live="polite">定休日を保存しました。</p>
                </div>
            </form>
        </section>
        <div class="grid items-start gap-6 xl:grid-cols-[1.35fr_1fr]">
            <section class="card overflow-hidden" aria-labelledby="weekly-schedule-title">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-100 p-6"><div class="flex items-center gap-3"><CalendarClock :size="20" class="text-[#c76655]" /><h2 id="weekly-schedule-title" class="section-title">週間スケジュール</h2></div><div v-if="trainers.length"><label for="schedule-trainer" class="sr-only">トレーナーを選択</label><select id="schedule-trainer" v-model="selectedTrainer" class="field min-h-10 min-w-36 text-sm" :disabled="scheduleForm.processing"><option v-for="trainer in trainers" :key="trainer.id" :value="trainer.id">{{ trainer.name }}</option></select></div></div>
                <div v-if="!trainers.length" class="empty-state px-6 py-16"><CalendarClock :size="36" class="mx-auto mb-4 text-stone-300" /><p class="font-medium">トレーナーが登録されていません</p><p class="mt-2 text-sm text-stone-500">トレーナー管理から登録してください。</p></div>
                <form v-else @submit.prevent="saveSchedules">
                    <div class="bg-[#faf8f4] px-6 py-4 text-xs leading-6 text-stone-500">{{ selectedTrainerName }}の毎週の勤務時間です。スタジオ営業時間外・休業日の予約は受け付けません。</div>
                    <p v-if="scheduleForm.errors.schedules" class="input-error px-6 pt-4" role="alert">{{ scheduleForm.errors.schedules }}</p>
                    <div class="overflow-x-auto"><table class="w-full min-w-[440px] text-sm"><thead class="text-left text-xs text-stone-500"><tr><th class="px-6 pb-3 pt-5 font-medium">曜日</th><th class="px-3 pb-3 pt-5 font-medium">勤務</th><th class="px-3 pb-3 pt-5 font-medium">開始</th><th class="pb-3 pt-5 pr-6 font-medium">終了</th></tr></thead><tbody class="divide-y divide-stone-100"><tr v-for="(schedule, index) in scheduleForm.schedules" :key="schedule.day_of_week"><th scope="row" class="px-6 py-4 text-left font-medium" :class="schedule.day_of_week === 0 ? 'text-[#c76655]' : schedule.day_of_week === 6 ? 'text-sky-700' : ''">{{ weekdays[schedule.day_of_week] }}曜日</th><td class="px-3 py-4"><label class="inline-flex cursor-pointer items-center gap-2 whitespace-nowrap"><input v-model="schedule.is_working" type="checkbox" class="h-4 w-4 accent-[#c76655]" :aria-label="`${weekdays[schedule.day_of_week]}曜日に勤務する`" /><span class="text-xs text-stone-500">{{ schedule.is_working ? '勤務' : '休み' }}</span></label><p v-if="scheduleError(`schedules.${index}.is_working`)" class="input-error">{{ scheduleError(`schedules.${index}.is_working`) }}</p></td><td class="px-3 py-4"><input v-model="schedule.start_time" type="time" step="1800" class="field min-h-10 w-28 text-sm disabled:opacity-40" :disabled="!schedule.is_working" :aria-label="`${weekdays[schedule.day_of_week]}曜日の開始時刻`" :aria-invalid="!!scheduleError(`schedules.${index}.start_time`)" required /><p v-if="scheduleError(`schedules.${index}.start_time`)" class="input-error">{{ scheduleError(`schedules.${index}.start_time`) }}</p></td><td class="py-4 pr-6"><input v-model="schedule.end_time" type="time" step="1800" class="field min-h-10 w-28 text-sm disabled:opacity-40" :disabled="!schedule.is_working" :aria-label="`${weekdays[schedule.day_of_week]}曜日の終了時刻`" :aria-invalid="!!scheduleError(`schedules.${index}.end_time`)" required /><p v-if="scheduleError(`schedules.${index}.end_time`)" class="input-error">{{ scheduleError(`schedules.${index}.end_time`) }}</p></td></tr></tbody></table></div>
                    <div class="flex items-center justify-between gap-3 border-t border-stone-100 px-6 py-5"><p class="text-xs text-stone-500" aria-live="polite">{{ scheduleForm.recentlySuccessful ? 'スケジュールを保存しました。' : '設定は保存後に反映されます。' }}</p><button type="submit" class="btn btn-primary" :disabled="scheduleForm.processing"><LoaderCircle v-if="scheduleForm.processing" :size="16" class="animate-spin" /><Save v-else :size="16" />{{ scheduleForm.processing ? '保存中…' : '保存する' }}</button></div>
                </form>
            </section>
            <section class="card overflow-hidden" aria-labelledby="closed-dates-title">
                <div class="flex items-center gap-3 border-b border-stone-100 p-6"><CalendarX2 :size="20" class="text-[#c76655]" /><h2 id="closed-dates-title" class="section-title">スタジオの休業日</h2></div>
                <div class="p-6"><p class="mb-5 text-sm leading-7 text-stone-500">毎週の定休日に加えて、臨時休業や年末年始のお休みを追加できます。</p><form class="space-y-4 rounded-2xl bg-[#faf8f4] p-4" @submit.prevent="addClosedDate"><div><label for="closed-date" class="field-label">休業日</label><input id="closed-date" v-model="closedForm.date" type="date" class="field w-full" required :aria-invalid="!!closedForm.errors.date" /><p v-if="closedForm.errors.date" class="input-error">{{ closedForm.errors.date }}</p></div><div><label for="closed-reason" class="field-label">理由</label><input id="closed-reason" v-model="closedForm.reason" type="text" class="field w-full" maxlength="255" placeholder="例：年末年始のお休み" required :aria-invalid="!!closedForm.errors.reason" /><p v-if="closedForm.errors.reason" class="input-error">{{ closedForm.errors.reason }}</p></div><button type="submit" class="btn btn-secondary w-full" :disabled="closedForm.processing"><LoaderCircle v-if="closedForm.processing" :size="16" class="animate-spin" /><Plus v-else :size="16" />{{ closedForm.processing ? '追加中…' : '休業日を追加' }}</button></form>
                    <div class="mt-7"><h3 class="mb-3 text-xs font-medium tracking-wide text-stone-500">登録済みの休業日</h3><p v-if="!closedDates.length" class="py-6 text-center text-sm text-stone-500">臨時休業日は登録されていません。</p><ul v-else class="divide-y divide-stone-100"><li v-for="closedDate in closedDates" :key="closedDate.id" class="flex items-center justify-between gap-3 py-4"><div class="min-w-0"><p class="text-sm font-medium">{{ formatDate(closedDate.date) }}</p><p class="mt-1 break-words text-xs text-stone-500">{{ closedDate.reason }}</p></div><div v-if="confirmDelete === closedDate.id" class="flex shrink-0 items-center gap-1"><span class="sr-only">休業日を削除しますか？</span><button type="button" class="rounded-lg bg-red-50 p-2 text-red-700 hover:bg-red-100" :disabled="deleting === closedDate.id" :aria-label="`${formatDate(closedDate.date)}の休業日を削除する`" @click="deleteClosedDate(closedDate.id)"><LoaderCircle v-if="deleting === closedDate.id" :size="16" class="animate-spin" /><Check v-else :size="16" /></button><button type="button" class="rounded-lg p-2 text-stone-500 hover:bg-stone-100" aria-label="削除を取り消す" :disabled="deleting === closedDate.id" @click="confirmDelete = null"><X :size="16" /></button></div><button v-else type="button" class="shrink-0 rounded-lg p-2 text-stone-500 transition-colors hover:bg-red-50 hover:text-red-600" :aria-label="`${formatDate(closedDate.date)}の休業日を削除`" @click="confirmDelete = closedDate.id"><Trash2 :size="16" /></button></li></ul></div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
