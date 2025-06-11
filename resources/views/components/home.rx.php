<div data-reactive data-reactive-name="{{ $id }}" data-reactive-state='@json($currentStates)'>
    <h1>{{ $name }}</h1>
    <input type="text" data-onchange="sync" data-attribute="name" value="{{ $name }}">
</div>