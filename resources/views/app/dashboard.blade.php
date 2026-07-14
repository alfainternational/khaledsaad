@extends('layouts.app', ['title' => 'لوحة العمل', 'pageTitle' => 'لوحة العمل', 'pageKicker' => ''])

@section('content')
    @include('partials.dashboard.ecommerce', ['dash' => $dash])
@endsection
