<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays, Check, ChevronLeft, ChevronRight, Clock3, Dumbbell, Leaf, MapPin, ShieldCheck, Sparkles, UserRound, Info, Mail, LoaderCircle } from 'lucide-vue-next';
import ClientLayout from '@/layouts/ClientLayout.vue';
import TrainerSelector from '@/components/reservation/TrainerSelector.vue';
import ReservationCalendar from '@/components/reservation/ReservationCalendar.vue';
import Modal from '@/components/ui/Modal.vue';
import { useReservationCalendar } from '@/composables/useReservationCalendar';
import { formatDate, formatMoney } from '@/composables/useFormat';
import { validateReservation } from '@/validation/reservation';
import type { Calendar, Trainer, SharedProps } from '@/types';

const props = defineProps<{ trainers: Trainer[]; calendar: Calendar; selectedTrainerId: number | null; session: { name: string; duration: number; price: number }; today: string; maxDate: string }>();
const page = usePage<SharedProps>();
const selected = ref<{ date: string; time: string } | null>(null);
const confirmOpen = ref(false);
const localError = ref<string | null>(null);
const form = useForm<{ date: string; time: string; trainer_id: number | null; notes: string }>({ date: '', time: '', trainer_id: null, notes: '' });
const user = computed(() => page.props.auth?.user);
const trainer = computed(() => props.trainers.find(t => t.id === props.selectedTrainerId));
const trainerName = computed(() => trainer.value?.name ?? '指名なし（おまかせ）');
const draftKey = 'form.reservation-draft';
let restoringDraft = false;
function saveDraft() {
    if (!selected.value || restoringDraft) return;
    try { sessionStorage.setItem(draftKey, JSON.stringify({ ...selected.value, notes: form.notes, trainer_id: props.selectedTrainerId, start: props.calendar.start, saved_at: Date.now() })); } catch { /* Storage can be disabled by the browser. */ }
}
function clearDraft() { try { sessionStorage.removeItem(draftKey); } catch { /* Optional browser storage. */ } }
watch([selected, () => form.notes], saveDraft);
onMounted(() => {
    try {
        const raw = sessionStorage.getItem(draftKey);
        if (!raw) return;
        const draft = JSON.parse(raw);
        if (!draft || typeof draft.date !== 'string' || typeof draft.time !== 'string' || Date.now() - draft.saved_at > 7_200_000 || draft.date < props.today || draft.date > props.maxDate || (draft.trainer_id !== null && !props.trainers.some(t => t.id === draft.trainer_id))) { clearDraft(); return; }
        restoringDraft = true;
        form.notes = typeof draft.notes === 'string' ? draft.notes.slice(0, 1000) : '';
        const restore = () => {
            if (props.calendar.slots[draft.date]?.[draft.time]?.status === 'available') selected.value = { date: draft.date, time: draft.time };
            else { localError.value = '前回選択した日時は現在予約できません。別の日時をお選びください。'; clearDraft(); }
            restoringDraft = false;
        };
        if (props.selectedTrainerId !== draft.trainer_id || !props.calendar.days.some(d => d.date === draft.date)) {
            router.get('/reservations/create', { start: draft.start || draft.date, ...(draft.trainer_id === null ? {} : { trainer_id: draft.trainer_id }) }, { preserveState: true, preserveScroll: true, only: ['calendar', 'selectedTrainerId'], onSuccess: restore, onError: () => { restoringDraft = false; } });
        } else restore();
    } catch { restoringDraft = false; clearDraft(); }
});
const rangeLabel = computed(() => {
    const start = props.calendar.start; const end = props.calendar.end;
    return `${start.slice(0, 4)}年 ${Number(start.slice(5, 7))}月${Number(start.slice(8))}日 — ${start.slice(5, 7) !== end.slice(5, 7) ? Number(end.slice(5, 7)) + '月' : ''}${Number(end.slice(8))}日`;
});
const endTime = computed(() => {
    if (!selected.value) return '';
    const [h, m] = selected.value.time.split(':').map(Number); const end = h * 60 + m + props.session.duration;
    return `${String(Math.floor(end / 60)).padStart(2, '0')}:${String(end % 60).padStart(2, '0')}`;
});
const { isLoading, canPrev, canNext, shiftRange, changeTrainer, goToday } = useReservationCalendar(() => props.calendar, () => props.selectedTrainerId, props.today, props.maxDate, () => { selected.value = null; clearDraft(); form.clearErrors(); localError.value = null; });
function selectSlot(date: string, time: string) { selected.value = { date, time }; localError.value = null; form.clearErrors(); }
function review() {
    localError.value = validateReservation(selected.value?.date ?? '', selected.value?.time ?? '', form.notes);
    if (localError.value) return;
    if (selected.value) { form.date = selected.value.date; form.time = selected.value.time; form.trainer_id = props.selectedTrainerId; }
    confirmOpen.value = true;
}
function submit() {
    form.post('/reservations', { preserveScroll: true, onSuccess: () => { clearDraft(); confirmOpen.value = false; }, onError: () => { confirmOpen.value = false; } });
}
</script>
<template>
    <Head title="トレーニングを予約" />
    <ClientLayout>
        <div class="flex items-end justify-between gap-5">
            <div><p class="page-kicker">RESERVATION <span class="mx-2 text-[#6f7b63]">/</span> ご予約</p><h1 class="page-title">トレーニングを予約</h1><p class="page-description">自分のための、いい時間を。<span class="hidden sm:inline">あなたに合ったトレーニングを始めましょう。</span></p></div>
            <div class="hidden items-center gap-2 rounded-full border border-[#dce3d2] bg-[#f0f3e9] px-3 py-2 text-[11px] text-[#6f7b63] md:flex"><Leaf :size="13" :stroke-width="1.5" />すべてのレベルの方を歓迎します</div>
        </div>
        <ol class="booking-steps" aria-label="予約の手順"><li class="booking-step active" aria-current="step"><span class="step-dot">01</span>日時を選択</li><li class="step-line" aria-hidden="true"></li><li class="booking-step"><span class="step-dot">02</span>内容を確認</li><li class="step-line" aria-hidden="true"></li><li class="booking-step"><span class="step-dot">03</span>予約完了</li></ol>
        <div class="booking-grid">
            <div class="min-w-0 space-y-5">
                <section class="card px-6 py-6 max-sm:px-4" aria-labelledby="trainer-title">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-2"><h2 id="trainer-title" class="section-title"><span class="section-number">01</span>トレーナーを選択</h2><span class="text-[11px] text-[#6f7b63]">指名料はかかりません</span></div>
                    <TrainerSelector :trainers="trainers" :selected-id="selectedTrainerId" :disabled="isLoading" @change="changeTrainer" />
                    <p v-if="trainer" class="mt-4 flex items-start gap-2 text-[11px] leading-6 text-[#6f7b63]"><Sparkles :size="13" class="mt-1 shrink-0" />{{ trainer.bio }}</p>
                    <p v-else class="mt-4 flex items-center gap-2 text-[11px] text-[#6f7b63]"><Info :size="12" />指名なしの場合は、空いているトレーナーが担当します。</p>
                </section>
                <section class="card overflow-hidden" aria-labelledby="calendar-title">
                    <div class="calendar-card-heading"><div><h2 id="calendar-title" class="section-title"><span class="section-number">02</span>日時を選択</h2><p class="mt-2 ml-9 text-[11px] text-[#6f7b63]">○からご希望の開始時間をお選びください。</p></div><span class="rounded-md bg-[#f4f6ee] px-2.5 py-1.5 text-[11px] tracking-wide text-[#6f7b63]">{{ session.duration }}分 / 1セッション</span></div>
                    <div class="calendar-toolbar"><div class="flex items-center gap-2"><CalendarDays :size="15" class="text-[#6f7b63]" /><h3 class="text-[12px] font-semibold tracking-wide">{{ rangeLabel }}</h3></div><div class="flex items-center gap-2"><button type="button" class="today-button" :disabled="isLoading" @click="goToday">今日</button><button type="button" class="calendar-arrow" aria-label="前の2週間" :disabled="!canPrev || isLoading" @click="shiftRange(-14)"><ChevronLeft :size="15" /></button><button type="button" class="calendar-arrow" aria-label="次の2週間" :disabled="!canNext || isLoading" @click="shiftRange(14)"><ChevronRight :size="15" /></button></div></div>
                    <ReservationCalendar :calendar="calendar" :selected="selected" :loading="isLoading" @select="selectSlot" />
                </section>
                <p class="flex items-start gap-2 px-1 text-[11px] leading-5 text-[#6f7b63]"><Info :size="13" class="mt-0.5 shrink-0" />{{ formatDate(maxDate) }}までご予約いただけます。空き状況は予約確定時に再確認されます。</p>
            </div>
            <aside class="booking-sidebar" aria-label="選択中の予約内容">
                <section class="card overflow-hidden">
                    <div class="border-b border-[#edf0e6] px-6 py-5"><p class="text-[11px] font-semibold tracking-[.18em] text-[#6f7b63]">YOUR SESSION</p><h2 class="mt-2 text-[16px] font-semibold">ご予約内容</h2></div>
                    <div class="px-6 pt-5 pb-6">
                        <div class="mb-5 flex gap-3"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#f1f3eb] text-[#6f7b63]"><Dumbbell :size="23" :stroke-width="1.4" /></span><div><h3 class="text-[13px] font-semibold">{{ session.name }}</h3><p class="mt-1 text-[11px] text-[#6f7b63]">マンツーマン・トレーニング</p></div></div>
                        <dl class="session-details">
                            <div><dt><UserRound :size="13" />トレーナー</dt><dd>{{ trainerName }}</dd></div>
                            <div><dt><CalendarDays :size="13" />日付</dt><dd :class="{ placeholder: !selected }">{{ selected ? formatDate(selected.date) : '日時を選択してください' }}</dd></div>
                            <div><dt><Clock3 :size="13" />時間</dt><dd :class="{ placeholder: !selected }">{{ selected ? `${selected.time} – ${endTime}` : '—' }}<span v-if="selected" class="ml-1 text-[11px] font-normal text-[#6f7b63]">({{ session.duration }}分)</span></dd></div>
                            <div><dt><MapPin :size="13" />場所</dt><dd>表参道スタジオ</dd></div>
                        </dl>
                        <div class="my-5 border-t border-dashed border-[#e1e6d7]"></div>
                        <label for="notes" class="field-label flex items-center justify-between">ご要望・連絡事項<span class="text-[11px] font-normal text-[#6f7b63]">任意</span></label><textarea id="notes" v-model="form.notes" class="field resize-y text-[11px]!" rows="3" maxlength="1000" placeholder="気になることや、目標など" :aria-invalid="!!form.errors.notes" aria-describedby="notes-error"></textarea><p v-if="form.errors.notes" id="notes-error" class="input-error">{{ form.errors.notes }}</p>
                        <div class="mt-5 flex items-end justify-between"><span class="text-[11px] font-medium text-[#6f7b63]">合計<span class="ml-1 text-[11px] font-normal">（税込）</span></span><span class="text-[25px] font-semibold tracking-tight">{{ formatMoney(session.price) }}</span></div>
                        <div v-if="localError || form.errors.date || form.errors.time || form.errors.trainer_id" class="input-error mt-4!" role="alert">{{ localError || form.errors.date || form.errors.time || form.errors.trainer_id }}</div>
                        <button type="button" class="btn btn-primary mt-5 w-full justify-between! px-4!" :disabled="!selected || isLoading || form.processing" @click="review">予約内容を確認<ArrowRight :size="16" /></button>
                        <p class="mt-3 text-center text-[11px] leading-5 text-[#6f7b63]">この時点では予約は確定しません</p>
                    </div>
                </section>
                <div class="mt-5 flex items-start gap-3 rounded-xl border border-[#e7e9de] bg-[#eeefe7] px-5 py-5"><ShieldCheck :size="22" :stroke-width="1.3" class="mt-0.5 shrink-0 text-[#6f7b63]" /><div><h3 class="text-[11px] font-semibold text-[#6a7959]">はじめての方も、お気軽に。</h3><p class="mt-2 text-[11px] leading-6 text-[#6f7b63]">一人ひとりの目標や体力に合わせて、トレーナーが丁寧にサポートします。</p></div></div>
                <p class="mt-5 px-2 text-[11px] leading-6 text-[#6f7b63]">動きやすい服装とお飲み物をご用意のうえ、開始5分前を目安にお越しください。</p>
            </aside>
        </div>
        <Modal v-model="confirmOpen" :title="user ? '予約内容の確認' : 'ログインして予約を続ける'">
            <div v-if="!user"><p class="text-sm leading-7 text-[#6f7b63]">ご予約にはログインが必要です。アカウントをお持ちでない方は、新規登録からお進みください。</p><div class="mt-7 space-y-3"><Link href="/login" class="btn btn-primary w-full">ログイン<ArrowRight :size="15" /></Link><Link href="/register" class="btn btn-secondary w-full">新規登録</Link></div></div>
            <form v-else @submit.prevent="submit"><p class="text-xs leading-6 text-[#6f7b63]">以下の内容で予約します。</p><dl class="confirm-details mt-5"><div><dt>トレーニング</dt><dd>{{ session.name }}</dd></div><div><dt>日時</dt><dd>{{ selected ? formatDate(selected.date) : '' }}<br>{{ selected?.time }} – {{ endTime }}</dd></div><div><dt>トレーナー</dt><dd>{{ trainerName }}</dd></div><div><dt>料金（税込）</dt><dd>{{ formatMoney(session.price) }}</dd></div><div v-if="form.notes"><dt>ご要望</dt><dd class="whitespace-pre-wrap break-words">{{ form.notes }}</dd></div></dl><div class="mt-5 flex items-start gap-2 rounded-lg bg-[#f2f5eb] px-3 py-3 text-[11px] leading-6 text-[#6f7b63]"><Mail :size="14" class="mt-1 shrink-0" /><span>予約確認メールを<br><strong class="font-medium break-all">{{ user.email }}</strong> にお送りします。</span></div><button type="submit" class="btn btn-primary mt-6 w-full" :disabled="form.processing"><LoaderCircle v-if="form.processing" :size="16" class="animate-spin" /><Check v-else :size="15" />{{ form.processing ? '予約を処理しています…' : '予約を確定する' }}</button><button type="button" class="btn btn-quiet mt-2 w-full" :disabled="form.processing" @click="confirmOpen = false">選択に戻る</button></form>
        </Modal>
    </ClientLayout>
</template>
<style scoped>
.booking-grid { display: grid; grid-template-columns: minmax(0, 1fr) 286px; gap: 24px; align-items: start; }
.booking-sidebar { position: sticky; top: 24px; }
.calendar-card-heading { display: flex; align-items: center; justify-content: space-between; padding: 24px 24px 20px; gap: 12px; }
.calendar-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 0 24px 18px; }
.calendar-arrow { width: 29px; height: 29px; display: grid; place-items: center; border: 1px solid #e0e5d6; border-radius: 6px; background: #fff; color: #718260; }
.calendar-arrow:hover:not(:disabled), .today-button:hover { background: #f2f5eb; }
.calendar-arrow:disabled { color: #c8cebf; background: #fafbf8; }
.today-button { height: 29px; padding: 0 12px; border: 1px solid #e0e5d6; border-radius: 6px; background: #fff; font-size: 11px; color: #7e8d70; }
.session-details > div { display: flex; flex-direction: column; gap: 7px; margin-top: 19px; }
.session-details dt { display: flex; align-items: center; gap: 7px; color: #6f7b63; font-size: 11px; }
.session-details dd { padding-left: 20px; color: #536148; font-size: 12px; font-weight: 500; }
.session-details dd.placeholder { color: #6f7b63; font-size: 11px; font-weight: 400; }
.confirm-details > div { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 12px; padding: 13px 0; border-bottom: 1px solid #edf0e6; font-size: 12px; line-height: 1.9; }
.confirm-details dt { color: #6f7b63; }
.confirm-details dd { color: #4f5c44; }
@media (max-width: 1090px) { .booking-grid { grid-template-columns: minmax(0, 1fr); } .booking-sidebar { position: static; display: grid; grid-template-columns: minmax(0, 1fr) 290px; gap: 20px; align-items: start; } .booking-sidebar > div { margin-top: 0; } .booking-sidebar > p { grid-column: 2; margin-top: -50px; } }
@media (max-width: 640px) { .booking-sidebar { display: block; } .booking-sidebar > div { margin-top: 20px; } .booking-sidebar > p { margin-top: 20px; } .calendar-card-heading { padding: 20px 16px; } .calendar-toolbar { padding: 0 16px 15px; gap: 8px; flex-wrap: wrap; } .confirm-details > div { grid-template-columns: 90px minmax(0, 1fr); } }
</style>
