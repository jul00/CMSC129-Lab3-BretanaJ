<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class MovieController extends Controller
{
    public function index(Request $request)
    {
        $query = Movie::with('category');

        if ($request->search) {
            $query->where('title', 'like', "%{$request->search}%")
                    ->orWhere('comment', 'like', "%{$request->search}%");
        }

        if ($request->genre) {
            $query->where('genre', $request->genre);
        }

        $movies = $query->get();

        return view('movies.index', compact('movies'));
    }

     public function create()
    {
        $categories = Category::all();
        return view('movies.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required',
            'genre' => 'nullable|string',
            'release_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string',
            'watched_at' => 'nullable|date',
            'category_id' => 'required|exists:categories,id',
            'poster' => 'nullable|image|mimes:jpg,png,jpeg,gif|max:2048'
        ]);

        if ($request->hasFile('poster')) {
            $data['poster'] = $request->file('poster')->store('posters','public');
        }

        Movie::create($data);

        return redirect()->route('movies.index')->with('success','Movie added');
    }

     public function show(Movie $movie)
    {
        return view('movies.show', compact('movie'));
    }

    public function edit(Movie $movie)
    {
        $categories = Category::all();
        return view('movies.edit', compact('movie','categories'));
    }

    public function update(Request $request, Movie $movie)
    {
        $request->validate([
            'title' => 'required',
            'rating' => 'required|numeric|min:1|max:5',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $data = $request->all();

        if ($request->hasFile('poster')) {
            // Delete old poster if it exists
            if ($movie->poster) {
                Storage::disk('public')->delete($movie->poster);
            }
            $path = $request->file('poster')->store('posters','public');
            $data['poster'] = $path;
        }

        $movie->update($data);
        return redirect()->route('movies.index')->with('success','Updated');
    }

    public function destroy(Movie $movie)
    {
        // Delete poster if it exists
        // if ($movie->poster) {
        //     Storage::disk('public')->delete($movie->poster);
        // }

        $movie->delete();
        return back();
    }

    public function trash()
    {
        $movies = Movie::onlyTrashed()->get();
        return view('movies.trash', compact('movies'));
    }

    public function restore($id)
    {
        Movie::withTrashed()->findOrFail($id)->restore();
        return back();
    }

    public function forceDelete($id)
    {
        $movie = Movie::withTrashed()->findOrFail($id);

        // Delete poster if it exists
        if ($movie->poster) {
            Storage::disk('public')->delete($movie->poster);
        }

        $movie->forceDelete();
        return back();
    }
}
