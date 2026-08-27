<?php

namespace App\Exports;

use App\Models\Audit;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectGanttExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    private Collection $tasks;

    private array $externalIds;

    public function __construct(Project|Audit $project)
    {
        $this->tasks = $project->tasks()
            ->with(['assignedUser', 'dependency'])
            ->orderBy('project_position')
            ->orderBy('id')
            ->get();

        $this->externalIds = $this->tasks
            ->values()
            ->mapWithKeys(fn (Task $task, int $index) => [$task->id => sprintf('T%03d', $index + 1)])
            ->all();
    }

    public function headings(): array
    {
        return [[
            'ID zadania',
            'Kolejność',
            'Nazwa',
            'Typ pozycji',
            'Data rozpoczęcia',
            'Data zakończenia',
            'Czas trwania (dni)',
            'Postęp (%)',
            'Status',
            'Priorytet',
            'Zależne od ID',
            'Zależne od',
            'E-mail osoby odpowiedzialnej',
            'Osoba odpowiedzialna',
            'Opis',
        ]];
    }

    public function array(): array
    {
        $statusLabels = ['todo' => 'Do zrobienia', 'in_progress' => 'W trakcie', 'done' => 'Wykonane'];
        $priorityLabels = ['low' => 'Niski', 'medium' => 'Średni', 'high' => 'Wysoki'];

        return $this->tasks->values()->map(function (Task $task, int $index) use ($statusLabels, $priorityLabels) {
            $duration = $task->start_date && $task->due_date
                ? $task->start_date->diffInDays($task->due_date) + 1
                : null;

            return [
                $this->externalIds[$task->id],
                $index + 1,
                $task->title,
                $task->is_milestone ? 'Kamień milowy' : 'Zadanie',
                $task->start_date?->format('Y-m-d'),
                $task->due_date?->format('Y-m-d'),
                $duration,
                $task->progress,
                $statusLabels[$task->status] ?? $task->status,
                $priorityLabels[$task->priority] ?? $task->priority,
                $task->depends_on_task_id ? ($this->externalIds[$task->depends_on_task_id] ?? '') : '',
                $task->dependency?->title,
                $task->assignedUser?->email,
                $task->assignedUser?->name,
                $task->description,
            ];
        })->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        $sheet->getStyle('A1:O1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE8F1EB');

        return [];
    }
}
