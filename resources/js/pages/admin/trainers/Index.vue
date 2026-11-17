<script setup lang="ts">
import { nextTick, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Pencil, X, Users, LoaderCircle } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Trainer } from '@/types';

const props = defineProps<{ trainers: Trainer[] }>();
const dialog = ref<HTMLDialogElement | null>(null);
const nameInput = ref<HTMLInputElement | null>(null);
const editing = ref<Trainer | null>(null);
const form = useForm({ name: '', specialty: '', bio: '', color: '#e89b85', is_active: true });
const palette = ['#e89b85', '#a7b59c', '#b5a3c7', '#9db8c5', '#d2b58f', '#aaa8a0'];
function openEditor(trainer: Trainer | null = null) {
    editing.value = trainer;
    form.clearErrors();
    form.name = trainer?.name ?? '';
    form.specialty = trainer?.specialty ?? '';
    form.bio = trainer?.bio ?? '';
    form.color = trainer?.color ?? palette[0]!;
    form.is_active = trainer?.is_active ?? true;
    dialog.value?.showModal();
    nextTick(() => nameInput.value?.focus());
}
function save() {
    const options = { preserveScroll: true, onSuccess: () => dialog.value?.close() };
    if (editing.value) form.patch(`/admin/trainers/${editing.value.id}`, options);
    else form.post('/admin/trainers', options);
}
</script>

<template>
    <Head title="トレーナー管理" />
    <AdminLayout>
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4"><div><p class="page-kicker">TRAINERS</p><h1 class="page-title">トレーナー管理</h1><p class="page-description">プロフィールと予約受付の状態を管理できます。</p></div><button class="btn btn-primary" @click="openEditor()"><Plus :size="18" />トレーナーを追加</button></div>
        <div v-if="!props.trainers.length" class="card empty-state py-20"><Users :size="38" class="mx-auto mb-4 text-stone-300" /><h2 class="font-medium">トレーナーを登録しましょう</h2><p class="mt-2 text-sm text-stone-500">登録後にスケジュールを設定すると、予約を受け付けられます。</p><button class="btn btn-secondary mt-6" @click="openEditor()"><Plus :size="16" />トレーナーを追加</button></div>
        <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="trainer in props.trainers" :key="trainer.id" class="card flex flex-col overflow-hidden">
                <div class="h-2" :style="{ backgroundColor: trainer.color }"></div>
                <div class="flex flex-1 flex-col p-6">
                    <div class="mb-5 flex items-start justify-between gap-4"><div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-2xl font-medium text-stone-800" :style="{ backgroundColor: `${trainer.color}40` }">{{ trainer.name.slice(0, 1) }}</div><span class="badge" :class="trainer.is_active ? 'badge-confirmed' : 'badge-cancelled'">{{ trainer.is_active ? '予約受付中' : '受付停止中' }}</span></div>
                    <h2 class="text-xl font-semibold tracking-tight">{{ trainer.name }}</h2><p class="mt-1.5 text-xs font-medium tracking-wide text-[#b65c4d]">{{ trainer.specialty || 'パーソナルトレーナー' }}</p><p class="mt-4 flex-1 whitespace-pre-line text-sm leading-7 text-stone-500">{{ trainer.bio || 'プロフィールは未設定です。' }}</p>
                    <button class="btn btn-secondary mt-6 w-full" :aria-label="`${trainer.name}のプロフィールを編集`" @click="openEditor(trainer)"><Pencil :size="15" />プロフィールを編集</button>
                </div>
            </article>
        </div>

        <dialog ref="dialog" class="modal" aria-labelledby="trainer-editor-title" @click="($event.target === dialog) && dialog?.close()">
            <form class="p-6 sm:p-8" @submit.prevent="save">
                <div class="mb-6 flex items-center justify-between gap-4"><h2 id="trainer-editor-title" class="text-xl font-semibold">{{ editing ? 'プロフィールを編集' : 'トレーナーを追加' }}</h2><button type="button" class="rounded-full p-2 text-stone-500 hover:bg-stone-100" aria-label="閉じる" @click="dialog?.close()"><X :size="20" /></button></div>
                <div class="space-y-5">
                    <div><label for="trainer-name" class="field-label">名前 <span class="text-[#c76655]">*</span></label><input id="trainer-name" ref="nameInput" v-model="form.name" class="field w-full" type="text" maxlength="100" required :aria-invalid="!!form.errors.name" :aria-describedby="form.errors.name ? 'trainer-name-error' : undefined" /><p v-if="form.errors.name" id="trainer-name-error" class="input-error">{{ form.errors.name }}</p></div>
                    <div><label for="trainer-specialty" class="field-label">得意分野 <span class="text-[#c76655]">*</span></label><input id="trainer-specialty" v-model="form.specialty" class="field w-full" type="text" maxlength="120" placeholder="例：ボディメイク・姿勢改善" required :aria-invalid="!!form.errors.specialty" /><p v-if="form.errors.specialty" class="input-error">{{ form.errors.specialty }}</p></div>
                    <div><label for="trainer-bio" class="field-label">プロフィール</label><textarea id="trainer-bio" v-model="form.bio" class="field w-full resize-y" rows="4" maxlength="1000" placeholder="経歴や、トレーニングへの想いを入力してください。" :aria-invalid="!!form.errors.bio"></textarea><p v-if="form.errors.bio" class="input-error">{{ form.errors.bio }}</p></div>
                    <fieldset><legend class="field-label">プロフィールカラー</legend><div class="flex items-center gap-3"><button v-for="color in palette" :key="color" type="button" class="h-8 w-8 rounded-full border-2 border-white outline-offset-2 transition-transform hover:scale-110" :class="form.color === color ? 'outline-2 outline-stone-600' : ''" :style="{ backgroundColor: color }" :aria-label="`カラー ${color}`" :aria-pressed="form.color === color" @click="form.color = color"></button><label class="sr-only" for="trainer-color">カスタムカラー</label><input id="trainer-color" v-model="form.color" type="color" class="h-8 w-8 cursor-pointer rounded-full border-0 bg-transparent p-0" /></div><p v-if="form.errors.color" class="input-error">{{ form.errors.color }}</p></fieldset>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-stone-200 px-4 py-3"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-[#c76655]" /><span class="text-sm">予約を受け付ける</span></label><p v-if="form.errors.is_active" class="input-error">{{ form.errors.is_active }}</p>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" class="btn btn-secondary" :disabled="form.processing" @click="dialog?.close()">キャンセル</button><button type="submit" class="btn btn-primary" :disabled="form.processing"><LoaderCircle v-if="form.processing" :size="16" class="animate-spin" />{{ form.processing ? '保存中…' : '保存する' }}</button></div>
            </form>
        </dialog>
    </AdminLayout>
</template>
