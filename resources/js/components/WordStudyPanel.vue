<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{ wordId: number | null }>();
const emit = defineEmits<{ close: [] }>();
const loading = ref(false);
const study = ref<any>(null);
const tab = ref<'occurrences' | 'lexicon' | 'morphology' | 'sources'>(
    'occurrences',
);

const morphology = computed(() =>
    Object.entries(study.value?.word?.morphology || {}),
);

watch(
    () => props.wordId,
    async (id) => {
        if (!id) {
            study.value = null;
            return;
        }
        loading.value = true;
        tab.value = 'occurrences';
        const response = await fetch(`/word-study/${id}`, {
            headers: { Accept: 'application/json' },
        });
        study.value = response.ok ? await response.json() : null;
        loading.value = false;
    },
);

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && props.wordId) emit('close');
}
onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Transition name="drawer">
        <div
            v-if="wordId"
            class="fixed inset-0 z-50 flex justify-end"
            role="dialog"
            aria-modal="true"
        >
            <button
                class="absolute inset-0 bg-stone-950/25"
                aria-label="Close word study"
                @click="$emit('close')"
            />
            <aside
                class="relative h-full w-full overflow-y-auto bg-[#fbf9f3] shadow-2xl sm:max-w-xl"
            >
                <div
                    class="sticky top-0 z-10 flex items-center justify-between border-b border-stone-300 bg-[#fbf9f3]/95 px-5 py-4"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-stone-500 uppercase"
                    >
                        Word study
                    </p>
                    <button
                        class="rounded px-3 py-1 text-2xl text-stone-500 hover:bg-stone-200"
                        @click="$emit('close')"
                    >
                        ×
                    </button>
                </div>
                <div v-if="loading" class="p-8 text-stone-500">
                    Loading word study…
                </div>
                <div v-else-if="study" class="p-5 sm:p-7">
                    <div class="border-b border-stone-300 pb-6">
                        <p dir="rtl" lang="he" class="font-serif text-5xl">
                            {{ study.word.hebrew }}
                        </p>
                        <p class="mt-2 text-stone-500 italic">
                            {{
                                study.word.transliteration ||
                                study.lemma?.transliteration ||
                                'Transliteration unresolved'
                            }}
                        </p>
                        <p class="mt-3 text-lg">
                            {{
                                study.word.english_gloss ||
                                study.lemma?.definition ||
                                'Meaning unresolved'
                            }}
                        </p>
                        <p
                            v-if="study.lemma?.strongs_number"
                            class="mt-2 text-sm font-semibold text-amber-800"
                        >
                            {{ study.lemma.strongs_number }}
                        </p>
                    </div>
                    <div
                        class="mt-5 flex gap-1 overflow-x-auto border-b border-stone-300 text-sm"
                    >
                        <button
                            v-for="name in [
                                'occurrences',
                                'lexicon',
                                'morphology',
                                'sources',
                            ]"
                            :key="name"
                            class="border-b-2 px-3 py-2 capitalize"
                            :class="
                                tab === name
                                    ? 'border-amber-800 text-stone-950'
                                    : 'border-transparent text-stone-500'
                            "
                            @click="tab = name as any"
                        >
                            {{ name }}
                        </button>
                    </div>
                    <div v-if="tab === 'occurrences'" class="py-5">
                        <p class="mb-4 text-sm text-stone-500">
                            {{ study.occurrence_count }} occurrences in the
                            published corpus
                        </p>
                        <div
                            v-for="occurrence in study.occurrences"
                            :key="occurrence.id"
                            class="border-b border-stone-200 py-4"
                        >
                            <Link
                                :href="`/library/${occurrence.book_slug}/${occurrence.chapter}#passage-${occurrence.passage_id}`"
                                class="text-sm font-semibold text-amber-800"
                                >{{ occurrence.reference }}</Link
                            >
                            <p
                                dir="rtl"
                                lang="he"
                                class="mt-2 font-serif text-xl"
                            >
                                {{ occurrence.hebrew }}
                            </p>
                            <p
                                class="mt-1 font-serif text-sm leading-relaxed text-stone-600"
                            >
                                {{ occurrence.english }}
                            </p>
                        </div>
                    </div>
                    <div v-else-if="tab === 'lexicon'" class="space-y-4 py-5">
                        <p>
                            {{
                                study.lemma?.definition ||
                                'No verified dictionary definition has been assigned.'
                            }}
                        </p>
                        <p v-if="study.lemma?.root">
                            <strong>Root:</strong> {{ study.lemma.root }}
                        </p>
                    </div>
                    <div v-else-if="tab === 'morphology'" class="py-5">
                        <dl
                            v-if="morphology.length"
                            class="grid grid-cols-2 gap-3 text-sm"
                        >
                            <template
                                v-for="[key, value] in morphology"
                                :key="key"
                            >
                                <dt class="text-stone-500 capitalize">
                                    {{ key.replaceAll('_', ' ') }}
                                </dt>
                                <dd>{{ value }}</dd>
                            </template>
                        </dl>
                        <p v-else class="text-stone-500">
                            Morphology has not been resolved.
                        </p>
                    </div>
                    <div v-else class="py-5 text-sm leading-relaxed">
                        <p>
                            {{
                                study.lemma?.source_reference ||
                                'No lexical source has been recorded.'
                            }}
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </Transition>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 0.18s ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}
</style>
