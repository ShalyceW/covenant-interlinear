<script setup lang="ts">
import PublicShell from '@/components/PublicShell.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Covenant of Christ Interlinear" />
    <PublicShell>
        <main
            class="mx-auto flex min-h-[70vh] max-w-3xl items-center px-6 py-16"
        >
            <div class="w-full border-y border-stone-300 py-14 text-center">
                <p
                    class="text-xs font-semibold tracking-[0.22em] text-amber-800 uppercase"
                >
                    Hebrew · English
                </p>
                <h1 class="mt-4 font-serif text-5xl text-stone-900">
                    Covenant Interlinear
                </h1>
                <p
                    class="mx-auto mt-5 max-w-xl font-serif text-lg leading-relaxed text-stone-600"
                >
                    No published scripture chapters are available yet. An
                    administrator can import and publish a chapter from the
                    content panel.
                </p>
                <a
                    href="/admin"
                    class="mt-8 inline-block rounded bg-stone-900 px-5 py-3 text-sm font-medium text-white hover:bg-stone-700"
                    >Open administration</a
                >
            </div>
        </main>
    </PublicShell>
</template>
