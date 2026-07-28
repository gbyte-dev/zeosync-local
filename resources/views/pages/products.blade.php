<!DOCTYPE html>
<html>
<head>
    <title>Products</title>

    <!-- Bootstrap CSS -->
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">

    <style>
        body {
            background: #f3f3f3;
        }

        .product-card {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
        }

        .main-img {
            width: 100%;
            border-radius: 10px;
        }

        .thumb-img {
            width: 70px;
            margin: 5px;
            cursor: pointer;
            border-radius: 5px;
        }

        .price {
            color: #B12704;
            font-size: 24px;
            font-weight: bold;
        }

        .vendor {
            color: #007185;
        }

        .btn-cart {
            background: #FFD814;
            border: none;
        }

        .btn-buy {
            background: #FFA41C;
            border: none;
        }
    </style>
</head>

<body>

<div class="container mt-4">

<h2 class="mb-4">🛍️ Shopify Products</h2>

@foreach($products as $product)

<div class="product-card row">

    <!-- LEFT: Images -->
    <div class="col-md-4">

        <!-- Main Image -->
        <img id="mainImage{{ $product['id'] }}" 
             src="{{ data_get($product, 'images.0.src', 'https://via.placeholder.com/300') }}" 
             class="main-img">

        <!-- Thumbnails -->
        <div class="mt-2">
            @foreach(data_get($product, 'images', []) as $img)
                <img src="{{ data_get($img, 'src') }}" 
                     class="thumb-img"
                     onclick="changeImage('{{ $product['id'] }}', '{{ $img['src'] }}')">
            @endforeach
        </div>

    </div>

    <!-- RIGHT: Details -->
    <div class="col-md-8">

        <!-- Title -->
        <h4>{{ data_get($product, 'title') }}</h4>

        <!-- Vendor -->
        <p class="vendor">by {{ data_get($product, 'vendor', 'N/A') }}</p>

        <!-- Price -->
        <div class="price" id="price{{ $product['id'] }}">
            ₹{{ data_get($product, 'variants.0.price', '0') }}
        </div>

        <!-- Description -->
        <div class="mt-3">
            {!! data_get($product, 'body_html', '<p>No description</p>') !!}
        </div>

        <!-- Variant Dropdown -->
        <div class="mt-3">
            <label><strong>Select Variant:</strong></label>

            <select class="form-select w-50" 
                    onchange="changePrice('{{ $product['id'] }}', this)">
                
                @foreach(data_get($product, 'variants', []) as $variant)
                    <option value="{{ data_get($variant, 'price') }}">
                        {{ data_get($variant, 'title') }} - ₹{{ data_get($variant, 'price') }}
                    </option>
                @endforeach

            </select>
        </div>

        <!-- Buttons -->
        <div class="mt-4">
            <button class="btn btn-cart w-50 mb-2">Add to Cart 🛒</button>
            <button class="btn btn-buy w-50">Buy Now ⚡</button>
        </div>

    </div>

</div>

@endforeach

</div>

<!-- JS -->
<script>
function changeImage(id, src) {
    document.getElementById('mainImage' + id).src = src;
}

function changePrice(id, select) {
    document.getElementById('price' + id).innerText = "₹" + select.value;
}
</script>

</body>
</html>