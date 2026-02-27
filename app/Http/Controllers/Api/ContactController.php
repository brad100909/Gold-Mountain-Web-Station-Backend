<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ContactController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $name    = trim($request->input('name', ''));
        $email   = trim($request->input('email', ''));
        $message = trim($request->input('message', ''));

        if (! $name || ! $email || ! $message) {
            return response()->json(['error' => 'All fields are required.'], 400);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'Invalid email address.'], 400);
        }

        $response = Http::withToken(config('services.resend.key'))
            ->post('https://api.resend.com/emails', [
                'from'     => config('services.resend.from'),
                'to'       => [config('services.resend.to')],
                'reply_to' => $email,
                'subject'  => "[Golden Mountain] New inquiry from {$name}",
                'text'     => "Name: {$name}\nEmail: {$email}\n\n{$message}",
            ]);

        if (! $response->successful()) {
            return response()->json(['error' => 'Failed to send email.'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
