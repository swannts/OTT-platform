<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MarketingCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $campaignSubject,
        public string $body,
        public ?string $callToAction,
        public ?string $destinationUrl,
        public string $unsubscribeUrl,
        public string $openPixelUrl,
    ) {}

    public function build(): self
    {
        return $this->subject(str_replace(["\r", "\n"], ' ', $this->campaignSubject))
            ->view('emails.marketing-campaign');
    }
}
