<script setup lang="ts">
import HebrewWord from '@/components/HebrewWord.vue';

defineProps<{
    passage: {
        id: number;
        reference: string;
        english_text: string;
        words: Array<{
            id: number;
            hebrew: string;
            transliteration: string | null;
            english_gloss: string | null;
        }>;
    };
    showTransliteration: boolean;
    showGlosses: boolean;
    showEnglish: boolean;
}>();

defineEmits<{ selectWord: [wordId: number] }>();
</script>

<template>
    <article
        :id="`passage-${passage.id}`"
        class="grid border-b border-stone-300/70 py-5 lg:grid-cols-[3fr_2fr] lg:gap-10"
    >
        <section>
            <p
                class="mb-3 text-[0.68rem] font-semibold tracking-[0.16em] text-stone-400 uppercase"
            >
                {{ passage.reference }}
            </p>
            <div
                dir="rtl"
                lang="he"
                class="flex flex-wrap items-start justify-start gap-x-1 gap-y-3 text-[var(--reader-scale)]"
            >
                <HebrewWord
                    v-for="word in passage.words"
                    :key="word.id"
                    :word="word"
                    :show-transliteration="showTransliteration"
                    :show-glosses="showGlosses"
                    @select="$emit('selectWord', $event)"
                />
            </div>
        </section>
        <section
            v-if="showEnglish"
            class="mt-5 border-t border-stone-200 pt-4 lg:mt-0 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8"
        >
            <p
                class="font-serif text-[calc(var(--reader-scale)*0.98)] leading-relaxed text-stone-800"
            >
                {{ passage.english_text }}
            </p>
        </section>
    </article>
</template>
