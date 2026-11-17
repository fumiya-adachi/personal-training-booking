<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { X } from 'lucide-vue-next';
const props = defineProps<{ modelValue: boolean; title: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const dialog = ref<HTMLDialogElement>();
function sync() { if (!dialog.value) return; if (props.modelValue && !dialog.value.open) dialog.value.showModal(); else if (!props.modelValue && dialog.value.open) dialog.value.close(); }
watch(() => props.modelValue, sync); onMounted(sync);
function close() { emit('update:modelValue', false); }
</script>
<template>
    <dialog ref="dialog" class="modal" aria-labelledby="modal-title" @cancel.prevent="close" @click="($event.target === dialog) && close()" @close="close">
        <div class="modal-content">
            <div class="modal-header"><h2 id="modal-title" class="modal-title">{{ title }}</h2><button type="button" class="icon-button" aria-label="閉じる" @click="close"><X :size="17" /></button></div>
            <slot />
        </div>
    </dialog>
</template>
