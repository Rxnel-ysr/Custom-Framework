<div data-reactive data-reactive-name="{{ $id }}" data-reactive-state='@json($currentStates)'>
    <ul>
        @foreach($lists as $name)
        <li>{{ $name }}</li>
        @endforeach
    </ul>

    <input type="text" data-oninput="update" data-delay="1000" data-attribute="temp" name="temp" value="{{$temp}}" />

    <button data-action="add" data-params='{"temp":"<?= $temp ?>"}' id="btn">Add</button>

    <pre>
    @php
        $allVariables = get_defined_vars();
        var_export($allVariables);
    @endphp
    </pre>
</div>