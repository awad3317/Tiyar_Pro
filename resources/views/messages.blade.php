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
        .header-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 14px;
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
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3 px-1">
        <div class="fw-bold text-secondary">
            Total Received Messages:
            <span class="badge bg-primary rounded-pill ms-1">{{ $messages->count() }}</span>
        </div>
        <div class="text-muted small">
            Last Updated: <span class="fw-semibold">{{ now()->format('Y-m-d H:i:s') }}</span>
        </div>
    </div>

    <div class="card table-card bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 65px;">#</th>
                        <th style="width: 170px;">Sender Number</th>
                        <th style="min-width: 240px;">Message Text</th>
                        <th style="width: 185px;">Device Sent Time (sent_at)</th>
                        <th style="width: 195px;">Server Received Time (created_at)</th>
                        <th style="width: 290px;">Raw Payload (JSON)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $msg)
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
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="py-4">
                                    <h5 class="fw-bold text-secondary mb-1">No incoming messages yet</h5>
                                    <p class="text-muted small mb-0">
                                        Send a <code>POST</code> request to <code>{{ url('/webhook/receive') }}</code> and it will appear here immediately.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function copyWebhookUrl() {
        const url = document.getElementById('webhookUrl').innerText;
        navigator.clipboard.writeText(url).then(() => {
            alert('Webhook URL copied:\n' + url);
        });
    }

    const toggle = document.getElementById('autoRefreshToggle');
    let refreshTimer = null;

    if (localStorage.getItem('webhook_poc_auto_refresh') === '1') {
        toggle.checked = true;
        refreshTimer = setInterval(() => window.location.reload(), 5000);
    }

    toggle.addEventListener('change', function () {
        if (this.checked) {
            localStorage.setItem('webhook_poc_auto_refresh', '1');
            refreshTimer = setInterval(() => window.location.reload(), 5000);
        } else {
            localStorage.removeItem('webhook_poc_auto_refresh');
            if (refreshTimer) clearInterval(refreshTimer);
        }
    });
</script>
</body>
</html>
