<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, LoaderCircle, Sparkles } from 'lucide-vue-next';
import ClientLayout from '@/layouts/ClientLayout.vue';
const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
function submit() { form.post('/register', { onFinish: () => { form.reset('password', 'password_confirmation'); } }); }
</script>
<template>
    <Head title="新規登録" />
    <ClientLayout>
        <div class="mx-auto max-w-[450px] py-4 sm:py-8">
            <div class="mb-7 text-center"><p class="page-kicker">LET'S START</p><h1 class="page-title mt-3!">新しい習慣の、はじまり。</h1><p class="page-description">無料のアカウント登録で、ご予約が可能になります。</p></div>
            <div class="card p-7 sm:p-9">
                <form class="space-y-5" @submit.prevent="submit">
                    <div><label for="name" class="field-label">お名前</label><input id="name" v-model="form.name" class="field" name="name" autocomplete="name" autofocus required maxlength="100" placeholder="山田 花子" :aria-invalid="!!form.errors.name" :aria-describedby="form.errors.name ? 'name-error' : undefined"><p v-if="form.errors.name" id="name-error" class="input-error">{{ form.errors.name }}</p></div>
                    <div><label for="email" class="field-label">メールアドレス</label><input id="email" v-model="form.email" class="field" type="email" name="email" autocomplete="email" required maxlength="255" placeholder="you@example.com" :aria-invalid="!!form.errors.email" :aria-describedby="form.errors.email ? 'email-error' : undefined"><p v-if="form.errors.email" id="email-error" class="input-error">{{ form.errors.email }}</p></div>
                    <div><label for="password" class="field-label">パスワード</label><input id="password" v-model="form.password" class="field" type="password" name="password" autocomplete="new-password" required minlength="8" aria-describedby="password-help password-error" :aria-invalid="!!form.errors.password"><p id="password-help" class="mt-2 text-[11px] text-[#6f7b63]">8文字以上で入力してください。</p><p v-if="form.errors.password" id="password-error" class="input-error">{{ form.errors.password }}</p></div>
                    <div><label for="password_confirmation" class="field-label">パスワード（確認）</label><input id="password_confirmation" v-model="form.password_confirmation" class="field" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8" :aria-invalid="!!form.errors.password_confirmation"><p v-if="form.errors.password_confirmation" class="input-error">{{ form.errors.password_confirmation }}</p></div>
                    <button type="submit" class="btn btn-primary w-full justify-between!" :disabled="form.processing"><span>{{ form.processing ? '登録しています…' : 'アカウントを作成' }}</span><LoaderCircle v-if="form.processing" :size="16" class="animate-spin" /><ArrowRight v-else :size="16" /></button>
                </form>
                <p class="mt-7 border-t border-[#edf0e6] pt-6 text-center text-[11px] text-[#6f7b63]">すでにアカウントをお持ちの方は<Link href="/login" class="ml-1 font-semibold text-[#6f7b63] hover:text-ink">ログイン</Link></p>
            </div>
            <p class="mt-6 flex items-center justify-center gap-1.5 text-[11px] text-[#6f7b63]"><Sparkles :size="12" />一歩ずつ、なりたい自分へ。</p>
        </div>
    </ClientLayout>
</template>
