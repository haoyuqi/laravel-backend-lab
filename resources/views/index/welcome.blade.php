@extends('layout.index')

@section('title', 'Laravel example')

@section('content')
    <div id="app">
        <show-info-component info="{{ $info }}"></show-info-component>
    </div>
@endsection
