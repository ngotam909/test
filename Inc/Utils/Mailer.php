<?php
namespace Utils;

class Mailer {
    private string $mailboxDir;
    private string $fromEmail;
    private string $fromName;

    public function __construct(string $mailboxDir, string $fromEmail, string $fromName) {
        $this->mailboxDir = rtrim($mailboxDir, '/');
        $this->fromEmail  = $fromEmail;
        $this->fromName   = $fromName;
        if (!is_dir($this->mailboxDir)) mkdir($this->mailboxDir, 0777, true);
    }
    public function send(string $to, string $subject, string $html, ?string $text = null, array $headers = []): bool {
        $id = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $meta = [
            'id' => $id,
            'from' => sprintf('%s <%s>', $this->fromName, $this->fromEmail),
            'to' => $to,
            'subject' => $subject,
            'date' => date('c'),
            'headers' => $headers,
        ];

        file_put_contents("{$this->mailboxDir}/{$id}.json", json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

        $raw =
            "From: {$meta['from']}\r\n" .
            "To: {$to}\r\n" .
            "Subject: {$subject}\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n\r\n" .
            $html;

        file_put_contents("{$this->mailboxDir}/{$id}.eml", $raw);
        if ($text) {
            file_put_contents("{$this->mailboxDir}/{$id}.txt", $text);
        }

        return true;
    }
}
