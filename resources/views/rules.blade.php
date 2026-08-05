@extends('layouts.app')

@section('content')
<div class="content">
    <div class="container-fluid p-4">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <h2 class="mb-4">Amazon Schema Rules</h2>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">What are Schema Rules?</h5>
                        <p class="card-text text-muted">
                            Schema rules are validation and formatting guidelines that ensure your Amazon product listings 
                            meet Amazon's requirements. These rules automatically validate your product data before submission, 
                            helping you avoid errors and improve listing quality.
                        </p>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">How Rules Work</h5>
                        <p class="card-text text-muted">
                            The rules system analyzes your product data and applies Amazon's specific requirements based on:
                        </p>
                        <ul class="text-muted">
                            <li>Product category and type</li>
                            <li>Size system and classification</li>
                            <li>Age range and gender specifications</li>
                            <li>Fulfillment availability and inventory status</li>
                            <li>Product attributes and variations</li>
                        </ul>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Rule Types</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold">Required Fields</h6>
                                <p class="text-muted small">
                                    Fields that must be present in your product data. Missing required fields will 
                                    trigger validation errors.
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold">Conditional Rules</h6>
                                <p class="text-muted small">
                                    Rules that apply based on specific product attributes. For example, certain fields 
                                    are only required for specific size systems or age ranges.
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold">Hidden Fields</h6>
                                <p class="text-muted small">
                                    Fields that should be excluded from the payload based on product characteristics. 
                                    This prevents invalid data from being sent to Amazon.
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold">Formatting Rules</h6>
                                <p class="text-muted small">
                                    Rules that ensure data is formatted correctly according to Amazon's specifications, 
                                    including data types, lengths, and value formats.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Benefits</h5>
                        <ul class="text-muted">
                            <li>Reduce listing errors and rejections from Amazon</li>
                            <li>Ensure compliance with Amazon's product data requirements</li>
                            <li>Automatically adapt to different product categories and types</li>
                            <li>Improve product listing quality and visibility</li>
                            <li>Save time with automated validation</li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Getting Started</h5>
                        <p class="card-text text-muted">
                            Rules are automatically applied when you create or update product listings through Zeosync. 
                            The system validates your data in real-time and provides feedback on any issues. 
                            To manage your product schemas and view specific rules, visit the 
                            <a href="{{ route('products.index') }}">Products</a> section.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection