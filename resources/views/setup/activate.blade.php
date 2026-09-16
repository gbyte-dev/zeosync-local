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

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Activation failed.');
        }

        // Activation successful hone ke baad window close
        window.close();

    } catch (error) {
        alert(error.message);

        button.disabled = false;
        button.innerText = 'Activate App';
    }
});
</script>
@endpush