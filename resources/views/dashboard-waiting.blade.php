<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connecting Shopify</title>
</head>

<body>

    <div style="text-align:center; margin-top:100px;">
        <h2>Connecting your Shopify store...</h2>
        <p>Please complete the installation in the popup window.</p>
    </div>

    <script>
        const shop = @json($shop);
        const shopStatusUrl = @json(route('api.shop.status'));
        const dashboardUrl = @json(route('dashboard'));

        async function checkShopStatus() {
            if (!shop) {
                return;
            }

            try {
                const response = await fetch(
                    shopStatusUrl + '?shop=' + encodeURIComponent(shop), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        },
                        cache: 'no-store'
                    }
                );

                if (!response.ok) {
                    return;
                }

                const data = await response.json();

                if (data.shop_name && data.email) {
                    window.location.href =
                        dashboardUrl + '?shop=' + encodeURIComponent(shop);
                }
            } catch (error) {
                console.error('Shop status check failed:', error);
            }
        }

        checkShopStatus();

        setInterval(checkShopStatus, 2000);
    </script>

</body>

</html>