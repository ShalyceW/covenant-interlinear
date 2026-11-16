<script setup lang="ts">
defineProps<{
    word: {
        id: number;
        hebrew: string;
        transliteration: string | null;
        english_gloss: string | null;
    };
    showTransliteration: boolean;
    showGlosses: boolean;
}>();

defineEmits<{ select: [wordId: number] }>();
</script>

<template>
    <button
        type="button"
        class="word-group group inline-flex min-w-8 flex-col items-center rounded px-1.5 py-1 text-center transition hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700"
        :aria-label="`Study ${word.hebrew}`"
        @click="$emit('select', word.id)"
    >
        <span class="hebrew-word leading-none group-hover:text-amber-900">{{
            word.hebrew
        }}</span>
        <span
            v-if="showTransliteration"
            class="mt-1 text-[0.65em] text-stone-500 italic"
        >
            {{ word.transliteration || '\u00a0' }}
        </span>
        <span
            v-if="showGlosses"
            class="mt-0.5 max-w-28 text-[0.62em] leading-tight text-stone-700"
        >
            {{ word.english_gloss || '\u00a0' }}
        </span>
    </button>
</template>

<style scoped>
.word-group {
    break-inside: avoid;
    vertical-align: top;
}
.hebrew-word {
    font-family: 'Noto Serif Hebrew', 'Times New Roman', serif;
    font-size: 1.55em;
}
</style>
