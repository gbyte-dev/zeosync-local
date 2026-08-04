<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Amazon Connected</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            margin:0;
            background:#f6f6f7;
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
            color:#202223;
        }

        .wrapper{
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            padding:30px;
        }

        .card-box{
            width:100%;
            max-width:650px;
            background:#fff;
            border-radius:16px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
            padding:45px;
        }

        .icon{
            width:85px;
            height:85px;
            border-radius:50%;
            background:#E3FCEF;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto 25px;
        }

        .icon svg{
            width:45px;
            height:45px;
            color:#008060;
        }

        h1{
            font-size:30px;
            font-weight:700;
            margin-bottom:15px;
        }

        p{
            color:#6d7175;
            font-size:16px;
            line-height:1.7;
        }

        .instruction{
            background:#F6F6F7;
            border-left:4px solid #008060;
            border-radius:8px;
            padding:20px;
            margin:30px 0;
        }

        .instruction h5{
            font-size:17px;
            font-weight:600;
            margin-bottom:15px;
        }

        .instruction ol{
            margin:0;
            padding-left:20px;
        }

        .instruction li{
            margin-bottom:10px;
            color:#4b4f56;
        }

        .store-box{
            background:#F1F2F3;
            border-radius:8px;
            padding:14px;
            font-weight:600;
            margin-top:20px;
        }

        .btn-shopify{
            background:#008060;
            color:#fff;
            border:none;
            padding:12px 28px;
            border-radius:8px;
            font-weight:600;
            margin-top:25px;
        }

        .btn-shopify:hover{
            background:#006e52;
            color:#fff;
        }

        .footer{
            margin-top:35px;
            font-size:14px;
            color:#8c9196;
        }
    </style>

</head>

<body>

<div class="wrapper">

    <div class="card-box text-center">

        @php
            $logo = \App\Models\AdminSetting::where('option_key', 'app_logo')->value('option_value');
            $logoUrl = asset('logo/favamzsync.png');

            if (!empty($logo) && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
                $logoUrl = asset('storage/' . $logo);
            }
        @endphp
        <div style="margin-bottom: 25px;">
            <img src="{{ $logoUrl }}" alt="App Logo" style="max-height: 55px; width: auto; object-fit: contain;">
        </div>

        <div class="icon">

            <svg fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M16.707 5.293a1 1 0 010 1.414l-7.25 7.25a1 1 0 01-1.414 0L3.293 9.207a1 1 0 111.414-1.414l4.043 4.043 6.543-6.543a1 1 0 011.414 0z"
                    clip-rule="evenodd"/>
            </svg>

        </div>

        <h1>Amazon Account Connected Successfully</h1>

        <p>

            Your Amazon Seller account has been connected successfully with
            <strong>AmazonSync</strong>.

            <br><br>

            Amazon authorization is completed outside Shopify's embedded application.
            To continue using AmazonSync, simply return to your Shopify Admin and reopen the app.

        </p>

        @if(!empty($shop))
            <div class="store-box">
                Connected Store<br>
                {{ $shop }}
            </div>
        @endif

        <div class="instruction text-start">

            <h5>Next Steps</h5>

            <ol>
                <li>Go back to your Shopify Admin.</li>
                <li>Open the <strong>AmazonSync</strong> app again.</li>
                <li>Your Amazon account is now connected and ready to use.</li>
            </ol>

        </div>

        <button
            onclick="window.close();"
            class="btn btn-shopify">

            Close This Page

        </button>

        <div class="footer">

            If this window doesn't close automatically,
            simply return to your Shopify Admin and reopen AmazonSync.

        </div>

    </div>

</div>

</body>
</html>