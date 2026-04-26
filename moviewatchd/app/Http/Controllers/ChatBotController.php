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
            $movies = Movie::all();

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

                                    MOVIES DATA:
                                    " . json_encode($movies) . "

                                    USER QUESTION:
                                    " . $message . "

                                    If user asks to delete, update, or find movies, respond using ONLY this dataset."
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

            return response()->json([
                'reply' => $reply
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'reply' => 'Server error. Please try again later.'
            ], 500);
        }
    }
}