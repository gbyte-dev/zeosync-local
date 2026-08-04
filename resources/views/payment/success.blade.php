<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful | ZeoSync</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f6f6f7;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #202223;
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .container {
            width: 100%;
            max-width: 680px;
            padding: 24px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .logo-container {
            margin-bottom: 32px;
            text-align: center;
        }

        .app-logo {
            height: 70px;
            width: auto;
            object-fit: contain;
        }

        .card {
            width: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 18px;
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.08), 0 0 0 1px #e5e7eb;
            padding: 48px;
            box-sizing: border-box;
            text-align: center;
        }

        .success-icon-wrapper {
            width: 104px;
            height: 104px;
            background-color: #f0fdf4;
            border: 1px solid #dcfce7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px auto;
            box-shadow: 0 8px 16px -4px rgba(22, 163, 74, 0.12);
        }

        .success-icon-wrapper svg {
            width: 52px;
            height: 52px;
            color: #16a34a;
        }

        h2 {
            font-size: 26px;
            font-weight: 700;
            color: #202223;
            margin: 0 0 16px 0;
            letter-spacing: -0.01em;
        }

        p.description {
            font-size: 15px;
            color: #6d7175;
            line-height: 1.6;
            margin: 0 0 36px 0;
            padding: 0 16px;
        }

        .btn-primary {
            width: 100%;
            height: 52px;
            background-color: #008060;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(0, 128, 96, 0.2);
            box-sizing: border-box;
        }

        .btn-primary:hover {
            background-color: #006e52;
            box-shadow: 0 6px 14px rgba(0, 128, 96, 0.25);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(0, 128, 96, 0.2);
        }

        .btn-secondary {
            width: 100%;
            height: 52px;
            background-color: #ffffff;
            color: #202223;
            border: 1px solid #c9cccf;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
        }

        .btn-secondary:hover {
            background-color: #f9fafb;
            border-color: #b5b7b9;
        }

        .btn-secondary:active {
            background-color: #f4f6f8;
        }

        .footer {
            margin-top: 24px;
            font-size: 13px;
            color: #6d7175;
            line-height: 1.5;
            text-align: center;
        }

        @media (max-width: 600px) {
            .card {
                padding: 32px 20px;
            }
            .success-icon-wrapper {
                width: 88px;
                height: 88px;
                margin-bottom: 24px;
            }
            .success-icon-wrapper svg {
                width: 44px;
                height: 44px;
            }
            h2 {
                font-size: 22px;
            }
            p.description {
                font-size: 14px;
                padding: 0;
            }
            .app-logo {
                height: 56px;
            }
            .logo-container {
                margin-bottom: 24px;
            }
            .container {
                padding: 16px;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="logo-container">
        @php
            $logo = AdminSetting('app_logo');
        @endphp
        @if($logo && file_exists(public_path('storage/' . $logo)))
            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="app-logo">
        @else
            <img src="{{ asset('logo/favamzsync.png') }}" alt="Logo" class="app-logo">
        @endif
    </div>

    <div class="card">

        <div class="success-icon-wrapper">
            <svg fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-7.25 7.25a1 1 0 01-1.414 0L3.293 9.207a1 1 0 111.414-1.414l4.043 4.043 6.543-6.543a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
        </div>

        <h2>Payment Successful</h2>

        <p class="description">
            Your subscription has been activated successfully.<br><br>
            Please return to your Shopify Admin and continue using ZeoSync.
        </p>

        @if(!empty($shop))
            <a href="{{ route('dashboard',['shop'=>$shop]) }}" target="_top" class="btn-primary">
                Open ZeoSync
            </a>
        @else
            <button onclick="window.close()" class="btn-secondary">
                Close Window
            </button>
        @endif

        <div class="footer">
            You can safely close this page after completing the above action.
        </div>

    </div>

</div>

</body>
</html>