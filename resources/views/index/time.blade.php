@extends('layout.index')

@section('title', 'Time')

@push('head')
    @if (config('reverb.client.key'))
        <script>window.__REVERB_CLIENT__ = @json(config('reverb.client'));</script>
    @endif
@endpush

@section('content')
    <div id="app">
        <time-component init_data="{{ json_encode($init_data) }}"></time-component>
    </div>
@endsection
