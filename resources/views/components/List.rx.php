<div rx:reactive rx:reactive-name="{{ $id }}" rx:state='@json($currentStates)'>
    <ul>
        @foreach($lists as $name)
        <li>{{ $name }}</li>
        @endforeach
    </ul>

    <input type="text" rx:oninput="update" rx:delay="5" rx:attribute="temp" name="temp" value="{{$temp}}" />

    <button rx:action="add" rx:params='@jparam($temp)' id="btn">Add</button>
</div>