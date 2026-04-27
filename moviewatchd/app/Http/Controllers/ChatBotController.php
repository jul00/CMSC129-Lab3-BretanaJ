<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Movie;

class ChatBotController extends Controller
{
    public function chat(Request $request)
    {
        try {
            $message = $request->message;
            $history = session('chat_history', []);
            $movies = Movie::all();
            $moviesData = $movies->map(function ($m) {
                return [
                    'id' => $m->id,
                    'title' => $m->title,
                    'rating' => $m->rating,
                    'comment' => $m->comment
                ];
            });

            $history[] = [
                'role' => 'user',
                'content' => $message
            ];

            $history = array_slice($history, -10);

            if (!$message) {
                return response()->json([
                    'reply' => 'Please enter a message.'
                ]);
            }

            if (trim(strtolower($message)) === 'yes' && session('pending_delete')) {
                $title = session('pending_delete');
                session()->forget('pending_delete');

                $movie = Movie::where('title', $title)->first();

                if ($movie) {
                    $movie->delete();

                    return response()->json([
                        'reply' => "🗑️ Movie '$title' deleted successfully!",
                        'refresh' => true
                    ]);
                }

                return response()->json([
                    'reply' => "❌ Movie not found.",
                ]);
            }

            $response = Http::post(
                'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash-lite:generateContent?key=' . env('GEMINI_API_KEY'),
                [
                    "contents" => [
                        [
                            "parts" => [
                                [
                                    "text" =>
                                    "You are a movie assistant inside a Laravel app called MovieWatchd.
                                    Use ONLY the movie data provided below.

                                    Conversation History:
                                    " . json_encode($history) . "

                                    MOVIES DATA:
                                    " . json_encode($moviesData) . "

                                    USER MESSAGE:
                                    " . $message . "

                                    Return movie ID instead of title whenever possible.

                                    If user asks to delete, update, or find movies, respond using ONLY this dataset.

                                    Respond ONLY in JSON format like this:

                                    {
                                    \"action\": \"create | update | delete | read | none\",
                                    \"id\": number,
                                    \"title\": \"movie title\" (optional for display only),
                                    \"rating\": number,
                                    \"comment\": \"text\"
                                    }

                                    If it's just a question, use action = \"read\".
                                    If unclear, use action = \"none\".

                                    "
                                ]
                            ]
                        ]
                    ]
                ]
            );

            if ($response->status() == 503) {
                return response()->json([
                    'reply' => "🤖 AI is busy right now. Please try again."
                ], 503);
            }

            if ($response->status() == 429) {
                return response()->json([
                    'reply' => "🚫 AI quota reached. Please try again later or upgrade API plan."
                ], 429);
}

            if (!$response->successful()) {
                return response()->json([
                    'reply' => 'AI Error: ' . $response->body()
                ], 500);
            }

            $data = $response->json();

            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    return response()->json([
                        'reply' => '🤖 AI did not return a valid response.'
                    ]);
                }

            $rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $cleanText = trim(preg_replace('/```json|```/', '', $rawText));

            $intent = json_decode($cleanText, true);

            if (!$intent || !isset($intent['action'])) {
                    return response()->json([
                        'reply' => 'Sorry, I did not understand your request.'
                    ]);
                }

            // $reply = 'Done.';

            $history[] = [
                'role' => 'assistant',
                'content' => $cleanText
            ];

            $history = array_slice($history, -10);

            session(['chat_history' => $history]);



            // CREATE
            if ($intent['action'] === 'create') {
                $movie = $this->createMovie([
                    'title' => $intent['title'],
                    'rating' => $intent['rating'] ?? 0,
                    'comment' => $intent['comment'] ?? '',
                    'genre' => 'Unknown',
                    'release_year' => date('Y'),
                    'watched_at' => now(),
                    'category_id' => 1 // make sure this exists in DB
                ]);

                return response()->json([
                    'reply' => "✅ Movie '{$movie->title}' added successfully!",
                    'refresh' => true
                ]);
            }

            // READ
            if ($intent['action'] === 'read') {
                if (!empty($intent['title'])) {
                    $movie = Movie::where('title', $intent['title'])->first();

                    if ($movie) {
                        return response()->json([
                            'reply' => "🎬 {$movie->title}\n⭐ Rating: {$movie->rating}\n💬 {$movie->comment}"
                        ]);
                    } else {
                        return response()->json([
                            'reply' => "❌ Movie not found."
                        ]);
                    }
                } else {
                    // If no title, return all movies
                    $movies = Movie::all();

                    if ($movies->isEmpty()) {
                        return response()->json([
                            'reply' => "📭 No movies found."
                        ]);
                    }

                    $list = $movies->map(function ($m) {
                        return "🎬 {$m->title} (⭐ {$m->rating})";
                    })->implode("\n");

                    return response()->json([
                        'reply' => "📽️ Your Movies:\n" . $list
                    ]);
                }
            }

            // UPDATE
            if ($intent['action'] === 'update') {
                $movie = $this->updateMovie($intent['id'], [
                    'title' => $intent['title'] ?? null,
                    'rating' => $intent['rating'] ?? null,
                    'comment' => $intent['comment'] ?? null,
                ]);

                return response()->json([
                    'reply' => $movie
                        ? "✏️ Movie '{$movie->title}' updated!"
                        : "❌ Movie not found.",
                    'refresh' => true
                ]);
            }

            // DELETE (ASK CONFIRMATION)
            if ($intent['action'] === 'delete') {
                session(['pending_delete' => $intent['title']]);

                return response()->json([
                    'reply' => "⚠️ Are you sure you want to delete '{$intent['title']}'? Type YES to confirm."
                ]);
            }

            // READ / DEFAULT RESPONSE
            return response()->json([
                'reply' => "🤖 I didn’t understand that. You can ask me to:
                        - 📽️ Show all movies
                        - ➕ Add a movie (e.g., 'Add Inception rating 5')
                        - ✏️ Update a movie
                        - 🗑️ Delete a movie"

            ]);

        } catch (\Exception $e) {
            return response()->json([
                'reply' => 'Server error. Please try again later.'
            ], 500);
        }
    }

    private function createMovie($data) {
        return Movie::create($data);
    }

    private function updateMovie($id, $data) {
        $movie = Movie::find($id);

        if ($movie) {
            $movie->update(array_filter($data)); // removes null values
            return $movie;
        }

        return null;
    }
}
