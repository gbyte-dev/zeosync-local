<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Redirecting...</title>
</head>

<body>

    <script nonce="{{ $cspNonce }}">
        window.top.location.href = @json($pricingUrl);
    </script>

</body>

</html>