<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Send via the primary SMTP block; on failure, retry with the failover block.
 * Throws RuntimeException if both fail.
 */
function send_mail(string $to, string $subject, string $htmlBody, string $textBody = ''): void
{
    $errors = [];
    foreach (['PRIMARY', 'FAILOVER'] as $block) {
        if (!env("MAIL_{$block}_HOST")) {
            continue;
        }
        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = env("MAIL_{$block}_HOST");
            $mailer->Port = (int) env("MAIL_{$block}_PORT", '587');
            $mailer->SMTPAuth = true;
            $mailer->Username = env("MAIL_{$block}_USERNAME");
            $mailer->Password = env("MAIL_{$block}_PASSWORD");
            $encryption = env("MAIL_{$block}_ENCRYPTION", 'tls');
            if ($encryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom(env("MAIL_{$block}_FROM_ADDRESS"), env("MAIL_{$block}_FROM_NAME", 'Agent Notes'));
            $mailer->addAddress($to);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $textBody ?: strip_tags($htmlBody);
            $mailer->send();
            if ($block === 'FAILOVER') {
                error_log("send_mail: primary SMTP failed, delivered via failover to {$to}");
            }
            return;
        } catch (Throwable $e) {
            $errors[] = "{$block}: {$e->getMessage()}";
            error_log("send_mail: {$block} SMTP failed: {$e->getMessage()}");
        }
    }
    throw new RuntimeException('All SMTP providers failed: ' . implode(' | ', $errors));
}
