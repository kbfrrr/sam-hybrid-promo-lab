<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use InvalidArgumentException;
use Throwable;

final class QueueWorker
{
    public function __construct(
        private SqsClient $sqs,
        private string $queueUrl,
        private MarketingProcessor $processor,
        private ActivityLogger $logger
    ) {}

    public function run(): void
    {
        while (true) {
            try {
                $result = $this->sqs->receiveMessage([
                    'QueueUrl' => $this->queueUrl,
                    'MaxNumberOfMessages' => 1,
                    'WaitTimeSeconds' => 20,
                    'VisibilityTimeout' => 180,
                    'MessageSystemAttributeNames' => ['ApproximateReceiveCount'],
                ]);
            } catch (Throwable $error) {
                $this->logger->record('receive_failed', [
                    'phase' => 'receive', 'errorType' => get_class($error),
                ]);
                sleep(5);
                continue;
            }
            foreach ($result['Messages'] ?? [] as $message) {
                $this->handle($message);
            }
        }
    }

    private function handle(array $message): void
    {
        $phase = 'decode';
        $context = [
            'messageId' => $message['MessageId'] ?? '',
            'receiveCount' => $message['Attributes']['ApproximateReceiveCount'] ?? '',
        ];
        try {
            $this->logger->record('received', $context);
            $job = json_decode($message['Body'] ?? '', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($job) || array_is_list($job)) {
                throw new InvalidArgumentException('The message body must be a JSON object.');
            }
            MarketingProcessor::validateJob($job);
            $context += [
                'requestId' => $job['requestId'], 'campaignId' => $job['campaignId'],
                'customerId' => $job['customerId'],
            ];
            $phase = 'process';
            // W1: Processing must return normally after saving a terminal outcome.
            $this->processor->process($job);

            // M3/W2: Acknowledge with the current receipt, after processing succeeds.
            $phase = 'delete';
            $this->sqs->deleteMessage([
                'QueueUrl' => $this->queueUrl,
                'ReceiptHandle' => $message['ReceiptHandle'],
            ]);

            // M3: Only report deletion once SQS has acknowledged it.
            $this->logger->record('deleted', $context);
        } catch (Throwable $error) {
            $this->logger->record($phase === 'delete' ? 'ack_failed' : 'processing_failed',
                $context + ['phase' => $phase, 'errorType' => get_class($error)]
            );
            // Leave the message undeleted for SQS retry and eventual DLQ handling.
        }
    }
}
