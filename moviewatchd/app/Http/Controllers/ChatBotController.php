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
                    'title' => $m->title,
                    'rating' => $m->rating,
                    'comment' => $m->comment
                ];
            });

            $history[] = [
                'role' => 'user',
                'message' => $message
            ];

            $history = array_slice($history, -10);

            if (strtolower($message) === 'yes' && session('pending_delete')) {
                $title = session('pending_delete');
                session()->forget('pending_delete');

                $movie = Movie::where('title', $title)->first();

                if ($movie) {
                    $movie->delete();

                    return response()->json([
                        'reply' => "🗑️ Movie '$title' deleted successfully!"
                    ]);
                }

                return response()->json([
                    'reply' => "❌ Movie not found."
                ]);
            }

            if (!$message) {
                return response()->json([
                    'reply' => 'Please enter a message.'
                ]);
            }

            $response = Http::post(
                'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' . env('GEMINI_API_KEY'),
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

                                    If user asks to delete, update, or find movies, respond using ONLY this dataset.

                                    Respond ONLY in JSON format like this:

                                    {
                                    \"action\": \"create | update | delete | read | none\",
                                    \"title\": \"movie title\",
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

            if (!$response->successful()) {
                return response()->json([
                    'reply' => 'AI Error: ' . $response->body()
                ], 500);
            }

            $data = $response->json();

            $reply = $data['candidates'][0]['content']['parts'][0]['text']
                ?? 'No response from AI.';


            $history[] = [
                'role' => 'assistant',
                'message' => $reply
            ];

            $history = array_slice($history, -10);

            session(['chat_history' => $history]);

            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

            $text = trim($text);
            $text = preg_replace('/```json|```/', '', $text);

            $intent = json_decode($text, true);

            // safety check
            if (!$intent || !isset($intent['action'])) {
                return response()->json([
                    'reply' => 'Sorry, I did not understand that.'
                ]);
            }

            // CREATE
            if ($intent['action'] === 'create') {
                $movie = $this->createMovie([
                    'title' => $intent['title'],
                    'rating' => $intent['rating'] ?? 0,
                    'comment' => $intent['comment'] ?? ''
                ]);

                return response()->json([
                    'reply' => "✅ Movie '{$movie->title}' added successfully!"
                ]);
            }

            // UPDATE
            if ($intent['action'] === 'update') {
                $movie = $this->updateMovie($intent['title'], [
                    'rating' => $intent['rating'],
                    'comment' => $intent['comment']
                ]);

                return response()->json([
                    'reply' => $movie
                        ? "✏️ Movie '{$movie->title}' updated!"
                        : "❌ Movie not found."
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
                'reply' => $reply
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

    private function updateMovie($title, $data) {
        $movie = Movie::where('title', $title)->first();
        if ($movie) {
            $movie->update($data);
            return $movie;
        }
        return null;
    }

    private function deleteMovie($title) {
        $movie = Movie::where('title', $title)->first();
        if ($movie) {
            $movie->delete();
            return $movie;
        }
        return null;
    }

}
