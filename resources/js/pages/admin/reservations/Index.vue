<script setup lang="ts">
import { computed, reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, CalendarCheck2, Users, ArrowUpRight, SlidersHorizontal, RotateCcw } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { ReservationView, Trainer } from '@/types';
import { formatDate, formatMoney } from '@/composables/useFormat';

defineProps<{
    stats: { today: number; upcoming: number; clients: number };
    trainers: Trainer[];
}>();
const page = usePage();
const filters = reactive({ date: '', status: '', trainer_id: '' });
const reservations = ref<ReservationView[]>([]);
const loading = ref(true);
const error = ref('');
const hasFilters = computed(() => Boolean(filters.date || filters.status || filters.trainer_id));
let activeRequest: AbortController | undefined;

function readFilters() {
    const params = new URLSearchParams(page.url.split('?')[1] || '');
    filters.date = params.get('date') || '';
    filters.status = params.get('status') || '';
    filters.trainer_id = params.get('trainer_id') || '';
}

async function loadReservations() {
    activeRequest?.abort();
    const request = new AbortController();
    activeRequest = request;
    loading.value = true;
    error.value = '';
    reservations.value = [];
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value) params.set(key, value);
    }
    try {
        const response = await fetch(`/api/admin/reservations?${params}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: request.signal,
        });
        if (!response.ok) {
            if (response.status === 401) throw new Error('ログインの有効期限が切れました。再度ログインしてください。');
            if (response.status === 403) throw new Error('予約一覧を表示する権限がありません。');
            if (response.status === 422) {
                const body = await response.json() as { errors: Record<string, string[]> };
                throw new Error(Object.values(body.errors).flat().join(' '));
            }
            throw new Error('予約一覧を取得できませんでした。時間をおいて再試行してください。');
        }
        const body = await response.json() as { reservations: ReservationView[] };
        if (!request.signal.aborted) reservations.value = body.reservations;
    } catch (cause) {
        if (!request.signal.aborted) error.value = cause instanceof Error ? cause.message : '予約一覧を取得できませんでした。';
    } finally {
        if (!request.signal.aborted) loading.value = false;
    }
}

function applyFilters() {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value) params.set(key, value);
    }
    const url = '/admin/reservations' + (params.size ? `?${params}` : '');
    if (url === page.url) {
        void loadReservations();
    } else {
        // Update Inertia's browser history without requesting another page.
        router.push({ url, preserveState: true, preserveScroll: true });
    }
}
function resetFilters() { filters.date = ''; filters.status = ''; filters.trainer_id = ''; applyFilters(); }

watch(() => page.url, () => { readFilters(); void loadReservations(); });
onMounted(() => { readFilters(); void loadReservations(); });
onBeforeUnmount(() => activeRequest?.abort());
</script>

<template>
    <Head title="予約管理" />
    <AdminLayout>
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div><p class="page-kicker">RESERVATIONS</p><h1 class="page-title">予約管理</h1><p class="page-description">クライアントの予約と、これからのセッションを確認できます。</p></div>
            <Link href="/reservations/create" class="btn btn-secondary">予約カレンダー <ArrowUpRight :size="16" /></Link>
        </div>

        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            <div class="card flex items-center gap-4 p-5"><span class="rounded-2xl bg-[#fbe9e4] p-3 text-[#c76655]"><CalendarDays :size="23" /></span><div><p class="text-sm text-stone-500">本日の予約</p><p class="mt-1 text-3xl font-semibold tracking-tight">{{ stats.today }}<span class="ml-2 text-xs font-normal text-stone-500">件</span></p></div></div>
            <div class="card flex items-center gap-4 p-5"><span class="rounded-2xl bg-[#edf2e9] p-3 text-[#6f8760]"><CalendarCheck2 :size="23" /></span><div><p class="text-sm text-stone-500">今後の予約</p><p class="mt-1 text-3xl font-semibold tracking-tight">{{ stats.upcoming }}<span class="ml-2 text-xs font-normal text-stone-500">件</span></p></div></div>
            <div class="card flex items-center gap-4 p-5"><span class="rounded-2xl bg-[#eeedf4] p-3 text-[#82769d]"><Users :size="23" /></span><div><p class="text-sm text-stone-500">クライアント</p><p class="mt-1 text-3xl font-semibold tracking-tight">{{ stats.clients }}<span class="ml-2 text-xs font-normal text-stone-500">人</span></p></div></div>
        </div>

        <section class="card overflow-hidden" aria-labelledby="reservation-list-title">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-100 px-6 py-5">
                <h2 id="reservation-list-title" class="section-title">予約一覧 <span class="ml-2 text-sm font-normal text-stone-500">{{ loading || error ? '—' : reservations.length }}件</span></h2>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
                    <div><label for="filter-date" class="mb-1 block text-xs text-stone-500">予約日</label><input id="filter-date" v-model="filters.date" type="date" class="field min-h-10 w-40 text-sm" /></div>
                    <div><label for="filter-status" class="mb-1 block text-xs text-stone-500">ステータス</label><select id="filter-status" v-model="filters.status" class="field min-h-10 w-36 text-sm"><option value="">すべて</option><option value="confirmed">予約確定</option><option value="cancelled">キャンセル</option></select></div>
                    <div><label for="filter-trainer" class="mb-1 block text-xs text-stone-500">トレーナー</label><select id="filter-trainer" v-model="filters.trainer_id" class="field min-h-10 w-44 text-sm"><option value="">すべて</option><option v-for="trainer in trainers" :key="trainer.id" :value="String(trainer.id)">{{ trainer.name }}</option></select></div>
                    <button type="submit" class="btn btn-secondary !min-h-10 text-sm"><SlidersHorizontal :size="15" />絞り込む</button>
                    <button v-if="hasFilters" type="button" class="btn btn-quiet !min-h-10" aria-label="絞り込みを解除" @click="resetFilters"><RotateCcw :size="16" /></button>
                </form>
            </div>
            <div v-if="loading" class="empty-state py-20" role="status">予約一覧を読み込み中…</div>
            <div v-else-if="error" class="empty-state py-20"><p role="alert" class="input-error">{{ error }}</p><button type="button" class="btn btn-secondary mt-4" @click="applyFilters">再試行</button><Link href="/login" class="btn btn-quiet mt-4">ログイン画面へ</Link></div>
            <div v-else-if="!reservations.length" class="empty-state py-20"><CalendarDays :size="36" class="mx-auto mb-4 text-stone-300" /><h3 class="font-medium text-stone-700">該当する予約はありません</h3><p class="mt-2 text-sm text-stone-500">日付・ステータス・トレーナーを変更して確認してください。</p></div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="bg-stone-50/70 text-xs text-stone-500"><tr><th class="px-6 py-4 font-medium">日時 / 予約番号</th><th class="px-5 py-4 font-medium">クライアント</th><th class="px-5 py-4 font-medium">トレーナー</th><th class="px-5 py-4 font-medium">料金</th><th class="px-5 py-4 font-medium">ステータス</th><th class="px-5 py-4"><span class="sr-only">詳細</span></th></tr></thead>
                    <tbody class="divide-y divide-stone-100"><tr v-for="reservation in reservations" :key="reservation.id" class="transition-colors hover:bg-stone-50/60"><td class="px-6 py-5"><p class="font-medium">{{ formatDate(reservation.date) }} <span class="ml-2 tabular-nums">{{ reservation.time }}–{{ reservation.end_time }}</span></p><p class="mt-1.5 text-xs tracking-wide text-stone-500">{{ reservation.reference }}</p></td><td class="px-5 py-5"><p class="font-medium">{{ reservation.client.name }}<span class="ml-1 font-normal text-stone-500">様</span></p><p class="mt-1 text-xs text-stone-500">{{ reservation.client.email }}</p></td><td class="px-5 py-5">{{ reservation.trainer.name }}</td><td class="whitespace-nowrap px-5 py-5 tabular-nums">{{ formatMoney(reservation.price) }}</td><td class="px-5 py-5"><span class="badge" :class="reservation.status === 'confirmed' ? 'badge-confirmed' : 'badge-cancelled'">{{ reservation.status === 'confirmed' ? '予約確定' : 'キャンセル' }}</span></td><td class="px-5 py-5"><Link :href="`/reservations/${reservation.id}`" class="inline-flex items-center gap-1 whitespace-nowrap text-sm font-medium text-[#b65c4d] hover:underline" :aria-label="`${reservation.client.name}様の予約詳細`">詳細<ArrowUpRight :size="14" /></Link></td></tr></tbody>
                </table>
            </div>
        </section>
    </AdminLayout>
</template>
