@extends('layouts.app')

@section('content')
<div class="ms-5 mt-4">

    <h2>Watched Movies</h2>

    <form method="GET" class="mb-3">
        <input name="search" placeholder="Search" class="form-control mb-2 w-50">
        <button class="btn btn-primary">Search</button>
    </form>

    <a href="{{ route('movies.create') }}" class="btn btn-success mb-3">Add Movie</a>

    <div class="row">
        @foreach($movies as $movie)
        <div class="col-md-4 mb-3">
            <div class="card shadow">

            @if($movie->poster)
            <img src="{{ asset('storage/'.$movie->poster) }}" class="card-img-top" style="height:250px;object-fit:cover;">
            @endif

                <div class="card-body">
                    <h5>{{ $movie->title }}</h5>
                    <p>⭐ {{ $movie->rating }}</p>

                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#movieModal{{ $movie->id }}">
                    View
                    </button>
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-danger btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteModal{{ $movie->id }}">
                        Move to Trash
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="movieModal{{ $movie->id }}">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>{{ $movie->title }}</h5>
                    </div>
                    <div class="modal-body">
                        <p>{{ $movie->comment }}</p>
                        <p>Genre: {{ $movie->genre }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteModal{{ $movie->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        Are you sure you want to move <strong>{{ $movie->title }}</strong> to trash?
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <form action="{{ route('movies.destroy', $movie->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger">
                                Yes, Trash
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        @endforeach
    </div>
</div>
