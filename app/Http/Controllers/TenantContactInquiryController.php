<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantInboxMessage;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class TenantContactInquiryController extends Controller
{
    public function store(Request $request, string $tenantKey): JsonResponse
    {
        $tenant = Tenant::query()->where('key', $tenantKey)->first();

        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:5000'],
            'pageSlug' => ['nullable', 'string', 'max:120'],
        ]);

        $settings = $this->normalizeSettings($tenant->setting?->settings);
        $recipientEmail = $settings['email'];
        $googleAppKey = $settings['google_app_key'];

        if ($recipientEmail === '' || $googleAppKey === '') {
            return response()->json([
                'ok' => false,
                'status' => 422,
                'error' => 'Contact settings are not configured for this tenant.',
            ], 422);
        }

        $senderName = $settings['sender_name'] !== '' ? $settings['sender_name'] : $tenant->name;
        $replyToEmail = $settings['reply_to_email'] !== '' ? $settings['reply_to_email'] : $validated['email'];

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.encryption' => 'tls',
            'mail.mailers.smtp.username' => $recipientEmail,
            'mail.mailers.smtp.password' => $googleAppKey,
            'mail.from.address' => $recipientEmail,
            'mail.from.name' => $senderName,
        ]);

        $subject = sprintf('[%s] Contact inquiry from %s', $tenant->name, $validated['name']);
        $html = $this->buildMessageHtml($tenant->name, $validated, $request->input('pageSlug'));

        Mail::mailer('smtp')->html($html, function ($message) use ($recipientEmail, $replyToEmail, $subject, $validated, $senderName) {
            $message->to($recipientEmail, $senderName)->subject($subject);
            if ($replyToEmail !== '') {
                $message->replyTo($replyToEmail, $validated['name']);
            }
        });

        $this->storeInquiryRecord($tenant, $validated, $request->input('pageSlug'));

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Your inquiry was sent successfully.',
        ]);
    }

    private function normalizeSettings(mixed $settings): array
    {
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($settings)) {
            $settings = [];
        }

        return [
            'email' => trim((string) ($settings['email'] ?? '')),
            'google_app_key' => trim((string) ($settings['google_app_key'] ?? '')),
            'reply_to_email' => trim((string) ($settings['reply_to_email'] ?? '')),
            'sender_name' => trim((string) ($settings['sender_name'] ?? '')),
        ];
    }

    private function buildMessageHtml(string $tenantName, array $validated, mixed $pageSlug): string
    {
        $safe = fn (mixed $value): string => e((string) $value);
        $name = $safe($validated['name']);
        $email = $safe($validated['email']);
        $phone = $safe($validated['phone'] ?? '');
        $message = $safe($validated['message']);
        $page = $safe($pageSlug ?? 'unknown');
        $tenant = $safe($tenantName);

        return <<<HTML
<div style="font-family: Arial, Helvetica, sans-serif; color: #0f172a; line-height: 1.6;">
  <h2 style="margin: 0 0 16px; font-size: 20px;">New contact inquiry from {$name}</h2>
  <p style="margin: 0 0 12px;"><strong>Tenant:</strong> {$tenant}</p>
  <p style="margin: 0 0 12px;"><strong>Page:</strong> {$page}</p>
  <p style="margin: 0 0 12px;"><strong>Name:</strong> {$name}</p>
  <p style="margin: 0 0 12px;"><strong>Email:</strong> {$email}</p>
  <p style="margin: 0 0 12px;"><strong>Phone:</strong> {$phone}</p>
  <p style="margin: 0 0 8px;"><strong>Message:</strong></p>
  <div style="white-space: pre-wrap; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px;">{$message}</div>
</div>
HTML;
    }

    private function storeInquiryRecord(Tenant $tenant, array $validated, mixed $pageSlug): void
    {
        $page = is_string($pageSlug) ? trim($pageSlug) : '';
        $message = trim((string) ($validated['message'] ?? ''));
        $name = trim((string) ($validated['name'] ?? ''));

        TenantInboxMessage::create([
            'tenant_id' => $tenant->id,
            'name' => $name !== '' ? $name : null,
            'email' => trim((string) ($validated['email'] ?? '')),
            'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
            'subject' => sprintf('Contact inquiry from %s', $name !== '' ? $name : 'customer'),
            'message' => $message,
            'page_slug' => $page !== '' ? $page : null,
            'source' => 'website-contact-form',
            'status' => 'new',
            'read_at' => null,
            'replied_at' => null,
            'meta' => [
                'submitted_at' => Carbon::now()->toISOString(),
                'tenant_name' => $tenant->name,
            ],
        ]);
    }
}
