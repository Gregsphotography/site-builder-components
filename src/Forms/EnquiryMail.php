<?php

declare(strict_types=1);

namespace SiteBuilder\Components\Forms;

use Illuminate\Mail\Mailable;

final class EnquiryMail extends Mailable
{
    public function __construct(public Submission $submission) {}

    public function build(): static
    {
        $mail = $this->subject('Website enquiry: '.str_replace(["\r", "\n"], '', $this->submission->form_title))->view('site-builder-components::forms.notification', ['submission' => $this->submission]);
        $reply = $this->submission->fields['email'] ?? null;
        if (is_array($reply) && ($reply['type'] ?? '') === 'email' && filter_var($reply['value'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $mail->replyTo($reply['value']);
        }

        return $mail;
    }
}
