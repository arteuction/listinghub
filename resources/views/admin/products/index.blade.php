@extends('admin.layout')

@section('title', 'Products — '.$listing->title)

@section('content')
    <h1>Products — {{ $listing->title }}</h1>

    <p><a class="btn" href="{{ route('admin.listings.products.create', $listing) }}">New product</a></p>

    @if ($errors->any())
        <div class="alert" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($products->isEmpty())
        <p>No products yet.</p>
    @else
        <table>
            <thead>
                <tr><th>Name</th><th>Price</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ (new \App\Support\Money($product->price_minor, $product->currency))->format() }}</td>
                        <td>{{ $product->status->value }}</td>
                        <td>
                            <a href="{{ route('admin.listings.products.edit', [$listing, $product]) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.listings.products.destroy', [$listing, $product]) }}" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
