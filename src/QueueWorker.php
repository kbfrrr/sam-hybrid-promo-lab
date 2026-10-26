<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use InvalidArgumentException;
use LogicException;
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
        // Remove this guard only after W1-W2, M3, AND processor/sender tasks are complete.
        // This prevents unfinished code from receiving live jobs and driving them into a DLQ.
        throw new LogicException('Complete the processor/sender and TODO W1-W2/M3 before starting the worker.');

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
            // TODO W1: Invoke the injected processor with this validated job.
            throw new LogicException('Complete TODO W1-W2 and M3 in QueueWorker::handle().');
            // TODO M3 (phase): Change $phase to 'delete' BEFORE acknowledgment.
            // TODO W2: DeleteMessage with QueueUrl and this message's CURRENT ReceiptHandle.
            // TODO M3 (deleted): Record deleted with $context AFTER deletion succeeds.
        } catch (Throwable $error) {
            $this->logger->record($phase === 'delete' ? 'ack_failed' : 'processing_failed',
                $context + ['phase' => $phase, 'errorType' => get_class($error)]
            );
            // Leave the message undeleted for SQS retry and eventual DLQ handling.
        }
    }
}
