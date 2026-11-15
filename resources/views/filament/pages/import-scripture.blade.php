<x-filament-panels::page>
    <form wire:submit="preview" class="space-y-6">
        {{ $this->form }}
        <div class="flex gap-3">
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">Preview import</x-filament::button>
            <x-filament::button type="button" color="success" icon="heroicon-o-arrow-down-tray" wire:click="import" wire:confirm="Import this scripture content?">Import</x-filament::button>
        </div>
    </form>

    @if ($previewData)
        <x-filament::section heading="Import preview" description="Review source content before committing it to the scripture database.">
            <div class="grid gap-5 md:grid-cols-4">
                <div><div class="text-sm text-gray-500">Book</div><div class="font-semibold">{{ $previewData['book']['ccBoMName'] ?? $previewData['book']['name'] }}</div></div>
                <div><div class="text-sm text-gray-500">Chapter</div><div class="font-semibold">{{ $previewData['chapter'] }}</div></div>
                <div><div class="text-sm text-gray-500">Passages</div><div class="font-semibold">{{ $previewData['passage_count'] }}</div></div>
                <div><div class="text-sm text-gray-500">Hebrew words</div><div class="font-semibold">{{ $previewData['word_count'] }}</div></div>
            </div>
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div><h3 class="mb-2 font-semibold">Hebrew preview</h3>@foreach ($previewData['hebrew_preview'] as $text)<p dir="rtl" lang="he" class="border-b py-2 text-xl">{{ $text }}</p>@endforeach</div>
                <div><h3 class="mb-2 font-semibold">English preview</h3>@foreach ($previewData['english_preview'] as $text)<p class="border-b py-2">{{ $text }}</p>@endforeach</div>
            </div>
            @if ($previewData['existing'])<p class="mt-5 text-sm font-medium text-warning-600">This chapter exists. Source passages will update without overwriting manual glosses or lemma links.</p>@endif
        </x-filament::section>
    @endif

    @if ($resultData)
        <x-filament::section heading="Import results">
            @php($results = array_is_list($resultData) ? $resultData : [$resultData])
            <div class="space-y-3">
                @foreach ($results as $result)
                    <div class="rounded-lg border p-4">
                        <strong>{{ $result['book'] }} {{ $result['chapter'] }}</strong>
                        @if (isset($result['error']))<p class="text-danger-600">{{ $result['error'] }}</p>
                        @else<p class="text-sm text-gray-600">{{ $result['passages_created'] }} passages created, {{ $result['passages_updated'] }} updated, {{ $result['words_created'] }} words created, {{ $result['passages_skipped'] }} manual passages preserved.</p>@endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
