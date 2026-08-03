<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful | ZeoSync</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f6f7fb;
            font-family: Inter, sans-serif;
        }

        .success-card {
            width: 100%;
            max-width: 540px;
            background: #fff;
            border-radius: 18px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 12px 35px rgba(0, 0, 0, .08);
        }

        .icon {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            background: #22c55e;
            color: #fff;
            font-size: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        h2 {
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #111827;
        }

        p {
            color: #6b7280;
            font-size: 16px;
            line-height: 28px;
            margin-bottom: 30px;
        }

        .btn-main {
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
        }

        .btn-outline-custom {
            border: 1px solid #d1d5db;
            color: #374151;
            background: #fff;
        }

        .btn-outline-custom:hover {
            background: #f3f4f6;
        }

        .note {
            margin-top: 20px;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>

</head>

<body>

    <div class="success-card">

        <div class="icon">
            ✓
        </div>

        <h2>Payment Successful</h2>

        <p>
            Your subscription has been activated successfully.
            Please return to your Shopify Admin and continue using ZeoSync.
        </p>

        @if(!empty($shop))

            <a href="{{ route('dashboard',['shop'=>$shop]) }}"
               target="_top"
               class="btn btn-success btn-main">
                Open ZeoSync
            </a>

        @else

            <button
                onclick="window.close()"
                class="btn btn-outline-custom btn-main">
                Close Window
            </button>

        @endif

        <div class="note">
            You can safely close this page after completing the above action.
        </div>

    </div>

</body>

</html>