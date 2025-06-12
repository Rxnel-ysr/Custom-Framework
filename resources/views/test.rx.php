@extends('parentTest')

@section('content')
@php
http_response_code(201);
$data = ['yusron', 'ronel', 'rexarion'];

@endphp
<h1>{{ $message }}</h1>
@say($message)

<ul>
    @foreach($data as $i)
    <li>{{ $i }}</li>
    @endforeach
    <form action="" method="get">
        <input type="text" name="name">
        <button>Send</button>
    </form>
    {{-- Haiz this is a comment --}}
</ul>

@reactive('Lists', ['lists' => [], 'temp'=>'ok'])

{{ $name??'' }}



@endsection

@push('scripts')
<script nonce="{{ $_nonce }}">
    console.log('Are this gonna work?');
</script>
@endpush

@push('scripts')
<script nonce="{{ $_nonce }}">
    console.log('Thiss too??');
</script>
@endpush