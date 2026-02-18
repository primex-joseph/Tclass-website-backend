<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactAutoReplyMail;
use App\Mail\ContactInquiryMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $recipient = env('CONTACT_RECEIVER_EMAIL', env('MAIL_FROM_ADDRESS'));
        if (! $recipient) {
            return response()->json([
                'message' => 'Contact recipient email is not configured.',
            ], 500);
        }

        try {
            Mail::to($recipient)->send(
                new ContactInquiryMail(
                    firstName: $validated['first_name'],
                    lastName: $validated['last_name'],
                    email: $validated['email'],
                    phone: $validated['phone'] ?? null,
                    body: $validated['message']
                )
            );

            Mail::to($validated['email'])->send(
                new ContactAutoReplyMail(
                    firstName: $validated['first_name']
                )
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unable to send message right now. Please try again later.',
            ], 500);
        }

        return response()->json([
            'message' => 'Message sent successfully. We will get back to you soon.',
        ]);
    }
}
