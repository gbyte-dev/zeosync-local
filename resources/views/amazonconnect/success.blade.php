 @if(empty($shop))
    @php  abort(404); @endphp
@endif
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amazon Connected</title>
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">

    <style nonce="{{ $cspNonce }}">
        :root {
            --bg: #f5f7f7;
            --panel: #ffffff;
            --panel-soft: #f6f7f7;
            --border: #e4e8eb;
            --text: #202223;
            --muted: #6d7175;
            --success: #008060;
            --success-deep: #006e52;
            --success-soft: #e9f8f3;
            --shadow: rgba(31, 39, 44, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(180deg, #f5f7f7 0%, #eef3f3 100%);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--text);
        }

        .wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 18px;
        }

        .card-box {
            width: 100%;
            max-width: 680px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 18px 42px var(--shadow);
            padding: 32px 28px 24px;
            text-align: center;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
        }

        .brand img {
            max-height: 52px;
            width: auto;
            object-fit: contain;
        }

        .icon-wrap {
            width: 86px;
            height: 86px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--success) 0%, var(--success-deep) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 24px rgba(0, 128, 96, 0.22);
        }

        .icon-wrap svg {
            width: 42px;
            height: 42px;
            color: #ffffff;
        }

        h5 {
            margin: 0 0 12px;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.25;
        }

        .lead {
            margin: 0 auto 18px;
            max-width: 560px;
            color: var(--muted);
            font-size: 0.98rem;
            line-height: 1.7;
            white-space: normal;
        }

        .store-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 220px;
            padding: 12px 16px;
            margin: 0 auto 22px;
            border-radius: 12px;
            background: var(--success-soft);
            border: 1px solid rgba(0, 128, 96, 0.18);
            color: var(--success-deep);
            font-size: 0.92rem;
            font-weight: 700;
            text-align: center;
        }

        .store-box .label {
            opacity: 0.8;
            font-weight: 600;
        }

        .instruction {
            margin-top: 8px;
            background: var(--panel-soft);
            border: 1px solid var(--border);
            border-left: 4px solid var(--success);
            border-radius: 12px;
            padding: 20px 18px;
            text-align: left;
        }

        .instruction h6 {
            margin: 0 0 12px;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text);
        }

        .instruction ol {
            margin: 0;
            padding-left: 20px;
        }

        .instruction li {
            margin-bottom: 8px;
            color: var(--muted);
            line-height: 1.6;
        }

        .action-row {
            margin-top: 22px;
            display: flex;
            justify-content: center;
        }

        .btn-shopify {
            min-width: 180px;
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--success) 0%, var(--success-deep) 100%);
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.01em;
            box-shadow: 0 10px 18px rgba(0, 128, 96, 0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-shopify:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 20px rgba(0, 128, 96, 0.24);
        }

        .footer {
            margin-top: 18px;
            font-size: 0.88rem;
            line-height: 1.6;
            color: var(--muted);
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="card-box">
            @php
                $logo = \App\Models\AdminSetting::where('option_key', 'app_logo')->value('option_value');
                $logoUrl = asset('logo/favamzsync.png');

                if (!empty($logo) && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
                    $logoUrl = asset('storage/' . $logo);
                }
            @endphp

            <div class="brand">
                <img src="{{ $logoUrl }}" alt="App Logo">
            </div>

            <div class="icon-wrap" aria-label="Success">
                <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-7.25 7.25a1 1 0 01-1.414 0L3.293 9.207a1 1 0 111.414-1.414l4.043 4.043 6.543-6.543a1 1 0 011.414 0z"
                        clip-rule="evenodd"/>
                </svg>
            </div>

            <h5>Amazon account connected</h5>

            <p class="lead">
                Your Amazon Seller account is now connected to <strong>Zeosync</strong>.
                Amazon authorization is completed outside Shopify, so once you return to your Shopify Admin,
                you can reopen the app and continue using it normally.
            </p>

            @if(!empty($shop))
                <div class="store-box">
                    <span class="label">Connected Store</span>
                    <span>{{ $shop }}</span>
                </div>
            @endif

            <div class="instruction">
                <h6>Next steps</h6>
                <ol>
                    <li>Return to your Shopify Admin.</li>
                    <li>Open the <strong>Zeosync</strong> app again.</li>
                    <li>Your Amazon connection is ready to use.</li>
                </ol>
            </div>

            <div class="action-row">
                <button onclick="window.close();" class="btn-shopify">
                    Close this page
                </button>
            </div>

            <div class="footer">
                If this window does not close automatically, return to your Shopify Admin and reopen Zeosync.
            </div>
        </div>
    </div>
</body>
</html>

<script nonce="{{ $cspNonce }}">
    (function(){
        try {
            var shop = @json($shop ?? null);
            var payload = { type: 'amazon_connected', shop: shop };
            if (window.opener && !window.opener.closed) {
                try {
                    window.opener.postMessage(payload, window.location.origin);
                } catch (e) {
                    // ignore
                }
            }
        } catch (e) {}

        setTimeout(function(){
            try { window.close(); } catch(e) {}
        }, 2000);
    })();
</script>
