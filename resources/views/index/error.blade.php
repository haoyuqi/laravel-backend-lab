@extends('layout.index')

@section('title', 'Error')

@section('content')
    <div id="app">
        @if($errors->any())
            <show-info-component info="{{ $errors->first() }}"></show-info-component>
        @else
            <show-info-component info="{{ $info }}"></show-info-component>
        @endif
    </div>
@endsection
