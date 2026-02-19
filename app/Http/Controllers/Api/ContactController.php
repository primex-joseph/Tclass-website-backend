<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactAutoReplyMail;
use App\Mail\ContactInquiryMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    private function currentRole(Request $request): ?string
    {
        return DB::table('portal_user_roles')
            ->where('user_id', $request->user()->id)
            ->where('is_active', 1)
            ->value('role');
    }

    private function assertAdmin(Request $request): ?JsonResponse
    {
        if ($this->currentRole($request) !== 'admin') {
            return response()->json(['message' => 'Forbidden. Admin role required.'], 403);
        }

        return null;
    }

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

        DB::table('contact_messages')->insert([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'message' => $validated['message'],
            'is_read' => 0,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

    public function adminMessages(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min($limit, 100));

        $messages = DB::table('contact_messages')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'first_name' => $row->first_name,
                    'last_name' => $row->last_name,
                    'full_name' => trim($row->first_name . ' ' . $row->last_name),
                    'email' => $row->email,
                    'phone' => $row->phone,
                    'message' => $row->message,
                    'is_read' => (bool) $row->is_read,
                    'read_at' => $row->read_at,
                    'created_at' => $row->created_at,
                ];
            });

        $unreadCount = DB::table('contact_messages')
            ->where('is_read', 0)
            ->count();

        return response()->json([
            'messages' => $messages,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        $exists = DB::table('contact_messages')->where('id', $id)->exists();
        if (! $exists) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        DB::table('contact_messages')
            ->where('id', $id)
            ->update([
                'is_read' => 1,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Message marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        if ($resp = $this->assertAdmin($request)) {
            return $resp;
        }

        DB::table('contact_messages')
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'All messages marked as read.']);
    }
}
