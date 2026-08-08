<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ProjectGanttImportService
{
    public function import(Project $project, UploadedFile $file, ?string $newStartDate, User $actor): array
    {
        try {
            $sheet = Excel::toCollection(null, $file)->first();
        } catch (Throwable) {
            $this->fail('Nie udało się odczytać pliku. Użyj pliku XLSX, XLS lub CSV z eksportu harmonogramu.');
        }

        if (! $sheet || $sheet->isEmpty()) {
            $this->fail('Plik nie zawiera danych harmonogramu.');
        }

        $rows = $sheet->map(fn ($row) => collect($row)->values());
        $headerRowIndex = $rows->search(function ($row) {
            $headers = $row->map(fn ($value) => $this->normalize($value));

            return $this->findColumn($headers, ['nazwa', 'zadanie', 'nazwa zadania']) !== null
                && $this->findColumn($headers, ['data rozpoczecia', 'start', 'data startu']) !== null
                && $this->findColumn($headers, ['data zakonczenia', 'koniec', 'termin', 'data koncowa']) !== null;
        });
        if ($headerRowIndex === false) {
            $this->fail('Nie znaleziono kolumn Nazwa, Data rozpoczęcia i Data zakończenia.');
        }

        $headers = $rows[$headerRowIndex]->map(fn ($value) => $this->normalize($value));
        $columns = [
            'external_id' => $this->findColumn($headers, ['id zadania', 'identyfikator zadania', 'id']),
            'position' => $this->findColumn($headers, ['kolejnosc', 'pozycja', 'lp']),
            'title' => $this->findColumn($headers, ['nazwa', 'zadanie', 'nazwa zadania']),
            'start' => $this->findColumn($headers, ['data rozpoczecia', 'start', 'data startu']),
            'end' => $this->findColumn($headers, ['data zakonczenia', 'koniec', 'termin', 'data koncowa']),
            'progress' => $this->findColumn($headers, ['postep', 'wykonanie', 'procent wykonania']),
            'status' => $this->findColumn($headers, ['status', 'stan']),
            'priority' => $this->findColumn($headers, ['priorytet']),
            'dependency_id' => $this->findColumn($headers, ['zalezne od id', 'id poprzednika', 'poprzednik id']),
            'dependency_name' => $this->findColumn($headers, ['zalezne od', 'poprzednik', 'zadanie poprzedzajace']),
            'assignee_email' => $this->findColumn($headers, ['e mail osoby odpowiedzialnej', 'email osoby odpowiedzialnej', 'email']),
            'assignee_name' => $this->findColumn($headers, ['osoba odpowiedzialna', 'osoba', 'przypisane do']),
            'description' => $this->findColumn($headers, ['opis', 'uwagi']),
        ];

        [$parsed, $invalid] = $this->parseRows($rows, (int) $headerRowIndex, $columns);
        if ($parsed->isEmpty()) {
            $this->fail('Nie znaleziono żadnego poprawnego zadania do importu.');
        }

        $parsed = $this->resolveDependencies($parsed->sortBy('position')->values());
        $this->validateGraph($parsed);
        $parsed = $this->shiftDates($parsed, $newStartDate);

        return $this->persist($project, $parsed, $invalid, $actor);
    }

    private function parseRows(Collection $rows, int $headerRowIndex, array $columns): array
    {
        $parsed = collect();
        $invalid = 0;
        $seenExternalIds = [];

        foreach ($rows->slice($headerRowIndex + 1) as $rowIndex => $row) {
            if ($row->filter(fn ($value) => trim((string) $value) !== '')->isEmpty()) {
                continue;
            }

            $title = $this->text($this->cell($row, $columns['title']));
            $start = $this->date($this->cell($row, $columns['start']));
            $end = $this->date($this->cell($row, $columns['end']));
            if (! $title || ! $start || ! $end || $end < $start) {
                $invalid++;

                continue;
            }

            $externalId = $this->text($this->cell($row, $columns['external_id'])) ?: 'ROW-'.($rowIndex + 1);
            $externalId = Str::upper(trim($externalId));
            if (isset($seenExternalIds[$externalId])) {
                $this->fail("Identyfikator zadania {$externalId} występuje w pliku więcej niż raz.");
            }
            $seenExternalIds[$externalId] = true;

            $progress = max(0, min(100, (int) round($this->number($this->cell($row, $columns['progress'])) ?? 0)));
            $status = $this->status($this->cell($row, $columns['status']), $progress);
            if ($status === 'done') {
                $progress = 100;
            }

            $parsed->push([
                'external_id' => $externalId,
                'position' => (int) ($this->number($this->cell($row, $columns['position'])) ?? ($rowIndex + 1)),
                'title' => Str::limit($title, 255, ''),
                'start_date' => $start,
                'due_date' => $end,
                'progress' => $progress,
                'status' => $status,
                'priority' => $this->priority($this->cell($row, $columns['priority'])),
                'dependency_id' => Str::upper((string) ($this->text($this->cell($row, $columns['dependency_id'])) ?? '')),
                'dependency_name' => $this->text($this->cell($row, $columns['dependency_name'])),
                'assignee_email' => Str::lower((string) ($this->text($this->cell($row, $columns['assignee_email'])) ?? '')),
                'assignee_name' => $this->text($this->cell($row, $columns['assignee_name'])),
                'description' => $this->text($this->cell($row, $columns['description'])),
            ]);
        }

        return [$parsed, $invalid];
    }

    private function resolveDependencies(Collection $rows): Collection
    {
        $titleIds = $rows->groupBy(fn ($row) => $this->normalize($row['title']));

        return $rows->map(function (array $row) use ($titleIds) {
            if ($row['dependency_id'] === '' && $row['dependency_name']) {
                $matches = $titleIds->get($this->normalize($row['dependency_name']), collect());
                if ($matches->count() !== 1) {
                    $this->fail("Nie można jednoznacznie odnaleźć zadania zależności „{$row['dependency_name']}”.");
                }
                $row['dependency_id'] = $matches->first()['external_id'];
            }

            return $row;
        });
    }

    private function validateGraph(Collection $rows): void
    {
        $dependencies = $rows->mapWithKeys(fn (array $row) => [$row['external_id'] => $row['dependency_id']])->all();
        foreach ($rows as $row) {
            if ($row['dependency_id'] !== '' && ! array_key_exists($row['dependency_id'], $dependencies)) {
                $this->fail("Zadanie „{$row['title']}” wskazuje nieistniejącą zależność {$row['dependency_id']}.");
            }
            if ($row['dependency_id'] === $row['external_id']) {
                $this->fail("Zadanie „{$row['title']}” nie może zależeć samo od siebie.");
            }
        }

        foreach (array_keys($dependencies) as $externalId) {
            $chain = [];
            $current = $externalId;
            while ($current !== '' && isset($dependencies[$current])) {
                if (isset($chain[$current])) {
                    $this->fail('Plik zawiera zamkniętą pętlę zależności zadań.');
                }
                $chain[$current] = true;
                $current = $dependencies[$current];
            }
        }
    }

    private function shiftDates(Collection $rows, ?string $newStartDate): Collection
    {
        if (! $newStartDate) {
            return $rows;
        }

        $shiftDays = (int) Carbon::parse($rows->min('start_date'))
            ->diffInDays(Carbon::parse($newStartDate), false);

        return $rows->map(function (array $row) use ($shiftDays) {
            $row['start_date'] = Carbon::parse($row['start_date'])->addDays($shiftDays)->format('Y-m-d');
            $row['due_date'] = Carbon::parse($row['due_date'])->addDays($shiftDays)->format('Y-m-d');

            return $row;
        });
    }

    private function persist(Project $project, Collection $rows, int $invalid, User $actor): array
    {
        $eligibleUserIds = $project->members()->pluck('users.id')->push($project->manager_id)->filter()->unique();
        $staff = User::whereIn('id', $eligibleUserIds)
            ->whereHas('roles', fn ($query) => $query->whereNotIn('name', ['client_admin', 'client_user']))
            ->get();
        $staffByEmail = $staff->keyBy(fn (User $user) => Str::lower($user->email));
        $staffByName = $staff->groupBy(fn (User $user) => $this->normalize($user->name));
        $existingTasks = $project->tasks()->get()->keyBy(fn (Task $task) => $this->fingerprint(
            $task->title,
            $task->start_date?->format('Y-m-d'),
            $task->due_date?->format('Y-m-d')
        ));
        $report = ['inserted' => 0, 'duplicates' => 0, 'invalid' => $invalid, 'unassigned' => 0];
        $taskMap = [];
        $newExternalIds = [];

        DB::transaction(function () use ($rows, $project, $actor, $staffByEmail, $staffByName, $existingTasks, &$report, &$taskMap, &$newExternalIds) {
            $nextPosition = ((int) $project->tasks()->max('project_position')) + 1;
            foreach ($rows as $row) {
                $fingerprint = $this->fingerprint($row['title'], $row['start_date'], $row['due_date']);
                if ($existingTasks->has($fingerprint)) {
                    $taskMap[$row['external_id']] = $existingTasks->get($fingerprint);
                    $report['duplicates']++;

                    continue;
                }

                $assignee = $row['assignee_email'] !== '' ? $staffByEmail->get($row['assignee_email']) : null;
                if (! $assignee && $row['assignee_name']) {
                    $matches = $staffByName->get($this->normalize($row['assignee_name']), collect());
                    $assignee = $matches->count() === 1 ? $matches->first() : null;
                }
                if (! $assignee && ($row['assignee_email'] !== '' || $row['assignee_name'])) {
                    $report['unassigned']++;
                }

                $task = $project->tasks()->create([
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'assigned_to' => $assignee?->id,
                    'company_id' => $project->company_id,
                    'created_by' => $actor->id,
                    'status' => $row['status'],
                    'priority' => $row['priority'],
                    'start_date' => $row['start_date'],
                    'due_date' => $row['due_date'],
                    'progress' => $row['progress'],
                    'project_position' => $nextPosition++,
                ]);
                $taskMap[$row['external_id']] = $task;
                $newExternalIds[$row['external_id']] = true;
                $existingTasks->put($fingerprint, $task);
                $report['inserted']++;
            }

            foreach ($rows as $row) {
                if (isset($newExternalIds[$row['external_id']]) && $row['dependency_id'] !== '') {
                    $taskMap[$row['external_id']]->update([
                        'depends_on_task_id' => $taskMap[$row['dependency_id']]->id,
                    ]);
                }
            }
        });

        return $report;
    }

    private function normalize(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }

    private function findColumn(Collection $headers, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $index = $headers->search($alias, true);
            if ($index !== false) {
                return (int) $index;
            }
        }

        return null;
    }

    private function cell(Collection $row, ?int $column): mixed
    {
        return $column === null ? null : $row->get($column);
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            }
            $text = trim((string) $value);
            foreach (['d.m.Y', 'd-m-Y', 'Y-m-d', 'd/m/Y', 'Y/m/d'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $text)->format('Y-m-d');
                } catch (Throwable) {
                    // Try the next common spreadsheet date format.
                }
            }

            return Carbon::parse($text)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $text = preg_replace('/[^0-9,.-]/u', '', (string) ($value ?? ''));
        if (! $text) {
            return null;
        }
        $lastComma = strrpos($text, ',');
        $lastDot = strrpos($text, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        } elseif ($lastDot !== false) {
            $text = str_replace(',', '', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function status(mixed $value, int $progress): string
    {
        if ($progress >= 100) {
            return 'done';
        }
        $status = $this->normalize($value);
        if (Str::contains($status, ['wykon', 'zakoncz', 'done', 'gotowe'])) {
            return 'done';
        }
        if ($progress > 0 || Str::contains($status, ['w trakcie', 'realiz', 'in progress', 'active'])) {
            return 'in_progress';
        }

        return 'todo';
    }

    private function priority(mixed $value): string
    {
        $priority = $this->normalize($value);
        if (Str::contains($priority, ['wysok', 'high', 'piln'])) {
            return 'high';
        }
        if (Str::contains($priority, ['nisk', 'low'])) {
            return 'low';
        }

        return 'medium';
    }

    private function fingerprint(string $title, ?string $startDate, ?string $dueDate): string
    {
        return hash('sha256', implode('|', [$this->normalize($title), $startDate ?? '', $dueDate ?? '']));
    }

    private function fail(string $message): never
    {
        $exception = ValidationException::withMessages(['file' => $message]);
        $exception->errorBag = 'ganttImport';

        throw $exception;
    }
}
