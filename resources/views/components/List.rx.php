<div rx:reactive rx:reactive-name="{{ $id }}" rx:state='@json($currentStates);'>
    <ul>
        @foreach($lists as $name):
        <li>{{ $name }}</li>
        @endforeach
    </ul>

    @if(strlen($temp) > 10):
    <h1>Sure it long</h1>
    @elseif(strlen($temp) > 5):
    <h1>Kinda long</h1>
    @endif

    @reactive('Oka', ['home' => true]);


    <input type="text" rx:oninput="update" rx:delay="1" rx:attribute="temp" name="temp" value="{{$temp}}" />

    <button rx:action="add" rx:params='@jparam($temp);' id="btn">Add</button>
</div>