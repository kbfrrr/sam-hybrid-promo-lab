<?php
declare(strict_types=1);

namespace Lab;

use InvalidArgumentException;
use LogicException;
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

        // TODO P1: Use history->isComplete(campaignId, customerId). If true,
        // record duplicate_skipped and return. SENT and SKIPPED are terminal.
        throw new LogicException('Complete TODO P1-P5 and M2 before processing messages.');

        try {
            // TODO M2 (start): Record processing_started using $context.
            // TODO P2: Use a prepared SELECT and job customerId to fetch the customer.
            $customer = null;

            if (!$customer || (int) $customer['marketing_opt_in'] !== 1) {
                $reason = !$customer ? 'Customer not found.' : 'Customer has not opted in.';
                // TODO P3: history->save(job, 'SKIPPED', ['reason' => reason]).
                // TODO M2 (skip): Record skipped AFTER saving, then return.
                throw new LogicException('Complete TODO P3 and M2 for consent/missing-customer skips.');
            }

            // TODO P4: Call the injected sender and retain its returned MIME Message-ID.
            $messageId = null;
            // TODO P5: Save SENT with recipient_email, provider, message_id, and UTC sent_at.
            // TODO M2 (sent): Record sent ONLY AFTER the local SENT save succeeds.
            throw new LogicException('Complete TODO P4-P5 and M2 for successful submissions.');
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
            // Duplicate and skipped branches should not submit any email.
            if ($this->delaySeconds > 0) {
                sleep($this->delaySeconds);
            }
        }
    }
}
