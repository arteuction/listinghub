{{--
    Status buttons for one listing.

    The set of buttons comes from ListingTransition::availableFrom(), so the
    closed transition map decides what an operator can do — this partial never
    names a move of its own. A listing with no legal move renders nothing
    rather than a dead button.
--}}
@php($moves = \App\Enums\ListingTransition::availableFrom($listing->status))

@if ($moves !== [])
    <form method="POST" action="{{ route('admin.listings.transition', $listing) }}">
        @csrf
        @foreach ($moves as $move)
            <button type="submit" name="transition" value="{{ $move->value }}">{{ $move->adminLabel() }}</button>
        @endforeach
    </form>
@else
    <span>—</span>
@endif
