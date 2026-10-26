<?php
declare(strict_types=1);

namespace Lab;

use LogicException;
use PHPMailer\PHPMailer\PHPMailer;

final class EmailSender
{
    public function __construct(private array $settings) {}

    public function send(array $customer, array $job): string
    {
        throw new LogicException('Complete TODO E1-E2 in EmailSender::send(), then remove this guard.');

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

        // TODO E1: Add the customer's email and full_name as the recipient.
        // TODO E2: Set Subject and a personalized plain-text Body containing job promoCode.

        // Do not disable TLS certificate verification or suppress exceptions.
        $mail->send();
        return $mail->getLastMessageID();
    }
}
