<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use LogicException;
use PDO;
use Throwable;

final class QueuePublisher
{
    public function __construct(
        private PDO $db,
        private SqsClient $sqs,
        private string $queueUrl,
        private ActivityLogger $logger
    ) {}

    public function publishPending(): int
    {
        throw new LogicException('Complete TODO Q1-Q2 and M1 before publishing; then remove this guard.');

        $rows = $this->db->query(
            "SELECT * FROM marketing_outbox WHERE status = 'PENDING' ORDER BY created_at, request_id"
        )->fetchAll();
        $update = $this->db->prepare(
            "UPDATE marketing_outbox SET status = 'QUEUED', queued_at = ?, sqs_message_id = ? "
            . "WHERE request_id = ? AND status = 'PENDING'"
        );
        $published = 0;
        foreach ($rows as $row) {
            $job = [
                'requestId' => $row['request_id'], 'campaignId' => $row['campaign_id'],
                'customerId' => $row['customer_id'], 'promoCode' => $row['promo_code'],
                'subject' => $row['subject'], 'body' => $row['body'],
            ];
            $phase = 'send';
            try {
                // TODO Q1: sendMessage with QueueUrl and JSON_THROW_ON_ERROR MessageBody.
                $result = null;
                $phase = 'outbox_update';
                // TODO Q2: Execute $update AFTER SQS acknowledgment with UTC queued_at,
                // returned MessageId, and local request_id. Never generate a new request ID.
                $phase = 'log';
                // TODO M1: Record queued after both SQS and the outbox update succeed.
                // Include requestId, campaignId, customerId, and SQS messageId.
                $published++;
            } catch (Throwable $error) {
                $this->logger->record('enqueue_failed', [
                    'requestId' => $job['requestId'], 'campaignId' => $job['campaignId'],
                    'customerId' => $job['customerId'], 'phase' => $phase,
                    'errorType' => get_class($error),
                ]);
                throw $error;
            }
        }
        return $published;
    }
}
