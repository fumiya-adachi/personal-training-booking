<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Eye, EyeOff, LoaderCircle, LockKeyhole } from 'lucide-vue-next';
import ClientLayout from '@/layouts/ClientLayout.vue';
const showPassword = ref(false);
const form = useForm({ email: '', password: '', remember: false });
function submit() { form.post('/login', { onFinish: () => { form.reset('password'); } }); }
</script>
<template>
    <Head title="ログイン" />
    <ClientLayout>
        <div class="mx-auto max-w-[430px] py-5 sm:py-10">
            <div class="mb-7 text-center"><p class="page-kicker">WELCOME BACK</p><h1 class="page-title mt-3!">おかえりなさい。</h1><p class="page-description">ログインして、次のトレーニングを予約しましょう。</p></div>
            <div class="card p-7 sm:p-9">
                <form class="space-y-5" @submit.prevent="submit">
                    <div><label for="email" class="field-label">メールアドレス</label><input id="email" v-model="form.email" class="field" type="email" name="email" autocomplete="email" autofocus required maxlength="255" placeholder="you@example.com" :aria-invalid="!!form.errors.email" :aria-describedby="form.errors.email ? 'email-error' : undefined"><p v-if="form.errors.email" id="email-error" class="input-error" role="alert">{{ form.errors.email }}</p></div>
                    <div><label for="password" class="field-label">パスワード</label><div class="relative"><input id="password" v-model="form.password" class="field pr-12!" :type="showPassword ? 'text' : 'password'" name="password" autocomplete="current-password" required :aria-invalid="!!form.errors.password" :aria-describedby="form.errors.password ? 'password-error' : undefined"><button type="button" class="absolute top-1.5 right-1.5 flex h-8 w-8 items-center justify-center rounded text-[#6f7b63]" :aria-label="showPassword ? 'パスワードを隠す' : 'パスワードを表示'" @click="showPassword = !showPassword"><EyeOff v-if="showPassword" :size="16" /><Eye v-else :size="16" /></button></div><p v-if="form.errors.password" id="password-error" class="input-error">{{ form.errors.password }}</p></div>
                    <label class="flex items-center gap-2.5 text-[11px] text-[#6f7b63]"><input v-model="form.remember" name="remember" type="checkbox" class="h-4 w-4 rounded accent-[#73845f]">ログイン状態を保持する</label>
                    <button type="submit" class="btn btn-primary w-full justify-between!" :disabled="form.processing"><span>{{ form.processing ? 'ログインしています…' : 'ログイン' }}</span><LoaderCircle v-if="form.processing" :size="16" class="animate-spin" /><ArrowRight v-else :size="16" /></button>
                </form>
                <div class="mt-7 border-t border-[#edf0e6] pt-6 text-center"><p class="text-[11px] text-[#6f7b63]">はじめてご利用の方</p><Link href="/register" class="mt-3 inline-flex items-center gap-1.5 text-[12px] font-semibold text-[#6f7b63] hover:text-ink">新規アカウント登録<ArrowRight :size="13" /></Link></div>
            </div>
            <p class="mt-6 flex items-center justify-center gap-1.5 text-[11px] text-[#6f7b63]"><LockKeyhole :size="11" />あなたのペースで、あなたらしいトレーニングを。</p>
        </div>
    </ClientLayout>
</template>
