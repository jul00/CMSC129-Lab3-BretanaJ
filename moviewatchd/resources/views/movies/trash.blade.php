@extends('layouts.app')

@section('content')
<h2>Trash</h2>

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

                <!-- Restore Button -->
                <form method="POST" action="{{ route('movies.restore', $movie->id) }}" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-success btn-sm">Restore</button>
                </form>

                <!-- Permanently Delete Button -->
                <button type="button" class="btn btn-danger btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#forceDeleteModal{{ $movie->id }}">
                    Delete Permanently
                </button>
            </div>
        </div>
    </div>

    <!-- Permanently Delete Modal -->
    <div class="modal fade" id="forceDeleteModal{{ $movie->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Confirm Permanent Delete</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    Are you sure you want to <strong>permanently delete</strong> <strong>{{ $movie->title }}</strong>? This action cannot be undone.
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <form action="{{ route('movies.forceDelete', $movie->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger">
                            Yes, Delete Permanently
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    @endforeach
</div>
@endsection
