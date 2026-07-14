@extends('layouts.admin', ['title' => 'لوحة الإدارة', 'pageTitle' => 'لوحة الإدارة', 'pageKicker' => 'Dashboard'])

@section('content')
    @include('partials.dashboard.ecommerce', ['dash' => $dash])
@endsection
