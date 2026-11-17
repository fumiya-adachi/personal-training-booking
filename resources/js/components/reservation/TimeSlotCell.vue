<script setup lang="ts">
import { Check, X } from 'lucide-vue-next';
import type { Slot } from '@/types';
defineProps<{ slot?: Slot; date: string; time: string; selected: boolean; loading?: boolean }>();
const emit = defineEmits<{ select: [] }>();
</script>
<template>
    <button v-if="slot?.status === 'available'" type="button" class="slot-button" :class="{ selected }" :disabled="loading" :aria-label="`${date} ${time} 予約可能`" :aria-pressed="selected" @click="emit('select')"><span v-if="selected" class="selected-check"><Check :size="14" :stroke-width="2.5" /></span><span v-else class="availability-mark" aria-hidden="true"></span></button>
    <span v-else-if="slot?.status === 'full'" class="unavailable" aria-label="予約不可"><X :size="12" :stroke-width="1.4" /></span>
    <span v-else class="unavailable" :aria-label="slot?.status === 'past' ? '受付終了' : '営業時間外'">–</span>
</template>
<style scoped>
.slot-button { display: grid; place-items: center; width: 100%; min-height: 36px; height: 100%; border: 0; background: transparent; transition: background .15s; }
.slot-button:hover:not(:disabled) { background: #fff0e9; }
.slot-button.selected { background: #fff1e9; }
.availability-mark { display: block; position: relative; width: 14px; height: 14px; border: 1.4px solid #c07b63; border-radius: 50%; }
.availability-mark::after { content: ''; position: absolute; inset: 2px; border: 1px solid #ce947f; border-radius: 50%; }
.selected-check { display: grid; place-items: center; width: 21px; height: 21px; border-radius: 50%; color: #fff; background: #d78369; }
.unavailable { display: grid; place-items: center; min-height: 36px; font-size: 12px; color: #b9bfb0; background: #f8f9f5; }
</style>
