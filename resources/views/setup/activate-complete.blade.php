<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activation Complete</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f7f8fb; color: #202124; margin: 0; }
        .page { max-width: 640px; margin: 0 auto; padding: 48px 24px; text-align: center; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 16px 40px rgba(16,24,40,.08); padding: 32px; }
        .button { appearance: none; border: none; background: #1d72f3; color: #fff; padding: 12px 24px; border-radius: 10px; font-size: 1rem; cursor: pointer; margin-top: 20px; }
        .button:hover { background: #1558d0; }
        .muted { color: #556074; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <h1>App activated successfully</h1>
            <p class="muted">Closing this window and redirecting the main tab to your dashboard.</p>
            <button class="button" onclick="handleClose()">Continue</button>
        </div>
    </div>

    <script>
        const dashboardUrl = {!! json_encode(route('dashboard', ['shop' => $shop])) !!};

        function notifyOpener() {
            if (window.opener && !window.opener.closed) {
                try {
                    window.opener.location.href = dashboardUrl;
                    window.opener.focus();
                } catch (error) {
                    console.warn('Unable to redirect opener:', error);
                    window.opener.postMessage({ type: 'shopify_activated', shop: {!! json_encode($shop) !!}, redirect_url: dashboardUrl }, '*');
                }
                setTimeout(() => {
                    try { window.close(); } catch (e) {}
                }, 200);
            } else {
                window.location.href = dashboardUrl;
            }
        }

        function handleClose() {
            notifyOpener();
        }

        window.addEventListener('DOMContentLoaded', notifyOpener);
    </script>
</body>
</html>
