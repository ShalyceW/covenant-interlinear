<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-[#f8f5ed] text-stone-900">
        <header
            class="border-b border-stone-300/80 bg-[#fbf9f3]/95 print:hidden"
        >
            <div
                class="mx-auto flex max-w-[1480px] items-center justify-between px-4 py-3 sm:px-7"
            >
                <Link href="/" class="font-serif text-lg tracking-wide"
                    >Covenant Interlinear</Link
                >
                <nav class="flex items-center gap-5 text-sm text-stone-600">
                    <Link href="/" class="hover:text-stone-950">Reader</Link>
                    <Link href="/concordance" class="hover:text-stone-950"
                        >Concordance</Link
                    >
                    <a href="/admin" class="hover:text-stone-950">Admin</a>
                </nav>
            </div>
        </header>
        <slot />
    </div>
</template>
