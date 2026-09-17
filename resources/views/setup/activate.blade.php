@extends('layouts.activate')

@section('content')

<style>
.setup-container {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.setup-card {
    width: 100%;
    max-width: 700px;
    border: 1px solid #e5e5e5;
    border-radius: 16px;
    padding: 30px;
    background: #fff;
    text-align: center;
}

.setup-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 10px;
}

.setup-subtitle {
    color: #666;
    margin-bottom: 20px;
}

.form-control {
    border-radius: 10px;
    padding: 12px;
}


</style>

<div class="container setup-container">

    <div class="setup-card">

        <div class="setup-title">Connect Your Shopify Store</div>
        <div class="setup-subtitle">
            Enter your store details to activate the app
        </div>

        <form method="POST" action="{{ route('setup.store', ['shop' => $shopModel?->shop]) }}" id="activateForm">
            @csrf

            <!-- Shopify URL -->
            <div class="mb-3 text-start">
                <label class="mb-1">Shopify Store URL</label>
                <input type="text" name="shop_url"
                       placeholder="your-store.myshopify.com"
                       class="form-control" value="{{$shopModel?->shop}}" required readonly>
            </div>

            <!-- Shop Name -->
            <div class="mb-3 text-start">
                <label class="mb-1">Shop Name</label>
                <input type="text" name="shop_name" placeholder="My Store"
                       class="form-control" value="{{$shopModel?->shop_name}}" required >
            </div>

            <!-- Email -->
            <div class="mb-3 text-start">
                <label class="mb-1">Email</label>
                <input type="email" name="email" placeholder="owner@email.com"
                       class="form-control" value="{{$shopModel?->email}}" required>
            </div>

            <input type="hidden" name="access_token" value="{{$shopModel?->access_token}}">

            <!-- Button -->
            <button type="submit" class="btn-activate btn btn-primary float-end">
                Activate App
            </button>

        </form>

    </div>

</div>

@endsection
@push('scripts')
<script>
document.getElementById('activateForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const form = this;
    const button = form.querySelector('button[type="submit"]');

    button.disabled = true;
    button.innerText = 'Activating...';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(form)
        });

        const contentType = response.headers.get('content-type') || '';
        let data = null;

        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            throw new Error('Server returned an unexpected response format. Please try again.');
        }

        if (!response.ok || !data.success) {
            let errorMsg = data.message || 'Activation failed.';
            if (data.errors && typeof data.errors === 'object') {
                const firstErr = Object.values(data.errors)[0];
                if (Array.isArray(firstErr) && firstErr.length > 0) {
                    errorMsg = firstErr[0];
                }
            }
            throw new Error(errorMsg);
        }

        const redirectUrl = data.redirect_url || '{{ route("dashboard", ["shop" => $shopModel?->shop]) }}';

        // Notify opener if popup window
        if (window.opener && !window.opener.closed) {
            try {
                window.opener.postMessage({
                    type: 'shopify_authenticated',
                    shop: '{{ $shopModel?->shop }}',
                    redirect_url: redirectUrl
                }, '*');
            } catch (e) {}

            setTimeout(function () {
                try { window.close(); } catch (e) {}
            }, 500);
        } else {
            // Direct navigation in embedded/standalone window
            window.location.href = redirectUrl;
        }

    } catch (error) {
        console.error('Activation error:', error);
        alert(error.message || 'An error occurred during activation.');
        button.disabled = false;
        button.innerText = 'Activate App';
    }
});
</script>
@endpush