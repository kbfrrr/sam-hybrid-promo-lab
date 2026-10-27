<?php
declare(strict_types=1);

namespace Lab;

use InvalidArgumentException;
use PDO;
use Throwable;

final class MarketingProcessor
{
    public function __construct(
        private PDO $db,
        private EmailHistory $history,
        private EmailSender $sender,
        private ActivityLogger $logger,
        private int $delaySeconds = 11
    ) {
        if ($delaySeconds < 0 || $delaySeconds > 120) {
            throw new InvalidArgumentException('Send delay must be from 0 to 120 seconds.');
        }
    }

    public static function validateJob(array $job): void
    {
        $limits = [
            'requestId' => 100, 'campaignId' => 64, 'customerId' => 32,
            'promoCode' => 64, 'subject' => 255,
        ];
        foreach ($limits as $field => $max) {
            if (!isset($job[$field]) || !is_string($job[$field])
                || trim($job[$field]) === '' || mb_strlen($job[$field]) > $max) {
                throw new InvalidArgumentException("Job field {$field} must contain 1-{$max} characters.");
            }
        }
        if (!isset($job['body']) || !is_string($job['body'])
            || trim($job['body']) === '' || strlen($job['body']) > 65535) {
            throw new InvalidArgumentException('Job field body must contain 1-65535 bytes.');
        }
    }

    public function process(array $job): void
    {
        self::validateJob($job);
        $context = [
            'requestId' => $job['requestId'], 'campaignId' => $job['campaignId'],
            'customerId' => $job['customerId'],
        ];

        // P1: Terminal outcomes suppress sequential duplicate submissions.
        if ($this->history->isComplete($job['campaignId'], $job['customerId'])) {
            $this->logger->record('duplicate_skipped', $context);
            return;
        }

        $sendAttempted = false;
        try {
            // M2: Record entry into the validated processing workflow.
            $this->logger->record('processing_started', $context);

            // P2: Load current customer data and consent from the local database.
            $statement = $this->db->prepare(
                'SELECT customer_id, full_name, email, marketing_opt_in '
                . 'FROM customers WHERE customer_id = ?'
            );
            $statement->execute([$job['customerId']]);
            $customer = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$customer || (int) $customer['marketing_opt_in'] !== 1) {
                $reason = !$customer ? 'Customer not found.' : 'Customer has not opted in.';
                // P3/M2: Persist the terminal skip before reporting it as complete.
                $this->history->save($job, 'SKIPPED', ['reason' => $reason]);
                $this->logger->record('skipped', $context);
                return;
            }

            // P4: SMTP exceptions must propagate so the worker does not acknowledge.
            $sendAttempted = true;
            $messageId = $this->sender->send($customer, $job);

            // P5/M2: Store SMTP acceptance locally before logging a successful outcome.
            $this->history->save($job, 'SENT', [
                'recipient_email' => $customer['email'],
                'provider' => 'Mailtrap',
                'message_id' => $messageId,
                'sent_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->logger->record('sent', $context);
        } catch (Throwable $error) {
            // Only validated jobs have usable history keys. Never acknowledge this failure.
            // Do not overwrite a terminal outcome if only post-save logging failed.
            if (!$this->history->isComplete($job['campaignId'], $job['customerId'])) {
                $this->history->save($job, 'FAILED', [
                    'reason' => 'Processing exception: ' . get_class($error),
                ]);
            }
            throw $error;
        } finally {
            // Throttle failed attempts too: SMTP acceptance can precede a later error.
            // No rate-limit delay is needed when no SMTP submission was attempted.
            if ($sendAttempted && $this->delaySeconds > 0) {
                sleep($this->delaySeconds);
            }
        }
    }
}
