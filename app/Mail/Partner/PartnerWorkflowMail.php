<?php

namespace App\Mail\Partner;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class PartnerWorkflowMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data = [])
    {
    }

    abstract protected function subjectText(): string;

    abstract protected function headline(): string;

    abstract protected function fields(): array;

    abstract protected function messageText(): string;

    protected function action(): ?array
    {
        return null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectText());
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->renderHtml());
    }

    private function renderHtml(): string
    {
        $fields = collect($this->fields())
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(function ($value, string $label): string {
                return '<tr><td style="padding:6px 10px;color:#64748b;">' . e($label) . '</td><td style="padding:6px 10px;font-weight:700;color:#0f172a;">' . e((string) $value) . '</td></tr>';
            })
            ->implode('');

        $action = $this->action();
        $button = $action
            ? '<p style="margin:22px 0 0;"><a href="' . e($action['url']) . '" style="display:inline-block;padding:10px 16px;border-radius:8px;background:#0f172a;color:#fff;text-decoration:none;font-weight:700;">' . e($action['label']) . '</a></p>'
            : '';

        return '<div style="font-family:Arial,sans-serif;line-height:1.55;color:#0f172a;max-width:640px;margin:auto;">'
            . '<h2 style="margin:0 0 14px;">' . e($this->headline()) . '</h2>'
            . '<table style="border-collapse:collapse;width:100%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin:0 0 18px;">' . $fields . '</table>'
            . '<p style="white-space:pre-line;margin:0;">' . e($this->messageText()) . '</p>'
            . $button
            . '<p style="margin-top:24px;color:#64748b;font-size:13px;">SportGo</p>'
            . '</div>';
    }

    protected function value(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }
}
