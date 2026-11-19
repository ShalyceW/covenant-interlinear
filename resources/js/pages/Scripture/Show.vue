<script setup lang="ts">
import InterlinearPassage from '@/components/InterlinearPassage.vue';
import PublicShell from '@/components/PublicShell.vue';
import WordStudyPanel from '@/components/WordStudyPanel.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    book: any;
    chapter: any;
    books: any[];
    chapters: any[];
}>();
const showTransliteration = ref(false);
const showGlosses = ref(true);
const showEnglish = ref(true);
const scale = ref(1);
const selectedWord = ref<number | null>(null);
onMounted(() => {
    const requestedWord = Number(
        new URLSearchParams(window.location.search).get('word'),
    );
    if (Number.isInteger(requestedWord) && requestedWord > 0) {
        selectedWord.value = requestedWord;
    }
});
const previous = computed(
    () =>
        props.chapters[
            props.chapters.findIndex(
                (item) => item.number === props.chapter.number,
            ) - 1
        ],
);
const next = computed(
    () =>
        props.chapters[
            props.chapters.findIndex(
                (item) => item.number === props.chapter.number,
            ) + 1
        ],
);
function go(bookSlug: string, chapter = 1) {
    router.visit(`/library/${bookSlug}/${chapter}`);
}
</script>

<template>
    <Head :title="`${book.name} ${chapter.number}`" />
    <PublicShell>
        <div
            class="sticky top-0 z-20 border-b border-stone-300 bg-[#f8f5ed]/95 px-4 py-2 shadow-sm backdrop-blur print:hidden"
        >
            <div
                class="mx-auto flex max-w-[1480px] flex-wrap items-center gap-2 text-sm"
            >
                <select
                    :value="book.slug"
                    class="rounded border border-stone-300 bg-white px-2 py-1.5"
                    aria-label="Book"
                    @change="go(($event.target as HTMLSelectElement).value)"
                >
                    <option
                        v-for="item in books"
                        :key="item.slug"
                        :value="item.slug"
                    >
                        {{ item.name }}
                    </option>
                </select>
                <select
                    :value="chapter.number"
                    class="rounded border border-stone-300 bg-white px-2 py-1.5"
                    aria-label="Chapter"
                    @change="
                        go(
                            book.slug,
                            Number(($event.target as HTMLSelectElement).value),
                        )
                    "
                >
                    <option
                        v-for="item in chapters"
                        :key="item.id"
                        :value="item.number"
                    >
                        Chapter {{ item.number }}
                    </option>
                </select>
                <span class="mx-1 h-5 border-l border-stone-300" />
                <label class="control"
                    ><input v-model="showTransliteration" type="checkbox" />
                    Transliteration</label
                >
                <label class="control"
                    ><input v-model="showGlosses" type="checkbox" />
                    Glosses</label
                >
                <label class="control"
                    ><input v-model="showEnglish" type="checkbox" />
                    English</label
                >
                <div class="ml-auto flex items-center gap-1">
                    <button
                        class="tool-button"
                        aria-label="Decrease text size"
                        @click="scale = Math.max(0.8, scale - 0.1)"
                    >
                        A−
                    </button>
                    <button
                        class="tool-button"
                        aria-label="Increase text size"
                        @click="scale = Math.min(1.5, scale + 0.1)"
                    >
                        A+
                    </button>
                    <a
                        :href="`/library/${book.slug}/${chapter.number}/print`"
                        target="_blank"
                        class="tool-button"
                        >Print</a
                    >
                </div>
            </div>
        </div>
        <main
            class="mx-auto max-w-[1480px] px-4 py-8 sm:px-7"
            :style="{ '--reader-scale': `${scale}rem` }"
        >
            <header
                class="mb-7 flex items-end justify-between border-b-2 border-stone-800 pb-5"
            >
                <div>
                    <p
                        dir="rtl"
                        lang="he"
                        class="font-serif text-2xl text-stone-500"
                    >
                        {{ book.hebrew_name }}
                    </p>
                    <h1 class="mt-1 font-serif text-4xl">
                        {{ book.name }}
                        <span class="text-stone-400">{{ chapter.number }}</span>
                    </h1>
                </div>
                <p
                    class="hidden max-w-sm text-right text-xs leading-relaxed text-stone-500 sm:block"
                >
                    Click any Hebrew word to inspect its occurrences and
                    available lexical notes.
                </p>
            </header>
            <InterlinearPassage
                v-for="passage in chapter.passages"
                :key="passage.id"
                :passage="passage"
                :show-transliteration="showTransliteration"
                :show-glosses="showGlosses"
                :show-english="showEnglish"
                @select-word="selectedWord = $event"
            />
            <nav
                class="mt-8 flex justify-between border-t border-stone-300 pt-5 print:hidden"
            >
                <button
                    v-if="previous"
                    class="tool-button"
                    @click="go(book.slug, previous.number)"
                >
                    ← Chapter {{ previous.number }}</button
                ><span v-else />
                <button
                    v-if="next"
                    class="tool-button"
                    @click="go(book.slug, next.number)"
                >
                    Chapter {{ next.number }} →
                </button>
            </nav>
        </main>
        <WordStudyPanel :word-id="selectedWord" @close="selectedWord = null" />
    </PublicShell>
</template>

<style scoped>
.control {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 0.25rem;
    padding: 0.35rem 0.45rem;
    color: #57534e;
    cursor: pointer;
}
.control:hover {
    background: #eee9de;
}
.tool-button {
    border: 1px solid #d6d3d1;
    border-radius: 0.25rem;
    background: white;
    padding: 0.35rem 0.65rem;
    color: #44403c;
}
.tool-button:hover {
    background: #f5f5f4;
}
</style>
