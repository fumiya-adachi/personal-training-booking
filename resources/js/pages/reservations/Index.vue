<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays, Clock3, Dumbbell, Plus, UserRound } from 'lucide-vue-next';
import ClientLayout from '@/layouts/ClientLayout.vue';
import Modal from '@/components/ui/Modal.vue';
import { formatDate, formatMoney } from '@/composables/useFormat';
import type { ReservationView } from '@/types';
const props = defineProps<{ reservations: ReservationView[] }>();
const tab = ref<'upcoming' | 'history'>('upcoming');
const cancelTarget = ref<ReservationView | null>(null);
const cancelling = ref(false);
const cancelError = ref('');
const upcoming = computed(() => props.reservations.filter(r => r.status === 'confirmed' && new Date(r.starts_at).getTime() > Date.now()).sort((a, b) => a.starts_at.localeCompare(b.starts_at)));
const history = computed(() => props.reservations.filter(r => r.status === 'cancelled' || new Date(r.starts_at).getTime() <= Date.now()).sort((a, b) => b.starts_at.localeCompare(a.starts_at)));
const visible = computed(() => tab.value === 'upcoming' ? upcoming.value : history.value);
function cancel() { if (!cancelTarget.value) return; cancelling.value = true; cancelError.value = ''; router.delete(`/reservations/${cancelTarget.value.id}`, { preserveScroll: true, onSuccess: () => { cancelTarget.value = null; }, onError: errors => { cancelError.value = Object.values(errors).join(' '); }, onFinish: () => { cancelling.value = false; } }); }
</script>
<template>
    <Head title="予約一覧" />
    <ClientLayout>
        <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="page-kicker">MY RESERVATIONS</p><h1 class="page-title">予約一覧</h1><p class="page-description">これからの予定と、これまでのトレーニング。</p></div><Link href="/reservations/create" class="btn btn-primary"><Plus :size="15" />新しく予約する</Link></div>
        <div class="mt-8 flex gap-7 border-b border-[#e0e5d6]" role="tablist" aria-label="予約の種類"><button id="tab-upcoming" type="button" role="tab" :aria-selected="tab === 'upcoming'" aria-controls="reservation-list" class="reservation-tab" :class="{ active: tab === 'upcoming' }" @click="tab = 'upcoming'">これからの予約<span>{{ upcoming.length }}</span></button><button id="tab-history" type="button" role="tab" :aria-selected="tab === 'history'" aria-controls="reservation-list" class="reservation-tab" :class="{ active: tab === 'history' }" @click="tab = 'history'">履歴・キャンセル<span>{{ history.length }}</span></button></div>
        <section id="reservation-list" role="tabpanel" :aria-labelledby="`tab-${tab}`" class="mt-6 space-y-4">
            <article v-for="reservation in visible" :key="reservation.id" class="card reservation-list-card">
                <div class="reservation-date"><span class="text-[11px] text-[#6f7b63]">{{ reservation.date.slice(0, 4) }}年</span><span class="text-[26px] font-semibold tracking-tight text-[#4f5b43]">{{ Number(reservation.date.slice(5, 7)) }}<span class="mx-0.5 text-lg font-normal text-[#6f7b63]">/</span>{{ Number(reservation.date.slice(8)) }}</span><span class="mt-1 text-[11px] text-[#6f7b63]">{{ formatDate(reservation.date).split('日')[1] }}</span></div>
                <div class="flex-1"><div class="flex flex-wrap items-center gap-3"><h2 class="text-[15px] font-semibold">パーソナルトレーニング</h2><span class="badge" :class="reservation.status === 'cancelled' ? 'badge-cancelled' : 'badge-confirmed'">{{ reservation.status === 'cancelled' ? 'キャンセル済み' : tab === 'history' ? '終了' : '予約確定' }}</span></div><div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-[11px] text-[#6f7b63]"><span class="flex items-center gap-1.5"><Clock3 :size="13" />{{ reservation.time }} – {{ reservation.end_time }}（{{ reservation.duration }}分）</span><span class="flex items-center gap-1.5"><UserRound :size="13" />{{ reservation.trainer.name }}</span><span>{{ formatMoney(reservation.price) }}<span class="ml-1 text-[11px]">税込</span></span></div><p class="mt-3 font-mono text-[11px] tracking-wider text-[#6f7b63]">{{ reservation.reference }}</p></div>
                <div class="flex shrink-0 flex-col items-end gap-2 max-sm:flex-row"><Link :href="`/reservations/${reservation.id}`" class="btn btn-secondary min-h-9! py-2! text-[11px]!">詳細を見る<ArrowRight :size="13" /></Link><button v-if="tab === 'upcoming'" type="button" class="btn btn-quiet min-h-8! py-1! text-[11px]!" @click="cancelTarget = reservation">予約をキャンセル</button></div>
            </article>
            <div v-if="visible.length === 0" class="card empty-state"><span class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-[#f0f3e9] text-[#6f7b63]"><CalendarDays :size="25" :stroke-width="1.3" /></span><h2 class="text-[15px] font-semibold text-[#6a7959]">{{ tab === 'upcoming' ? 'これからの予約はありません' : '予約履歴はまだありません' }}</h2><p class="mt-2 text-[12px] text-[#6f7b63]">{{ tab === 'upcoming' ? 'あなたに合った時間で、次のトレーニングを。' : 'トレーニングの記録がここに表示されます。' }}</p><Link href="/reservations/create" class="btn btn-primary mt-6">トレーニングを予約<ArrowRight :size="14" /></Link></div>
        </section>
        <Modal :model-value="!!cancelTarget" title="予約をキャンセルしますか？" @update:model-value="value => { if (!value) cancelTarget = null; }"><p class="text-[12px] leading-7 text-[#6f7b63]">{{ cancelTarget ? formatDate(cancelTarget.date) : '' }} {{ cancelTarget?.time }}からの予約をキャンセルします。この操作を行うと、予約枠が再び公開されます。</p><p v-if="cancelError" class="input-error mt-4!" role="alert">{{ cancelError }}</p><button type="button" class="btn btn-danger mt-6 w-full" :disabled="cancelling" @click="cancel">{{ cancelling ? '処理しています…' : 'キャンセルする' }}</button><button type="button" class="btn btn-quiet mt-2 w-full" :disabled="cancelling" @click="cancelTarget = null">予約を保持する</button></Modal>
    </ClientLayout>
</template>
<style scoped>
.reservation-tab { display: flex; align-items: center; gap: 8px; padding: 16px 0; color: #6f7b63; font-size: 12px; border-bottom: 2px solid transparent; margin-bottom: -1px; }
.reservation-tab span { font-size: 11px; background: #e8ede0; color: #94a37e; padding: 2px 6px; border-radius: 5px; }
.reservation-tab.active { border-color: #df947e; color: #4d5d40; font-weight: 600; }
.reservation-list-card { display: flex; gap: 27px; align-items: center; padding: 24px; }
.reservation-date { display: flex; flex-direction: column; text-align: center; padding-right: 25px; border-right: 1px solid #e8ede0; min-width: 95px; }
@media (max-width: 640px) { .reservation-list-card { flex-wrap: wrap; gap: 18px; padding: 20px; } .reservation-date { min-width: 70px; padding-right: 17px; } .reservation-list-card > div:last-child { width: 100%; padding-top: 15px; border-top: 1px solid #edf0e6; justify-content: space-between; align-items: center; } .reservation-tab { font-size: 11px; gap: 5px; } }
</style>
