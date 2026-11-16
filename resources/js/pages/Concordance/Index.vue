<script setup lang="ts">
import PublicShell from '@/components/PublicShell.vue';
import WordStudyPanel from '@/components/WordStudyPanel.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps<{ query: string; results: any[] }>();
const search = ref(props.query);
const selectedWord = ref<number | null>(null);
function submit() {
    router.get(
        '/concordance',
        { q: search.value },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Hebrew Concordance" />
    <PublicShell>
        <main class="mx-auto max-w-5xl px-4 py-10 sm:px-7">
            <header class="border-b-2 border-stone-800 pb-6">
                <p
                    class="text-xs font-semibold tracking-[.2em] text-amber-800 uppercase"
                >
                    Published corpus
                </p>
                <h1 class="mt-2 font-serif text-4xl">Hebrew Concordance</h1>
                <p class="mt-3 max-w-2xl text-stone-600">
                    Search a written Hebrew form, unpointed Hebrew,
                    transliteration, English meaning, or verified Strong’s
                    number.
                </p>
            </header>
            <form class="mt-7 flex gap-2" @submit.prevent="submit">
                <input
                    v-model="search"
                    dir="auto"
                    class="min-w-0 flex-1 rounded border border-stone-400 bg-white px-4 py-3 text-lg"
                    placeholder="Search the published corpus…"
                /><button class="rounded bg-stone-900 px-5 py-3 text-white">
                    Search
                </button>
            </form>
            <p v-if="query" class="mt-5 text-sm text-stone-500">
                {{ results.length }} entries for “{{ query }}”
            </p>
            <div
                class="mt-4 divide-y divide-stone-300 border-y border-stone-300"
            >
                <button
                    v-for="entry in results"
                    :key="`${entry.word_id}-${entry.normalized}`"
                    class="grid w-full gap-3 py-5 text-left hover:bg-white/60 sm:grid-cols-[11rem_1fr_7rem] sm:items-center"
                    @click="selectedWord = entry.word_id"
                >
                    <div>
                        <p dir="rtl" lang="he" class="font-serif text-3xl">
                            {{ entry.hebrew }}
                        </p>
                        <p class="mt-1 text-sm text-stone-500 italic">
                            {{
                                entry.transliteration ||
                                'Transliteration pending'
                            }}
                        </p>
                    </div>
                    <div>
                        <p>{{ entry.definition || 'Definition unresolved' }}</p>
                        <p
                            v-if="entry.strongs_number"
                            class="mt-1 text-xs font-semibold text-amber-800"
                        >
                            {{ entry.strongs_number }}
                        </p>
                        <p class="mt-1 text-xs text-stone-500">
                            {{ entry.match_type.replaceAll('_', ' ') }}
                        </p>
                    </div>
                    <p class="text-sm text-stone-500 sm:text-right">
                        {{ entry.occurrence_count }} occurrences
                    </p>
                </button>
            </div>
            <div v-if="!query" class="py-16 text-center text-stone-500">
                Enter a term to search the indexed, published scripture.
            </div>
        </main>
        <WordStudyPanel :word-id="selectedWord" @close="selectedWord = null" />
    </PublicShell>
</template>
