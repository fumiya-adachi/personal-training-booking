<script setup lang="ts">
import { Check, Users } from 'lucide-vue-next';
import type { Trainer } from '@/types';
defineProps<{ trainers: Trainer[]; selectedId: number | null; disabled?: boolean }>();
const emit = defineEmits<{ change: [id: number | null] }>();
</script>
<template>
    <div class="trainer-selector" role="group" aria-label="トレーナーを選択">
        <button type="button" class="trainer-pill" :class="{ selected: selectedId === null }" :aria-pressed="selectedId === null" :disabled="disabled" @click="emit('change', null)">
            <span class="trainer-avatar automatic"><Users :size="17" /></span><span class="trainer-info"><span class="trainer-name">指名なし</span><span class="trainer-specialty">おまかせで予約</span></span><span v-if="selectedId === null" class="trainer-check"><Check :size="10" :stroke-width="3" /></span>
        </button>
        <button v-for="trainer in trainers" :key="trainer.id" type="button" class="trainer-pill" :class="{ selected: selectedId === trainer.id }" :aria-pressed="selectedId === trainer.id" :disabled="disabled" @click="emit('change', trainer.id)">
            <span class="trainer-avatar" :style="{ backgroundColor: trainer.color + '20', color: trainer.color }">{{ trainer.name.slice(0, 1) }}</span><span class="trainer-info"><span class="trainer-name">{{ trainer.name }}</span><span class="trainer-specialty">{{ trainer.specialty }}</span></span><span v-if="selectedId === trainer.id" class="trainer-check"><Check :size="10" :stroke-width="3" /></span>
        </button>
    </div>
</template>
<style scoped>
.trainer-selector { display: flex; gap: 10px; flex-wrap: wrap; }
.trainer-pill { position: relative; display: flex; align-items: center; gap: 9px; padding: 10px 13px; border: 1px solid #e5e7df; border-radius: 10px; background: #fff; text-align: left; min-height: 62px; transition: border-color .15s, background .15s; flex: 1 1 130px; }
.trainer-pill:hover:not(:disabled) { background: #fafbf6; border-color: #b6c0ab; }
.trainer-pill.selected { border-color: #e39784; background: #fff8f4; box-shadow: 0 0 0 1px #e3978430; }
.trainer-avatar { width: 33px; height: 33px; display: grid; place-items: center; border-radius: 50%; flex: none; font-size: 15px; font-weight: 600; }
.trainer-avatar.automatic { background: #efeee7; color: #918a7a; }
.trainer-info { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.trainer-name { font-size: 12px; font-weight: 600; line-height: 1.2; }
.trainer-specialty { font-size: 11px; color: #6f7b63; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 125px; }
.trainer-check { position: absolute; top: -5px; right: -5px; width: 16px; height: 16px; display: grid; place-items: center; background: #e39784; border-radius: 50%; color: white; border: 2px solid white; }
.trainer-pill:disabled { opacity: .6; }
@media (max-width: 640px) { .trainer-selector { gap: 8px; } .trainer-pill { flex-basis: 130px; padding: 9px 11px; min-height: 58px; } }
</style>
