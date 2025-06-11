@extends('parentTest')

@section('content')
@php
http_response_code(200);
$data = ['yusron', 'ronel', 'rexarion'];
$counter1 = new \App\Reactive\CounterComponent(['count' => 'alok','count2' => 'alok']);
$counter2 = new \App\Reactive\Oka(['name' => 'Ronel']);

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

{!! $counter2->render() !!}


@endsection

@push('scripts')
<script src="{{ asset('js/reactive.js') }}"></script>
<script>
    console.log('Are this gonna work?');
</script>
@endpush

@push('scripts')
<script>
    console.log('Thiss too??');
</script>
@endpush