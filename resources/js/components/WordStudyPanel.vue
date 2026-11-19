<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{ wordId: number | null }>();
const emit = defineEmits<{ close: [] }>();
const loading = ref(false);
const study = ref<any>(null);
const occurrencePage = ref(1);
const biblicalPage = ref(1);
const occurrenceCorpus = ref<'covenant' | 'bible'>('covenant');
const selectedGloss = ref('');
const selectedLemma = ref('');
const customGloss = ref('');
const savingGloss = ref(false);
const savingLemma = ref(false);
const tab = ref<'occurrences' | 'lexicon' | 'morphology' | 'sources'>(
    'occurrences',
);

const morphology = computed<Array<[string, string]>>(() =>
    Object.entries(study.value?.word?.morphology || {}).map(([key, value]) => [
        key,
        typeof value === 'object' ? JSON.stringify(value) : String(value),
    ]),
);

async function loadStudy(page = 1, biblePage = 1) {
    if (!props.wordId) return;
    loading.value = true;
    occurrencePage.value = page;
    biblicalPage.value = biblePage;
    const parameters = new URLSearchParams({
        page: String(page),
        bible_page: String(biblePage),
    });
    const response = await fetch(
        `/word-study/${props.wordId}?${parameters.toString()}`,
        { headers: { Accept: 'application/json' } },
    );
    study.value = response.ok ? await response.json() : null;
    selectedGloss.value =
        study.value?.word?.english_gloss ||
        study.value?.review?.gloss_options?.[0] ||
        '';
    customGloss.value = '';
    selectedLemma.value =
        study.value?.review?.selected_lemma ||
        study.value?.review?.lemma_options?.[0]?.value ||
        '';
    loading.value = false;
}

function approveLemma() {
    if (!props.wordId || !selectedLemma.value) return;
    savingLemma.value = true;
    router.patch(
        `/editor/words/${props.wordId}`,
        { field: 'lemma', value: selectedLemma.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => loadStudy(occurrencePage.value, biblicalPage.value),
            onFinish: () => {
                savingLemma.value = false;
            },
        },
    );
}

const glossValue = computed(() =>
    selectedGloss.value === '__custom'
        ? customGloss.value.trim()
        : selectedGloss.value,
);

function approveGloss() {
    if (!props.wordId || !glossValue.value) return;
    savingGloss.value = true;
    router.patch(
        `/editor/words/${props.wordId}`,
        { field: 'gloss', value: glossValue.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => loadStudy(occurrencePage.value, biblicalPage.value),
            onFinish: () => {
                savingGloss.value = false;
            },
        },
    );
}

watch(
    () => props.wordId,
    async (id) => {
        if (!id) {
            study.value = null;
            return;
        }
        loading.value = true;
        tab.value = 'occurrences';
        occurrenceCorpus.value = 'covenant';
        await loadStudy(1);
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
                                study.word.english_gloss || 'Meaning unresolved'
                            }}
                        </p>
                        <div
                            v-if="study.review && !study.word.english_gloss"
                            class="mt-4 rounded border border-red-200 bg-red-50 p-3"
                        >
                            <p class="text-xs font-semibold tracking-wide text-red-800 uppercase">
                                Suggested word gloss
                            </p>
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                                <select
                                    v-model="selectedGloss"
                                    class="min-w-0 flex-1 rounded border border-red-300 bg-white px-3 py-2 text-sm text-stone-900"
                                    aria-label="Suggested word gloss"
                                >
                                    <option
                                        v-for="option in study.review.gloss_options"
                                        :key="option"
                                        :value="option"
                                    >
                                        {{ option }}
                                    </option>
                                    <option value="__custom">Custom gloss…</option>
                                </select>
                                <button
                                    type="button"
                                    class="rounded bg-red-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
                                    :disabled="savingGloss || !glossValue"
                                    @click="approveGloss"
                                >
                                    {{ savingGloss ? 'Saving…' : 'Approve gloss' }}
                                </button>
                            </div>
                            <input
                                v-if="selectedGloss === '__custom'"
                                v-model="customGloss"
                                type="text"
                                class="mt-2 w-full rounded border border-red-300 bg-white px-3 py-2 text-sm"
                                placeholder="Enter a contextual English gloss"
                                aria-label="Custom word gloss"
                                @keyup.enter="approveGloss"
                            />
                            <p
                                v-if="!study.review.gloss_options.length"
                                class="mt-2 text-xs text-red-700"
                            >
                                No source-backed alternate was found. Choose Custom gloss to enter an editorial value.
                            </p>
                        </div>
                        <div
                            v-if="study.review && study.review.lemma_status !== 'confirmed' && study.review.lemma_options.length"
                            class="mt-3 rounded border border-amber-200 bg-amber-50 p-3"
                        >
                            <p class="text-xs font-semibold tracking-wide text-amber-900 uppercase">Suggested lemma</p>
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                                <select
                                    v-model="selectedLemma"
                                    class="min-w-0 flex-1 rounded border border-amber-300 bg-white px-3 py-2 text-sm"
                                    aria-label="Suggested lemma"
                                >
                                    <option
                                        v-for="option in study.review.lemma_options"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    class="rounded bg-amber-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
                                    :disabled="savingLemma || !selectedLemma"
                                    @click="approveLemma"
                                >
                                    {{ savingLemma ? 'Saving…' : 'Approve lemma' }}
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="study.lemma"
                            dir="rtl"
                            lang="he"
                            class="mt-4 text-sm text-stone-600"
                        >
                            <span dir="ltr" class="mr-2 font-sans">Lemma</span
                            >{{ study.lemma.hebrew }}
                        </p>
                        <p
                            v-if="study.lemma?.strongs_number"
                            class="mt-2 text-sm font-semibold text-amber-800"
                        >
                            {{ study.lemma.strongs_number }}
                        </p>
                        <p
                            v-if="study.word.morphology?.description"
                            class="mt-2 text-sm text-stone-600"
                        >
                            {{ study.word.morphology.description }}
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
                        <div
                            class="mb-5 grid grid-cols-2 rounded border border-stone-300 p-1 text-sm"
                        >
                            <button
                                class="rounded px-3 py-2"
                                :class="
                                    occurrenceCorpus === 'covenant'
                                        ? 'bg-stone-800 text-white'
                                        : 'text-stone-600'
                                "
                                @click="occurrenceCorpus = 'covenant'"
                            >
                                Covenant of Christ
                            </button>
                            <button
                                class="rounded px-3 py-2"
                                :class="
                                    occurrenceCorpus === 'bible'
                                        ? 'bg-stone-800 text-white'
                                        : 'text-stone-600'
                                "
                                @click="occurrenceCorpus = 'bible'"
                            >
                                Hebrew Bible
                            </button>
                        </div>
                        <div v-if="occurrenceCorpus === 'covenant'">
                            <h3 class="font-semibold">Covenant of Christ</h3>
                            <p class="mt-1 mb-4 text-sm text-stone-500">
                                {{ study.occurrence_count }} actual occurrences
                                in imported published scriptures ·
                                {{ study.match_type.replaceAll('_', ' ') }}
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
                                    class="mt-2 flex flex-wrap gap-x-1 font-serif text-xl"
                                >
                                    <span
                                        v-for="contextWord in occurrence.words"
                                        :key="contextWord.id"
                                        :class="
                                            contextWord.is_match
                                                ? 'rounded bg-amber-200 px-1'
                                                : ''
                                        "
                                        >{{ contextWord.hebrew }}</span
                                    >
                                </p>
                                <p
                                    class="mt-1 font-serif text-sm leading-relaxed text-stone-600"
                                >
                                    {{ occurrence.english }}
                                </p>
                            </div>
                            <div
                                v-if="study.pagination.last_page > 1"
                                class="mt-5 flex items-center justify-between text-sm"
                            >
                                <button
                                    class="rounded border border-stone-300 px-3 py-2 disabled:opacity-40"
                                    :disabled="occurrencePage <= 1"
                                    @click="loadStudy(occurrencePage - 1)"
                                >
                                    Previous
                                </button>
                                <span
                                    >Page {{ occurrencePage }} of
                                    {{ study.pagination.last_page }}</span
                                >
                                <button
                                    class="rounded border border-stone-300 px-3 py-2 disabled:opacity-40"
                                    :disabled="
                                        occurrencePage >=
                                        study.pagination.last_page
                                    "
                                    @click="loadStudy(occurrencePage + 1)"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                        <div v-else>
                            <h3 class="font-semibold">Hebrew Bible</h3>
                            <p class="mt-1 text-sm text-stone-500">
                                {{ study.biblical_reference_count }} source
                                evidence rows ·
                                {{ study.biblical_unique_location_count }}
                                unique source-provided locations
                            </p>
                            <p
                                class="mt-2 text-xs leading-relaxed text-stone-500"
                            >
                                Brit HaMashiach supplies opaque book-and-number
                                values. They are preserved exactly and are not
                                labeled as chapter or verse references unless
                                that structure is verified.
                            </p>
                            <p
                                v-if="!study.biblical_reference_count"
                                class="mt-5 text-sm text-stone-500"
                            >
                                No verified Biblical Hebrew references are
                                linked to this lexical entry.
                            </p>
                            <div
                                v-for="reference in study.biblical_references"
                                :key="reference.id"
                                class="border-b border-stone-200 py-4"
                            >
                                <p class="font-semibold">
                                    {{ reference.book || 'Book unresolved' }}
                                </p>
                                <p class="mt-1 font-mono text-sm">
                                    {{ reference.raw_reference }}
                                </p>
                                <p class="mt-1 text-xs text-stone-500">
                                    {{ reference.source }} · source item
                                    {{ reference.source_position }}
                                </p>
                                <a
                                    v-if="reference.source_url"
                                    :href="reference.source_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="mt-2 inline-block text-xs text-amber-800 underline"
                                    >View source evidence</a
                                >
                            </div>
                            <div
                                v-if="study.biblical_pagination.last_page > 1"
                                class="mt-5 flex items-center justify-between text-sm"
                            >
                                <button
                                    class="rounded border border-stone-300 px-3 py-2 disabled:opacity-40"
                                    :disabled="biblicalPage <= 1"
                                    @click="
                                        loadStudy(
                                            occurrencePage,
                                            biblicalPage - 1,
                                        )
                                    "
                                >
                                    Previous
                                </button>
                                <span
                                    >Page {{ biblicalPage }} of
                                    {{
                                        study.biblical_pagination.last_page
                                    }}</span
                                >
                                <button
                                    class="rounded border border-stone-300 px-3 py-2 disabled:opacity-40"
                                    :disabled="
                                        biblicalPage >=
                                        study.biblical_pagination.last_page
                                    "
                                    @click="
                                        loadStudy(
                                            occurrencePage,
                                            biblicalPage + 1,
                                        )
                                    "
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="tab === 'lexicon'" class="space-y-4 py-5">
                        <p v-if="study.lemma?.hebrew">
                            <strong>Headword:</strong>
                            <span dir="rtl" lang="he" class="ml-2 text-lg">{{
                                study.lemma.hebrew
                            }}</span>
                        </p>
                        <p v-if="study.lemma?.transliteration">
                            <strong>Dictionary transliteration:</strong>
                            {{ study.lemma.transliteration }}
                        </p>
                        <p v-if="study.lemma?.strongs_number">
                            <strong>Extended Strong’s:</strong>
                            {{ study.lemma.strongs_number }}
                        </p>
                        <p v-if="study.lemma?.base_strongs_number">
                            <strong>Base Strong’s:</strong>
                            {{ study.lemma.base_strongs_number }}
                        </p>
                        <p v-if="study.lemma?.source_lexical_id">
                            <strong>Source lexical ID:</strong>
                            {{ study.lemma.source_lexical_id }}
                        </p>
                        <p>
                            {{
                                study.lemma?.definition ||
                                'No verified dictionary definition has been assigned.'
                            }}
                        </p>
                        <p v-if="study.lemma?.root">
                            <strong>Root:</strong> {{ study.lemma.root }}
                        </p>
                        <div v-if="study.lemma?.alternate_definitions?.length">
                            <strong>Alternate senses:</strong>
                            <ul class="mt-2 list-disc space-y-2 pl-5 text-sm">
                                <li
                                    v-for="(sense, index) in study.lemma
                                        .alternate_definitions"
                                    :key="`${sense.source_lexical_id}-${index}`"
                                >
                                    {{ sense.gloss || sense.definition }}
                                    <span
                                        v-if="sense.source_lexical_id"
                                        class="text-stone-500"
                                        >({{ sense.source_lexical_id }})</span
                                    >
                                </li>
                            </ul>
                        </div>
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
                    <div v-else class="space-y-4 py-5 text-sm leading-relaxed">
                        <p>
                            <strong>Hebrew scripture:</strong>
                            {{
                                study.sources.hebrew_scripture ||
                                'Source URL unavailable'
                            }}
                        </p>
                        <p>
                            <strong>Covenant of Christ English:</strong>
                            {{
                                study.sources.english_context ||
                                'Passage reference unavailable'
                            }}
                        </p>
                        <p>
                            <strong>Brit HaMashiach morphology:</strong>
                            {{
                                study.sources.morphology ||
                                'No source morphology match assigned'
                            }}
                        </p>
                        <p>
                            <strong>Supporting Hebrew dictionary:</strong>
                            {{
                                study.sources.supporting_lexicon ||
                                'No verified lexical source assigned'
                            }}
                        </p>
                        <p>
                            <strong>Occurrence annotation:</strong>
                            {{
                                study.sources.annotation ||
                                'No generated or editorial annotation'
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
