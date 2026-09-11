@extends('layout.index')

@section('title', 'Laravel example')

@section('content')
    {{--@if (Route::has('login'))
    <div class="top-right links">
        @auth
            <a href="{{ url('/home') }}">Home</a>
        @else
            <a href="{{ route('login') }}">Login</a>

            @if (Route::has('register'))
                <a href="{{ route('register') }}">Register</a>
            @endif
        @endauth
    </div>
@endif--}}
    <div id="app">
        <show-info-component info="{{ $info }}"></show-info-component>
    </div>
@endsection
