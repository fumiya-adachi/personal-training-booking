<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, Users, CalendarClock, ArrowUpRight, LogOut, CheckCircle2, AlertCircle } from 'lucide-vue-next';
import type { SharedProps } from '@/types';
const page = usePage<SharedProps>();
const current = computed(() => page.url.split('?')[0]);
const links = [{ href: '/admin/reservations', label: '予約管理', icon: CalendarDays }, { href: '/admin/trainers', label: 'トレーナー', icon: Users }, { href: '/admin/schedules', label: 'スケジュール', icon: CalendarClock }];
</script>
<template>
    <div class="app-shell">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">本文へスキップ</a>
        <header class="app-header"><div class="app-width header-inner">
            <Link href="/admin/reservations" class="brand" aria-label="FORM 管理画面"><span class="brand-symbol" aria-hidden="true"><i></i><i></i><i></i><i></i></span><span><span class="brand-name">FORM</span><span class="brand-caption block">STUDIO MANAGEMENT</span></span></Link>
            <nav class="header-nav" aria-label="管理ナビゲーション"><Link v-for="link in links" :key="link.href" :href="link.href" class="nav-link" :class="{ active: current === link.href }"><component :is="link.icon" :size="15" />{{ link.label }}</Link></nav>
            <div class="header-account"><Link href="/" class="flex items-center gap-1.5 text-[#6f7b63] hover:text-ink">予約画面<ArrowUpRight :size="14" /></Link><button class="icon-button border-0!" type="button" aria-label="ログアウト" @click="router.post('/logout')"><LogOut :size="16" /></button></div>
        </div></header>
        <main id="main-content" class="app-width page-main">
            <div v-if="page.props.flash?.success" role="status" class="flash flash-success"><CheckCircle2 :size="18" class="shrink-0" />{{ page.props.flash.success }}</div>
            <div v-if="page.props.flash?.error" role="alert" class="flash flash-error"><AlertCircle :size="18" class="shrink-0" />{{ page.props.flash.error }}</div>
            <slot />
        </main>
        <footer class="app-footer"><div class="app-width footer-inner"><span>© {{ new Date().getFullYear() }} FORM PERSONAL TRAINING</span><span>STUDIO MANAGEMENT</span></div></footer>
    </div>
</template>
