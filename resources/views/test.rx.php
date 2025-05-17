@extends('parentTest')

@section('content')
<?php
$data = ['yusron', 'ronel', 'rexarion'];
?>
<h1>{{ $message }}</h1>
<ul>
    @foreach($data as $i)
    <li>{{ $i }}</li>
    @endforeach
    <form action="" method="get">
        <input type="text" name="name">
        <button>Send</button>
    </form>
</ul>
@endsection

@push('scripts')
<script>
    console.log('Are this gonna work?');
</script>
@endpush

@push('scripts')
<script>
    console.log('Thiss too??');
</script>
@endpush