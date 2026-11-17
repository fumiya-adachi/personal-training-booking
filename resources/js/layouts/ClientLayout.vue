<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, CalendarDays, ListChecks, CheckCircle2, AlertCircle, LogOut } from 'lucide-vue-next';
import type { SharedProps } from '@/types';
const page = usePage<SharedProps>();
const user = computed(() => page.props.auth?.user);
const current = computed(() => page.url.split('?')[0]);
</script>
<template>
    <div class="app-shell">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">本文へスキップ</a>
        <header class="app-header">
            <div class="app-width header-inner">
                <Link href="/" class="brand" aria-label="FORM ホーム">
                    <span class="brand-symbol" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                    <span><span class="brand-name">FORM</span><span class="brand-caption block">PERSONAL TRAINING</span></span>
                </Link>
                <nav class="header-nav" aria-label="メインナビゲーション">
                    <Link href="/reservations/create" class="nav-link" :class="{ active: current === '/' || current === '/reservations/create' }"><CalendarDays :size="15" />トレーニング予約</Link>
                    <Link href="/reservations" class="nav-link" :class="{ active: current.startsWith('/reservations') && current !== '/reservations/create' }"><ListChecks :size="15" />予約一覧</Link>
                    <Link v-if="user?.role === 'admin'" href="/admin/reservations" class="nav-link">管理画面<ArrowUpRight :size="13" /></Link>
                </nav>
                <div class="header-account">
                    <template v-if="user"><span class="account-avatar" aria-hidden="true">{{ user.name.slice(0, 1) }}</span><span class="hidden sm:inline">{{ user.name }} さん</span><button class="icon-button border-0!" type="button" aria-label="ログアウト" @click="router.post('/logout')"><LogOut :size="16" /></button></template>
                    <template v-else><Link href="/register" class="hidden sm:inline text-[#6f7b63] hover:text-ink">新規登録</Link><Link href="/login" class="btn btn-secondary">ログイン<ArrowUpRight :size="13" /></Link></template>
                </div>
            </div>
        </header>
        <main id="main-content" class="app-width page-main">
            <div v-if="page.props.flash?.success" role="status" class="flash flash-success"><CheckCircle2 :size="18" class="mt-0.5 shrink-0" />{{ page.props.flash.success }}</div>
            <div v-if="page.props.flash?.error" role="alert" class="flash flash-error"><AlertCircle :size="18" class="mt-0.5 shrink-0" />{{ page.props.flash.error }}</div>
            <slot />
        </main>
        <footer class="app-footer"><div class="app-width footer-inner"><span>© {{ new Date().getFullYear() }} FORM PERSONAL TRAINING</span><div class="flex items-center gap-5"><span>あなたらしい、健やかな毎日へ。</span><span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-[#a4b58d]"></span>表参道スタジオ</span></div></div></footer>
    </div>
</template>
