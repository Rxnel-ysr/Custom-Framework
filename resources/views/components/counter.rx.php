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
    <div style="display: flex; flex-direction: row;">
        <input
            type="text"
            data-onchange="increment"
            data-attribute="count"
            name="counter-input"
            value="{{ $count }}"
            />
    </div>
</div>