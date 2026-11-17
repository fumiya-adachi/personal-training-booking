<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays, Check, CheckCircle2, Clock3, Dumbbell, Mail, MapPin, UserRound } from 'lucide-vue-next';
import ClientLayout from '@/layouts/ClientLayout.vue';
import Modal from '@/components/ui/Modal.vue';
import { formatDate, formatMoney } from '@/composables/useFormat';
import type { ReservationView, SharedProps } from '@/types';
const props = defineProps<{ reservation: ReservationView }>();
const page = usePage<SharedProps>();
const justCreated = computed(() => props.reservation.status === 'confirmed' && !!page.props.flash?.success);
const canCancel = computed(() => props.reservation.status === 'confirmed' && new Date(props.reservation.starts_at).getTime() > Date.now());
const cancelOpen = ref(false); const cancelling = ref(false); const cancelError = ref('');
function cancel() { cancelling.value = true; cancelError.value = ''; router.delete(`/reservations/${props.reservation.id}`, { onError: errors => { cancelError.value = Object.values(errors).join(' '); }, onFinish: () => { cancelling.value = false; } }); }
</script>
<template>
    <Head :title="justCreated ? 'ご予約が完了しました' : '予約の詳細'" />
    <ClientLayout>
        <div class="mx-auto max-w-[720px] py-2 sm:py-5">
            <div class="text-center"><p class="page-kicker">{{ justCreated ? 'RESERVATION COMPLETE' : 'RESERVATION DETAILS' }}</p><span v-if="justCreated" class="mx-auto mt-6 mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#e8eee0] text-[#6f7b63]"><Check :size="30" :stroke-width="1.7" /></span><h1 class="page-title">{{ reservation.status === 'cancelled' ? 'キャンセル済みのご予約' : justCreated ? 'ご予約が完了しました' : 'ご予約の詳細' }}</h1><p class="page-description">{{ justCreated ? 'トレーニングでお会いできるのを楽しみにしています。' : '予約内容をご確認いただけます。' }}</p></div>
            <ol v-if="justCreated" class="booking-steps justify-center" aria-label="予約の手順"><li class="booking-step done"><span class="step-dot"><Check :size="10" /></span>日時を選択</li><li class="step-line" aria-hidden="true"></li><li class="booking-step done"><span class="step-dot"><Check :size="10" /></span>内容を確認</li><li class="step-line" aria-hidden="true"></li><li class="booking-step active" aria-current="step"><span class="step-dot">03</span>予約完了</li></ol>
            <section class="card mt-6 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e8ede0] bg-[#fcfdf9] px-7 py-5"><div><p class="text-[11px] tracking-widest text-[#6f7b63]">RESERVATION NUMBER</p><p class="mt-2 font-mono text-[13px] font-medium tracking-wider text-[#687b54]">{{ reservation.reference }}</p></div><span class="badge" :class="reservation.status === 'confirmed' ? 'badge-confirmed' : 'badge-cancelled'"><CheckCircle2 v-if="reservation.status === 'confirmed'" :size="11" />{{ reservation.status === 'confirmed' ? '予約確定' : 'キャンセル済み' }}</span></div>
                <div class="px-6 py-7 sm:px-9">
                    <div class="mb-6 flex items-center gap-3"><span class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f1f4e9] text-[#6f7b63]"><Dumbbell :size="25" :stroke-width="1.4" /></span><div><h2 class="text-[16px] font-semibold">パーソナルトレーニング</h2><p class="mt-1 text-[11px] text-[#6f7b63]">マンツーマン・トレーニング / {{ reservation.duration }}分</p></div></div>
                    <dl class="reservation-detail-list">
                        <div><dt><CalendarDays :size="14" />日付</dt><dd>{{ formatDate(reservation.date) }}</dd></div>
                        <div><dt><Clock3 :size="14" />時間</dt><dd>{{ reservation.time }} – {{ reservation.end_time }}<span class="ml-2 text-[11px] font-normal text-[#6f7b63]">日本時間</span></dd></div>
                        <div><dt><UserRound :size="14" />トレーナー</dt><dd>{{ reservation.trainer.name }}</dd></div>
                        <div><dt><MapPin :size="14" />場所</dt><dd>表参道スタジオ</dd></div>
                        <div><dt><UserRound :size="14" />ご予約者</dt><dd>{{ reservation.client.name }} 様</dd></div>
                        <div v-if="reservation.notes"><dt>ご要望・連絡事項</dt><dd class="whitespace-pre-wrap break-words font-normal!">{{ reservation.notes }}</dd></div>
                    </dl>
                    <div class="mt-6 flex items-end justify-between border-t border-dashed border-[#dfe6d3] pt-6"><span class="text-[12px] text-[#6f7b63]">合計<span class="ml-1 text-[11px]">（税込）</span></span><span class="text-[27px] font-semibold tracking-tight">{{ formatMoney(reservation.price) }}</span></div>
                </div>
            </section>
            <div v-if="reservation.status === 'confirmed'" class="mt-5 flex items-start gap-3 rounded-xl border border-[#e1e8d5] bg-[#edf2e5] px-6 py-4"><Mail :size="18" :stroke-width="1.4" class="mt-1 shrink-0 text-[#6f7b63]" /><div><p class="text-[11px] font-medium text-[#6f7b63]">予約確認メールの送信先</p><p class="mt-1 text-[12px] break-all text-[#667e50]">{{ reservation.client.email }}</p><p class="mt-2 text-[11px] leading-5 text-[#6f7b63]">メールが届かない場合も、こちらの予約一覧から内容を確認できます。</p></div></div>
            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row"><Link href="/reservations" class="btn btn-primary sm:min-w-[210px]">予約一覧を見る<ArrowRight :size="15" /></Link><Link href="/reservations/create" class="btn btn-secondary sm:min-w-[210px]">続けて予約する</Link></div>
            <p v-if="canCancel" class="mt-5 text-center"><button type="button" class="btn btn-quiet text-[11px]!" @click="cancelOpen = true">この予約をキャンセルする</button></p>
        </div>
        <Modal v-model="cancelOpen" title="予約をキャンセルしますか？"><p class="text-[12px] leading-7 text-[#6f7b63]">{{ formatDate(reservation.date) }} {{ reservation.time }}からの予約をキャンセルします。この操作を行うと、予約枠が再び公開されます。</p><p v-if="cancelError" class="input-error mt-4!" role="alert">{{ cancelError }}</p><button type="button" class="btn btn-danger mt-6 w-full" :disabled="cancelling" @click="cancel">{{ cancelling ? '処理しています…' : 'キャンセルする' }}</button><button type="button" class="btn btn-quiet mt-2 w-full" :disabled="cancelling" @click="cancelOpen = false">予約を保持する</button></Modal>
    </ClientLayout>
</template>
<style scoped>.reservation-detail-list > div { display: grid; grid-template-columns: 145px minmax(0, 1fr); gap: 15px; padding-block: 14px; border-bottom: 1px solid #edf1e5; font-size: 12px; line-height: 1.8; }.reservation-detail-list dt { display: flex; align-items: center; gap: 8px; color: #6f7b63; font-size: 11px; }.reservation-detail-list dd { color: #5c6f49; font-weight: 500; }@media (max-width: 640px) { .reservation-detail-list > div { grid-template-columns: 105px minmax(0, 1fr); gap: 10px; } .reservation-detail-list dt { font-size: 11px; gap: 6px; } }</style>
