<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming Webhook Messages Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .table-card {
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }
        .code-box {
            background-color: #0f172a;
            color: #38bdf8;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.78rem;
            padding: 8px 10px;
            border-radius: 8px;
            max-height: 110px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-word;
            margin: 0;
        }
        .sender-badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
        }
        .webhook-url-box {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 6px 12px;
            font-family: monospace;
            font-size: 0.85rem;
            color: #e2e8f0;
        }
    </style>
</head>
<body class="py-4">

<div class="container">
    <div class="card table-card bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 65px;">#</th>
                        <th style="width: 170px;">Sender Number</th>
                        <th style="min-width: 240px;">Message Text</th>
                        <th style="width: 185px;">(sent_at)</th>
                        <th style="width: 195px;"> (created_at)</th>
                        <th style="width: 290px;">Payload</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($messages as $msg)
                        <tr>
                            <td class="ps-3 fw-bold text-secondary">#{{ $msg->id }}</td>
                            <td>
                                @if($msg->sender_number)
                                    <span class="badge bg-dark-subtle text-dark sender-badge px-2 py-1">
                                        {{ $msg->sender_number }}
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                @if($msg->message_text)
                                    <div class="fw-semibold text-dark" style="white-space: pre-wrap;">{{ $msg->message_text }}</div>
                                @else
                                    <span class="text-muted small fst-italic">No text</span>
                                @endif
                            </td>
                            <td>
                                @if($msg->sent_at)
                                    <span class="badge bg-info-subtle text-info-emphasis font-monospace px-2 py-1">
                                        {{ $msg->sent_at }}
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="small fw-semibold text-secondary font-monospace">
                                    {{ $msg->created_at }}
                                </div>
                            </td>
                            <td>
                                <pre class="code-box">{{ $msg->raw_payload }}</pre>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
