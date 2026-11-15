<?php

namespace App\Filament\Pages;

use App\Models\Book;
use App\Services\ScriptureImporter as Importer;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportScripture extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|\UnitEnum|null $navigationGroup = 'Scripture';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.import-scripture';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $previewData = null;

    /** @var array<string, mixed>|list<array<string, mixed>>|null */
    public ?array $resultData = null;

    /** @var array<string, string> */
    public array $bookOptions = [];

    public function mount(Importer $importer): void
    {
        try {
            $this->bookOptions = collect($importer->availableBooks())
                ->mapWithKeys(fn (array $book) => [$book['name'] => $book['ccBoMName'] ?? $book['name']])
                ->all();
        } catch (Throwable) {
            $this->bookOptions = Book::query()->orderBy('position')->pluck('name', 'slug')->all();
        }

        $this->formSchema()->fill([
            'source' => 'remote',
            'scope' => 'chapter',
            'book' => request()->query('book', array_key_first($this->bookOptions)),
            'chapter' => (int) request()->query('chapter', 1),
            'publish' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Radio::make('source')->options(['remote' => 'Brit HaMashiach website', 'local' => 'Local JSON file'])->inline()->live()->required(),
            Radio::make('scope')->options(['chapter' => 'Single chapter', 'book' => 'Entire book'])->inline()->live()->required(),
            Select::make('book')->label('Book')->options($this->bookOptions)->searchable()->required(),
            TextInput::make('chapter')->numeric()->minValue(1)->required(),
            FileUpload::make('file')->label('Chapter JSON file')->disk('local')->directory('scripture-imports')->acceptedFileTypes(['application/json', 'text/json'])->maxSize(5120),
            Toggle::make('publish')->label('Publish after import')->default(true),
        ])->statePath('data')->columns(2);
    }

    public function preview(Importer $importer): void
    {
        $data = $this->formSchema()->getState();
        try {
            $path = $this->localPath($data);
            $this->previewData = $importer->preview((string) $data['book'], (int) $data['chapter'], $path);
            $this->resultData = null;
        } catch (Throwable $exception) {
            Notification::make()->danger()->title('Preview failed')->body($exception->getMessage())->send();
        }
    }

    public function import(Importer $importer): void
    {
        $data = $this->formSchema()->getState();
        try {
            if ($data['scope'] === 'book') {
                if ($data['source'] === 'local') {
                    throw new \RuntimeException('Entire-book imports require the verified remote book index.');
                }
                $this->resultData = $importer->importBook((string) $data['book'], (bool) $data['publish']);
            } else {
                $this->resultData = $importer->importChapter(
                    (string) $data['book'], (int) $data['chapter'], $this->localPath($data), (bool) $data['publish'],
                );
            }
            Notification::make()->success()->title('Scripture import complete')->send();
            $this->previewData = null;
        } catch (Throwable $exception) {
            Notification::make()->danger()->title('Import failed')->body($exception->getMessage())->send();
        }
    }

    /** @param array<string, mixed> $data */
    private function localPath(array $data): ?string
    {
        if (($data['source'] ?? 'remote') !== 'local') {
            return null;
        }

        $file = $data['file'] ?? null;
        if (! is_string($file) || $file === '') {
            throw new \RuntimeException('Choose a JSON file for a local import.');
        }

        return Storage::disk('local')->path($file);
    }

    private function formSchema(): Schema
    {
        return $this->getSchema('form') ?? throw new \LogicException('The import form schema is unavailable.');
    }
}
