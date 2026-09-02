{{-- Day-by-day feed report for one crop cycle. Rendered by dompdf, so the CSS
     is deliberately plain: no flexbox, no grid, no web fonts. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px 26px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #222;
        }

        h1 { font-size: 17px; margin: 0 0 2px; }
        .sub { color: #666; font-size: 10px; margin-bottom: 12px; }

        .summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary td {
            border: 1px solid #d8d8d8;
            padding: 6px 8px;
            width: 25%;
        }
        .summary .label { color: #666; font-size: 9px; display: block; }
        .summary .value { font-size: 13px; font-weight: bold; }

        table.days { width: 100%; border-collapse: collapse; }
        table.days th {
            background: #f0f4f8;
            border: 1px solid #cfd8e0;
            padding: 5px 6px;
            text-align: left;
            font-size: 10px;
        }
        table.days td {
            border: 1px solid #e2e2e2;
            padding: 4px 6px;
        }
        td.num { text-align: right; }

        /* A day nobody recorded is shown, not skipped — the gaps are the point
           of the report. */
        tr.empty td { color: #aaa; }

        tfoot td {
            border: 1px solid #cfd8e0;
            background: #f0f4f8;
            font-weight: bold;
            padding: 5px 6px;
        }

        .foot {
            margin-top: 10px;
            color: #888;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <h1>{{ $tank->tank_name }} — Feed Report</h1>
    <div class="sub">
        {{ $farm->farm_name }}
        @if ($farmerName) · {{ $farmerName }} @endif
        · generated {{ $generatedAt->format('d M Y, H:i') }}
    </div>

    <table class="summary">
        <tr>
            <td>
                <span class="label">Stocked on</span>
                <span class="value">{{ $start->format('d M Y') }}</span>
            </td>
            <td>
                <span class="label">{{ $isFinished ? 'Harvested on' : 'Report up to' }}</span>
                <span class="value">{{ $end->format('d M Y') }}</span>
            </td>
            <td>
                <span class="label">Days</span>
                <span class="value">{{ count($rows) }}</span>
            </td>
            <td>
                <span class="label">Total feed used</span>
                <span class="value">{{ number_format($totalQuantity, 2) }} kg</span>
            </td>
        </tr>
    </table>

    <table class="days">
        <thead>
            <tr>
                <th style="width:52px">Day</th>
                <th style="width:96px">Date</th>
                <th style="width:70px" class="num">Meals</th>
                <th style="width:104px" class="num">Feed (kg)</th>
                <th>Entries</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="{{ $row['entries'] === 0 ? 'empty' : '' }}">
                    <td>{{ $row['day'] }}</td>
                    <td>{{ $row['date']->format('d M Y') }}</td>
                    <td class="num">{{ $row['entries'] === 0 ? '—' : $row['meals'] }}</td>
                    <td class="num">{{ $row['entries'] === 0 ? '—' : number_format($row['quantity'], 2) }}</td>
                    <td>{{ $row['entries'] === 0 ? 'No feed recorded' : $row['entries'] . ' ' . ($row['entries'] === 1 ? 'entry' : 'entries') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Total</td>
                <td class="num">{{ $totalMeals }}</td>
                <td class="num">{{ number_format($totalQuantity, 2) }}</td>
                <td>{{ $fedDays }} of {{ count($rows) }} days fed</td>
            </tr>
        </tfoot>
    </table>

    <div class="foot">
        Bestseed · every day from stocking to
        {{ $isFinished ? 'harvest' : 'today' }} is listed, including days with no
        record.
    </div>
</body>
</html>
