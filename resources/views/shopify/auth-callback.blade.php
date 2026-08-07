<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopify Authorization Complete</title>
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
            <h1>Shopify authorization complete</h1>
            <p class="muted">The Shopify authorization flow has finished. This window will close automatically or redirect you back to the app.</p>
            <button class="button" onclick="handleClose()">Continue</button>
        </div>
    </div>

    <script>
        (function() {
            const payload = {
                type: 'shopify_authenticated',
                shop: {!! json_encode($shop) !!},
                redirect_url: {!! json_encode($redirectUrl) !!}
            };

            function notifyOpener() {
                if (window.opener && !window.opener.closed) {
                    try {
                        window.opener.postMessage(payload, window.location.origin);
                    } catch (e) {
                        // ignore cross-origin or other issues
                    }
                    try { window.close(); } catch (e) {}
                } else {
                    window.top.location.href = payload.redirect_url;
                }
            }

            function handleClose() {
                notifyOpener();
            }

            window.handleClose = handleClose;

            // Notify opener immediately if present.
            notifyOpener();

            setTimeout(function() {
                try { window.close(); } catch (e) {}
            }, 2000);
        })();
    </script>
</body>
</html>
