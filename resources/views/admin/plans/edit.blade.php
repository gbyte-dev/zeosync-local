@extends('admin.layout.app')

@section('title', 'Edit Plan')

@section('content')
@php $type = 'Edit Plan';  @endphp
<form method="POST" action="{{ route('admin.plans.update', $plan) }}">
@csrf
@method('PUT')

@include('admin.plans.form')

<button class="btn btn-primary mt-3">Update</button>

</form>

@endsection