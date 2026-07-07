<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class TenantCustomerIntakeMailController extends Controller
{
    public function send(Request $request, string $tenantKey): JsonResponse
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
            'token' => ['required', 'string'],
            'intakeLink' => ['required', 'string'],
        ]);

        $session = $this->decodeSessionToken($validated['token']);
        if (!$session) {
            return response()->json([
                'ok' => false,
                'status' => 410,
                'error' => 'The intake token is invalid or expired.',
            ], 410);
        }

        if (($session['tenantKey'] ?? '') !== $tenantKey) {
            return response()->json([
                'ok' => false,
                'status' => 422,
                'error' => 'The intake token does not belong to this tenant.',
            ], 422);
        }

        $recipientEmail = trim((string) ($session['customerEmail'] ?? ''));
        if ($recipientEmail === '') {
            return response()->json([
                'ok' => false,
                'status' => 422,
                'error' => 'The customer email is missing from the intake token.',
            ], 422);
        }

        $settings = $this->normalizeSettings($tenant->setting?->settings);
        $senderEmail = $settings['email'];
        $googleAppKey = $settings['google_app_key'];

        if ($senderEmail === '' || $googleAppKey === '') {
            return response()->json([
                'ok' => false,
                'status' => 422,
                'error' => 'Contact mail settings are not configured for this tenant.',
            ], 422);
        }

        $senderName = $settings['sender_name'] !== '' ? $settings['sender_name'] : $tenant->name;
        $replyToEmail = $settings['reply_to_email'] !== '' ? $settings['reply_to_email'] : $senderEmail;
        $intakeLink = trim($validated['intakeLink']);
        $customerName = trim((string) ($session['customerName'] ?? 'Customer'));
        $partialAmount = trim((string) ($session['partialPaymentAmount'] ?? $settings['payment_partial_amount'] ?? '100'));
        $currency = trim((string) ($session['currency'] ?? $settings['payment_currency'] ?? 'LKR'));
        $brandName = trim((string) ($session['brandName'] ?? $settings['payment_brand_name'] ?? $tenant->name));
        $note = trim((string) ($session['note'] ?? $settings['payment_note'] ?? ''));

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.encryption' => 'tls',
            'mail.mailers.smtp.username' => $senderEmail,
            'mail.mailers.smtp.password' => $googleAppKey,
            'mail.from.address' => $senderEmail,
            'mail.from.name' => $senderName,
        ]);

        $subject = sprintf('[%s] Customer intake request', $tenant->name);
        $html = $this->buildMessageHtml(
            tenantName: $tenant->name,
            customerName: $customerName,
            intakeLink: $intakeLink,
            partialAmount: $partialAmount,
            currency: $currency,
            brandName: $brandName,
            note: $note,
        );

        Mail::mailer('smtp')->raw($html, function ($message) use ($recipientEmail, $replyToEmail, $subject, $senderName, $customerName, $html) {
            $message->to($recipientEmail, $customerName)->subject($subject)->setBody($html, 'text/html');

            if ($replyToEmail !== '') {
                $message->replyTo($replyToEmail, $senderName);
            }
        });

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Customer intake email sent successfully.',
        ]);
    }

    private function decodeSessionToken(string $token): ?array
    {
        $normalized = strtr(trim($token), '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $json = base64_decode($normalized, true);
        if ($json === false) {
            return null;
        }

        $session = json_decode($json, true);
        if (!is_array($session)) {
            return null;
        }

        $expiresAt = trim((string) ($session['expiresAt'] ?? ''));
        if ($expiresAt === '') {
            return null;
        }

        try {
            if (Carbon::parse($expiresAt)->isPast()) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $session;
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
            'payment_partial_amount' => trim((string) ($settings['payment_partial_amount'] ?? '100')),
            'payment_currency' => trim((string) ($settings['payment_currency'] ?? 'LKR')),
            'payment_brand_name' => trim((string) ($settings['payment_brand_name'] ?? '')),
            'payment_note' => trim((string) ($settings['payment_note'] ?? '')),
        ];
    }

    private function buildMessageHtml(
        string $tenantName,
        string $customerName,
        string $intakeLink,
        string $partialAmount,
        string $currency,
        string $brandName,
        string $note,
    ): string {
        $safe = fn (mixed $value): string => e((string) $value);

        $tenant = $safe($tenantName);
        $customer = $safe($customerName);
        $link = $safe($intakeLink);
        $amount = $safe($partialAmount);
        $currency = $safe($currency);
        $brand = $safe($brandName);
        $noteText = $safe($note);

        return <<<HTML
<div style="font-family: Arial, Helvetica, sans-serif; color: #0f172a; line-height: 1.6;">
  <h2 style="margin: 0 0 16px; font-size: 20px;">{$tenant} requires customer data</h2>
  <p style="margin: 0 0 12px;">Hi {$customer},</p>
  <p style="margin: 0 0 12px;">Please use the secure link below to complete the customer data form and partial payment.</p>
  <p style="margin: 0 0 16px;">
    <a href="{$link}" style="display: inline-block; background: #0f172a; color: #fff; padding: 12px 18px; border-radius: 8px; text-decoration: none;">Open customer intake</a>
  </p>
  <p style="margin: 0 0 12px;"><strong>Link:</strong> {$link}</p>
  <p style="margin: 0 0 12px;"><strong>Session validity:</strong> 24 hours</p>
  <p style="margin: 0 0 12px;"><strong>Partial payment:</strong> {$amount} {$currency}</p>
  <p style="margin: 0 0 12px;"><strong>Brand:</strong> {$brand}</p>
  <p style="margin: 0 0 12px;"><strong>Note:</strong> {$noteText}</p>
</div>
HTML;
    }
}
