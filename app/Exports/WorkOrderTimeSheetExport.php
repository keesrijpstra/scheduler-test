<?php

namespace App\Exports;

use App\Models\TimeEntry;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class WorkOrderTimeSheetExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $workOrder;
    protected $filters;

    public function __construct($workOrder, $filters = [])
    {
        $this->workOrder = $workOrder;
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = TimeEntry::query()
            ->where('workorder_id', $this->workOrder->id)
            ->with('user');

        // Apply filters if they exist
        if (isset($this->filters['day'])) {
            $query->whereDate('date', $this->filters['day']);
        }
        if (isset($this->filters['week'])) {
            $weekStart = Carbon::parse($this->filters['week'])->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
            $query->whereBetween('date', [$weekStart, $weekEnd]);
        }
        if (isset($this->filters['month'])) {
            $date = Carbon::createFromFormat('Y-m', $this->filters['month']);
            $query->whereMonth('date', $date->month)
                ->whereYear('date', $date->year);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Werkorder',
            'Medewerker',
            'Datum',
            'Start Tijd',
            'Eind Tijd',
            'Pauze',
            'Reistijd',
            'Totaal Uren',
            'Omschrijving'
        ];
    }

    public function map($timeEntry): array
    {
        $start = Carbon::parse($timeEntry->start_time);
        $end = Carbon::parse($timeEntry->end_time);
        $break = Carbon::parse($timeEntry->break_duration);

        $totalMinutes = $end->diffInMinutes($start) - $break->diffInMinutes(Carbon::today());
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return [
            $this->workOrder->title,
            $timeEntry->user->name,
            $timeEntry->date,
            $timeEntry->start_time,
            $timeEntry->end_time,
            $timeEntry->break_duration,
            $timeEntry->travel_time,
            sprintf('%02d:%02d', $hours, $minutes),
            $timeEntry->description
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
