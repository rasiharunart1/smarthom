<?php

namespace App\Mail;

use App\Models\Device;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeviceSharedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Device $device,
        public User   $sharedBy,
        public User   $sharedWith,
        public string $permission,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Smarthom] {$this->sharedBy->name} membagikan akses device ke kamu",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.device-shared',
        );
    }
}
