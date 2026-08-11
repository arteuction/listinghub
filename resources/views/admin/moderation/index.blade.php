@extends('admin.layout')

@section('title', 'Moderation queue')

@section('content')
    <h1>Moderation queue</h1>

    @if ($errors->any())
        <div class="alert" role="alert">{{ $errors->first() }}</div>
    @endif

    <h2>Listings awaiting approval ({{ $listings->count() }})</h2>
    @if ($listings->isEmpty())
        <p>Nothing pending.</p>
    @else
        <table>
            <thead><tr><th>Title</th><th>Owner</th><th>Category</th><th></th></tr></thead>
            <tbody>
                @foreach ($listings as $listing)
                    <tr>
                        <td>{{ $listing->title }}</td>
                        <td>{{ $listing->owner?->email ?? '—' }}</td>
                        <td>{{ $listing->category?->name ?? '—' }}</td>
                        <td><a href="{{ route('admin.listings.edit', $listing) }}">Review</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Reviews awaiting approval ({{ $reviews->count() }})</h2>
    @if ($reviews->isEmpty())
        <p>Nothing pending.</p>
    @else
        <table>
            <thead><tr><th>Listing</th><th>Author</th><th>Rating</th><th>Body</th><th></th></tr></thead>
            <tbody>
                @foreach ($reviews as $review)
                    <tr>
                        <td>{{ $review->listing?->title ?? '—' }}</td>
                        <td>{{ $review->user?->email ?? '—' }}</td>
                        <td>{{ $review->rating }}/5</td>
                        <td>{{ Str::limit((string) $review->body, 120) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.moderation.reviews.decide', $review) }}" style="display:inline">
                                @csrf
                                <button type="submit" name="decision" value="approve">Approve</button>
                                <button type="submit" name="decision" value="reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Ownership claims ({{ $claims->count() }})</h2>
    @if ($claims->isEmpty())
        <p>Nothing pending.</p>
    @else
        <table>
            <thead><tr><th>Listing</th><th>Claimant</th><th>Message</th><th></th></tr></thead>
            <tbody>
                @foreach ($claims as $claim)
                    <tr>
                        <td>{{ $claim->listing?->title ?? '—' }}</td>
                        <td>{{ $claim->user?->email ?? '—' }}</td>
                        <td>{{ Str::limit((string) $claim->message, 120) }}</td>
                        <td>
                            {{-- Approving TRANSFERS the listing to the claimant. --}}
                            <form method="POST" action="{{ route('admin.moderation.claims.decide', $claim) }}" style="display:inline"
                                  onsubmit="return confirm('Approving transfers this listing to the claimant. Continue?')">
                                @csrf
                                <button type="submit" name="decision" value="approve">Approve &amp; transfer</button>
                                <button type="submit" name="decision" value="reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
