@extends('admin.layout.app')

@section('title', 'Create Plan')

@section('content')

@php $type = 'Add Plan';  @endphp

<form method="POST" action="{{ route('admin.plans.store') }}">
@csrf

@include('admin.plans.form')

<button class="btn btn-primary mt-3">Save</button>

</form>

@endsection