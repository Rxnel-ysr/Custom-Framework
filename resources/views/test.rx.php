@extends('parentTest');

@section('content'):
<h1>{{ $message ?? '' }}</h1>
@say('HIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIIi');

<ul>
    @foreach([] as $i):
    <li>{{ $i }}</li>
    @endforeach
    {{-- Haiz this is a comment --}}
</ul>
<form action="" method="get">
    <input type="text" name="name" value="{{ request()->query('name') }}">
    <button>Send</button>
</form>

@reactive('Lists', ['lists' => [], 'temp'=>'ok']);

{{ $name??'' }}

@endsection

@push('scripts'):
<script nonce="{{ $_nonce }}">
    console.log('Are this gonna work?');
</script>
@endpush

@push('scripts'):
<script nonce="{{ $_nonce }}">
    console.log('Thiss too??');
</script>
@endpush