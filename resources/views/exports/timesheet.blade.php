<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f5f5f5;
        }

        .header {
            margin-bottom: 30px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Werkorder: {{ $workOrder->title }}</h2>
        <p>Gegenereerd op: {{ now()->format('d-m-Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Medewerker</th>
                <th>Datum</th>
                <th>Start</th>
                <th>Eind</th>
                <th>Pauze</th>
                <th>Reistijd</th>
                <th>Totaal</th>
                <th>Omschrijving</th>
            </tr>
        </thead>
        <tbody>
            @foreach($timeEntries as $entry)
            @php
            $start = \Carbon\Carbon::parse($entry->start_time);
            $end = \Carbon\Carbon::parse($entry->end_time);
            $break = \Carbon\Carbon::parse($entry->break_duration);
            $totalMinutes = $end->diffInMinutes($start) - $break->diffInMinutes(\Carbon\Carbon::today());
            $hours = floor($totalMinutes / 60);
            $minutes = $totalMinutes % 60;
            @endphp
            <tr>
                <td>{{ $entry->user->name }}</td>
                <td>{{ $entry->date }}</td>
                <td>{{ $entry->start_time }}</td>
                <td>{{ $entry->end_time }}</td>
                <td>{{ $entry->break_duration }}</td>
                <td>{{ $entry->travel_time }}</td>
                <td>{{ sprintf('%02d:%02d', $hours, $minutes) }}</td>
                <td>{{ $entry->description }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>