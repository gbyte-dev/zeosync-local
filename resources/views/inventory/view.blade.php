@extends('layouts.app')

@section('content')

<style>
.product-page {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:40px;
}
@media(max-width:768px){
    .product-page { grid-template-columns:1fr; }
}

/* Images */
.main-img { width:100%; border-radius:16px; background:#f6f6f7; padding:10px; }
.thumbnail-gallery { display:flex; gap:10px; margin-top:10px; }
.thumbnail-item { border:2px solid transparent; border-radius:10px; cursor:pointer; }
.thumbnail-item.active { border-color:black; }

/* Options */
.option-box {
    border:1px solid #ddd;
    padding:10px 14px;
    border-radius:10px;
    cursor:pointer;
}
.option-box.selected { border:2px solid black; }

/* Price */
.price { font-size:28px; font-weight:700; }

/* Inventory */
.inventory-box {
    background:#f6f6f7;
    padding:12px;
    border-radius:10px;
    margin-top:15px;
}

/* Buttons */
.action-buttons { display:flex; gap:10px; margin-top:20px; }
.btn-primary { flex:1; background:black; color:white; padding:14px; border-radius:12px; }
.btn-secondary { flex:1; background:#eee; padding:14px; border-radius:12px; }

/* FULL INFO SECTION */
.info-section {
    margin-top:40px;
    border:1px solid #e5e5e5;
    border-radius:12px;
    padding:20px;
}

.info-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

.info-item strong {
    font-size:13px;
    color:#666;
}
.info-item span {
    font-size:15px;
    font-weight:500;
}

.full {
    grid-column:1 / -1;
}
</style>

<div class="container py-4">

<!-- TOP -->
<div class="product-page">

    <!-- LEFT -->
    <div>
        <img id="mainImage"
             src="{{ $product['variants'][0]['image_src'] ?? $product['images'][0]['src'] ?? '' }}"
             class="main-img">

        <div class="thumbnail-gallery">
            @foreach($product['images'] as $i => $img)
            <div class="thumbnail-item {{ $i==0?'active':'' }}">
                <img src="{{ $img['src'] }}" width="60"
                     onclick="changeImage(this)">
            </div>
            @endforeach
        </div>
    </div>

    <!-- RIGHT -->
    <div>

        <h2>{{ $product['title'] }}</h2>

        <div class="text-muted mb-2">
            {{ $product['vendor'] }} • {{ $product['product_type'] }}
        </div>

        <div class="price" id="price">
            ₹{{ $product['variants'][0]['price'] ?? 0 }}
        </div>

        <!-- INVENTORY -->
        <div class="inventory-box">
            Available: <strong id="availableQty">0</strong><br>
            Committed: <strong id="committedQty">0</strong><br>
            On Hand: <strong id="onHandQty">0</strong>
        </div>

        <!-- COLOR -->
        <div class="mt-3">
            <strong>Color</strong>
            <div class="d-flex gap-2 mt-2 flex-wrap">
                @foreach(array_unique(array_column($product['variants'], 'option1')) as $color)
                <div class="option-box {{ $loop->first?'selected':'' }}"
                     onclick="selectColor(this,'{{ $color }}')">
                    {{ $color }}
                </div>
                @endforeach
            </div>
        </div>

        <!-- SIZE -->
        <div class="mt-3">
            <strong>Size</strong>
            <div class="d-flex gap-2 mt-2 flex-wrap">
                @foreach(array_unique(array_column($product['variants'], 'option2')) as $size)
                <div class="option-box"
                     onclick="selectSize(this,'{{ $size }}')">
                    {{ $size }}
                </div>
                @endforeach
            </div>
        </div>

    </div>
</div>

<!-- 🔥 ONE SINGLE FULL INFO BLOCK -->
<div class="info-section">

    <h4>Product Information</h4>

    <div class="info-grid">

        <div class="info-item">
            <strong>Brand</strong>
            <span>{{ $product['vendor'] }}</span>
        </div>

        <div class="info-item">
            <strong>Category</strong>
            <span>{{ $product['product_type'] }}</span>
        </div>

        <!-- DESCRIPTION -->
        <div class="info-item full">
            <strong>Description</strong>
            <div>{!! $product['body_html'] !!}</div>
        </div>

        <!-- FEATURES -->
        @if(!empty($product['metafields']['features']))
        <div class="info-item full">
            <strong>Features</strong>
            <ul>
                @foreach(explode('|', $product['metafields']['features']) as $f)
                <li>✔ {{ $f }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- SIZE CHART -->
        @if(!empty($product['metafields']['size_chart']))
        <div class="info-item full">
            <strong>Size Chart</strong>
            <table class="table table-bordered">
                {!! $product['metafields']['size_chart'] !!}
            </table>
        </div>
        @endif

        <!-- OTHER META -->
        @if(!empty($product['metafields']))
            @foreach($product['metafields'] as $key => $value)
                @if(!in_array($key,['features','size_chart']))
                <div class="info-item">
                    <strong>{{ ucfirst($key) }}</strong>
                    <span>{{ $value }}</span>
                </div>
                @endif
            @endforeach
        @endif

    </div>
</div>

</div>

<script>
let selectedColor='', selectedSize='';
const variants = @json($product['variants']);

function changeImage(el){
    document.getElementById('mainImage').src = el.src;
}

function selectColor(el,color){
    selectedColor=color;
    document.querySelectorAll('.option-box').forEach(e=>e.classList.remove('selected'));
    el.classList.add('selected');
    updateVariant();
}

function selectSize(el,size){
    selectedSize=size;
    document.querySelectorAll('.option-box').forEach(e=>e.classList.remove('selected'));
    el.classList.add('selected');
    updateVariant();
}

function updateVariant(){
    let v=variants.find(v=>v.option1===selectedColor && v.option2===selectedSize);
    if(v){
        document.getElementById('price').innerText='₹'+v.price;
        if(v.image_src) document.getElementById('mainImage').src=v.image_src;

        document.getElementById('availableQty').innerText=v.available||0;
        document.getElementById('committedQty').innerText=v.committed||0;
        document.getElementById('onHandQty').innerText=v.on_hand||0;
    }
}

document.addEventListener('DOMContentLoaded',()=>{
    selectedColor=variants[0]?.option1;
    selectedSize=variants[0]?.option2;
    updateVariant();
});
</script>

@endsection