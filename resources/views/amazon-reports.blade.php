<div class="container mt-4">

    <h2 class="mb-4">📊 Amazon Reports Tracker</h2>

    @if(count($reports) > 0)
    <table class="table table-dark table-hover">
        <thead>
            <tr>
                <th>Report ID</th>
                <th>Type</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reports as $report)
            <tr>
                <td>{{ $report['reportId'] }}</td>

                <td>{{ $report['reportType'] }}</td>

                <td>
                    @php
                        $status = $report['processingStatus'];
                        $badge = 'secondary';

                        if ($status == 'DONE') $badge = 'success';
                        elseif ($status == 'IN_PROGRESS') $badge = 'warning';
                        elseif ($status == 'IN_QUEUE') $badge = 'info';
                        elseif ($status == 'FATAL') $badge = 'danger';
                    @endphp

                    <span class="badge bg-{{ $badge }}">
                        {{ $status }}
                    </span>
                </td>

                <td>{{ $report['createdTime'] ?? '-' }}</td>

                <td>
                    @if(isset($report['reportDocumentId']))
                        <a href="/amazon/report/download/{{ $report['reportDocumentId'] }}" class="btn btn-sm btn-success">
                            Download
                        </a>
                    @else
                        <span class="text-muted">Not Ready</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @else
        <p class="text-muted">No reports found 😭</p>
    @endif

</div>