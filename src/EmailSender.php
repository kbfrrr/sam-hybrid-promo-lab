<?php
declare(strict_types=1);

namespace Lab;

use PHPMailer\PHPMailer\PHPMailer;

final class EmailSender
{
    public function __construct(private array $settings) {}

    public function send(array $customer, array $job): string
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->settings['host'];
        $mail->Port = $this->settings['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->settings['username'];
        $mail->Password = $this->settings['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = 30;
        $mail->Timelimit = 30;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($this->settings['from'], 'Marketing laboratory');
        $mail->isHTML(false);

        // E1: Address the message to the selected fictional customer.
        $mail->addAddress($customer['email'], $customer['full_name']);

        // E2: Personalize the plain-text message using the persisted campaign data.
        $mail->Subject = $job['subject'];
        $mail->Body =
            "Hello {$customer['full_name']},\n\n"
            . $job['body'] . "\n\n"
            . "Your promotional code: {$job['promoCode']}\n";

        // Do not disable TLS certificate verification or suppress exceptions.
        $mail->send();
        return $mail->getLastMessageID();
    }
}
