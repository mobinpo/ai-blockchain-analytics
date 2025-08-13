<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

final class OnboardingEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $emailType,
        private readonly array $data,
        private readonly string $messageId
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'welcome' => '🚀 Welcome to AI Blockchain Analytics!',
            'getting_started' => '📊 Ready to analyze your first smart contract?',
            'first_analysis' => '🎯 How\'s your smart contract analysis going?',
            'advanced_features' => '🚀 Unlock powerful advanced features!',
            'feedback' => '💭 How has your experience been so far?',
        ];

        return new Envelope(
            from: config('mailgun.onboarding.from_email'),
            replyTo: config('mailgun.onboarding.reply_to'),
            subject: $subjects[$this->emailType] ?? 'Update from AI Blockchain Analytics',
            tags: [
                'onboarding',
                $this->emailType,
                'email-sequence'
            ],
        );
    }

    public function content(): Content
    {
        $templateMap = [
            'welcome' => 'emails.onboarding.welcome',
            'getting_started' => 'emails.onboarding.getting-started',
            'first_analysis' => 'emails.onboarding.first-analysis',
            'advanced_features' => 'emails.onboarding.advanced-features',
            'feedback' => 'emails.onboarding.feedback',
        ];

        return new Content(
            view: $templateMap[$this->emailType] ?? 'emails.onboarding.welcome',
            with: array_merge($this->data, [
                'emailType' => $this->emailType,
                'trackingPixel' => $this->getTrackingPixelUrl()
            ])
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            messageId: $this->messageId,
            references: [],
            text: [
                'X-Mailgun-Track' => 'yes',
                'X-Mailgun-Track-Clicks' => 'yes',
                'X-Mailgun-Track-Opens' => 'yes',
                'X-Mailgun-Variables' => json_encode([
                    'email_type' => $this->emailType,
                    'sequence' => 'onboarding',
                    'message_id' => $this->messageId
                ])
            ]
        );
    }

    private function getTrackingPixelUrl(): string
    {
        return route('email.tracking.pixel', [
            'message_id' => $this->messageId,
            'type' => 'open'
        ]);
    }
}