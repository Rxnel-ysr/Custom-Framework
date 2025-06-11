<div data-reactive data-reactive-name="{{ $id }}" data-reactive-state='@json($currentStates)'>
    <h2>Count: {{ $count }}</h2>
    @php
    $success = strlen($count) > 8;
    @endphp
    @if($success)
    <h1>Passed!</h1>
    @else
    <h1>Nope</h1>
    @endif
    <h1>other: {{ $count2 }}</h1>
    <div style="display: flex; flex-direction: row;">
        <input
            type="text"
            data-onchange="increment"
            data-attribute="count"
            id="count-1"
            value="{{ $count }}" />
            
            <input
            type="text"
            data-onchange="decrement"
            data-attribute="count"
            id="count-2"
            value="{{ $count2 }}" />
    </div>
</div>