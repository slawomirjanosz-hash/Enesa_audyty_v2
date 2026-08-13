<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateDocumentFolders extends Command
{
    protected $signature = 'documents:migrate-folders {--dry-run : Pokaż co zostanie zrobione, bez wprowadzania zmian}';

    protected $description = 'Przenosi pliki dokumentów z folderów company_{id} na foldery z nazwą firmy (folderSlug)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $disk = Storage::disk('local');

        $documents = Document::with('company')->get();

        if ($documents->isEmpty()) {
            $this->info('Brak dokumentów w bazie do zmigrowania.');

            return self::SUCCESS;
        }

        $moved = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($documents as $doc) {
            if (! $doc->company) {
                $this->warn("Dokument #{$doc->id} ({$doc->original_filename}) nie ma przypisanej firmy — pomijam.");
                $skipped++;

                continue;
            }

            $oldPath = $doc->stored_path;
            $filename = basename($oldPath);
            $newFolder = 'documents/'.$doc->company->folderSlug();
            $newPath = $newFolder.'/'.$filename;

            if ($oldPath === $newPath) {
                $skipped++;

                continue;
            }

            if (! $disk->exists($oldPath)) {
                $this->error("Plik fizyczny nie istnieje dla dokumentu #{$doc->id}: {$oldPath} — pomijam.");
                $errors++;

                continue;
            }

            $this->line(($dryRun ? '[DRY-RUN] ' : '')."Przenoszę: {$oldPath}  →  {$newPath}");

            if (! $dryRun) {
                if (! $disk->exists($newFolder)) {
                    $disk->makeDirectory($newFolder);
                }

                $disk->move($oldPath, $newPath);
                $doc->update(['stored_path' => $newPath]);
            }

            $moved++;
        }

        $this->newLine();
        $this->info("Podsumowanie: przeniesiono {$moved}, pominięto {$skipped}, błędów {$errors}.");

        if ($dryRun) {
            $this->comment('To był dry-run — żadne pliki nie zostały fizycznie przeniesione. Uruchom bez --dry-run, aby wykonać migrację.');
        }

        return self::SUCCESS;
    }
}
