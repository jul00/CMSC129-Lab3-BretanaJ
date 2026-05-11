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

            if (!$message) {
                return response()->json([
                    'reply' => 'Please enter a message.'
                ]);
            }

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

            // DELETE CONFIRMATION
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
                    'reply' => "❌ Movie not found."
                ]);
            }

            // MODEL ROTATION
            $models = [
                'gemini-2.5-flash-lite',
                'gemini-2.5-flash',
                'gemini-1.5-flash'
            ];

            $response = null;
            $lastError = null;

            foreach ($models as $model) {
                try {
                    $response = Http::timeout(10)->post(
                        "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . env('GEMINI_API_KEY'),
                        [
                            "contents" => [
                                [
                                    "parts" => [
                                        [
                                            "text" =>
                                            "You are a movie assistant for MovieWatchd.

                                            MOVIES:
                                            " . json_encode($moviesData) . "

                                            CHAT HISTORY:
                                            " . json_encode($history) . "

                                            USER:
                                            {$message}

                                            Return ONLY valid JSON:

                                            {
                                                \"action\": \"create|read|update|delete|history|none\",
                                                \"id\": number,
                                                \"title\": \"string\",
                                                \"rating\": number,
                                                \"min_rating\": number,
                                                \"max_rating\": number,
                                                \"comment\": \"string\"
                                            }"
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    );

                    if ($response->successful()) {
                        break;
                    }

                    if (in_array($response->status(), [429, 503])) {
                        $lastError = $response->body();
                        continue;
                    }

                    return response()->json([
                        'reply' => 'AI Error: ' . $response->body()
                    ], 500);

                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    continue;
                }
            }

            if (!$response || !$response->successful()) {
                return response()->json([
                    'reply' => "AI unavailable. Please try again later."
                ], 503);
            }

            $data = $response->json();

            $rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $cleanText = trim(preg_replace('/```json|```/', '', $rawText));

            $intent = json_decode($cleanText, true);

            if (!$intent || !isset($intent['action'])) {
                return response()->json([
                    'reply' => 'Invalid AI response.'
                ]);
            }

            $history[] = [
                'role' => 'assistant',
                'content' => $cleanText
            ];

            session(['chat_history' => array_slice($history, -10)]);

            // CREATE
            if ($intent['action'] === 'create') {
                $movie = Movie::create([
                    'title' => $intent['title'] ?? 'Untitled',
                    'rating' => $intent['rating'] ?? 0,
                    'comment' => $intent['comment'] ?? ''
                ]);

                return response()->json([
                    'reply' => "✅ Movie '{$movie->title}' added!",
                    'refresh' => true
                ]);
            }

            // READ
            if ($intent['action'] === 'read') {

                $query = Movie::query();

                if (!empty($intent['title'])) {
                    $query->where('title', 'LIKE', '%' . $intent['title'] . '%');
                }

                if (!empty($intent['min_rating'])) {
                    $query->where('rating', '>=', $intent['min_rating']);
                }

                if (!empty($intent['max_rating'])) {
                    $query->where('rating', '<=', $intent['max_rating']);
                }

                $movies = $query->get();

                if ($movies->isEmpty()) {
                    return response()->json([
                        'reply' => "📭 No matching movies found."
                    ]);
                }

                return response()->json([
                    'reply' => "📽️ Movies:\n" .
                        $movies->map(fn($m) => "🎬 {$m->title} (⭐ {$m->rating})")->implode("\n")
                ]);
            }

            // UPDATE
            if ($intent['action'] === 'update') {

                $movie = Movie::find($intent['id']);

                if (!$movie) {
                    return response()->json([
                        'reply' => "❌ Movie not found."
                    ]);
                }

                $movie->update(array_filter([
                    'title' => $intent['title'] ?? null,
                    'rating' => $intent['rating'] ?? null,
                    'comment' => $intent['comment'] ?? null
                ]));

                return response()->json([
                    'reply' => "✏️ Movie '{$movie->title}' updated!",
                    'refresh' => true
                ]);
            }

            // DELETE
            if ($intent['action'] === 'delete') {
                session(['pending_delete' => $intent['title']]);

                return response()->json([
                    'reply' => "⚠️ Confirm delete '{$intent['title']}'? Type YES."
                ]);
            }

            // HISTORY
            if ($intent['action'] === 'history') {

                $messages = collect(session('chat_history', []))
                    ->where('role', 'user')
                    ->pluck('content')
                    ->take(-5)
                    ->implode("\n");

                return response()->json([
                    'reply' => "🕘 Recent prompts:\n" . $messages
                ]);
            }

            return response()->json([
                'reply' => "🤖 I didn’t understand that."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'reply' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
