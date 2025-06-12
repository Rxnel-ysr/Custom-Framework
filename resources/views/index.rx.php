@extends('layout.app')

@section('content')
<main class="container mt-5 pt-5">
    <h2 class="text-center">Welcome to Native-PHP &COPY;</h2>
    <p class="text-center">Congrats to you for successfully running your project.</p>

    <div class="text-center mt-4">

        <img id="rxnel_gif" loading="lazy" src="{{ asset('media/RXNEL.gif') }}" alt="Rxnel's Animated Logo" class="img-thumbnail img-fluid">

    </div>
</main>
@endsection

@push('styles')
<style nonce="{{ $_nonce }}">
    #rxnel_gif {
        border-radius: 15px;
        overflow: hidden;
        max-height: 450px;
    }
</style>
@endpush