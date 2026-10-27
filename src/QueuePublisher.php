<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use PDO;
use RuntimeException;
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
            $messageId = null;
            try {
                // Q1: Publish the persisted request without selecting new recipients.
                $result = $this->sqs->sendMessage([
                    'QueueUrl' => $this->queueUrl,
                    'MessageBody' => json_encode($job, JSON_THROW_ON_ERROR),
                ]);
                $messageId = $result['MessageId'] ?? null;
                if (!is_string($messageId) || $messageId === '') {
                    throw new RuntimeException('SQS did not return a publication MessageId.');
                }

                $phase = 'outbox_update';
                // Q2: Record QUEUED only after SQS confirms acceptance.
                $update->execute([
                    gmdate('Y-m-d H:i:s'), $messageId, $row['request_id'],
                ]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('The acknowledged request was not updated from PENDING to QUEUED.');
                }

                $phase = 'log';
                // M1: Correlate publication only after both acknowledgments succeed.
                $this->logger->record('queued', [
                    'requestId' => $job['requestId'], 'campaignId' => $job['campaignId'],
                    'customerId' => $job['customerId'], 'messageId' => $messageId,
                ]);
                $published++;
            } catch (Throwable $error) {
                $this->logger->record('enqueue_failed', [
                    'requestId' => $job['requestId'], 'campaignId' => $job['campaignId'],
                    'customerId' => $job['customerId'], 'phase' => $phase,
                    'messageId' => $messageId,
                    'errorType' => get_class($error),
                ]);
                throw $error;
            }
        }
        return $published;
    }
}
