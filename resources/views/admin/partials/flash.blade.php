{{-- Status and error feedback, in one place so every admin screen reports the
     outcome of a write the same way. --}}
@if (session('status'))
    <div role="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
