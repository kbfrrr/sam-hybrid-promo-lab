<?php
declare(strict_types=1);

namespace Lab;

use InvalidArgumentException;
use LogicException;
use PDO;
use RuntimeException;
use Throwable;

final class CampaignPreparer
{
    public function __construct(private PDO $db) {}

    public function prepare(string $campaignId, string $promoCode): int
    {
        if (trim($campaignId) === '' || mb_strlen($campaignId) > 64
            || trim($promoCode) === '' || mb_strlen($promoCode) > 64) {
            throw new InvalidArgumentException('Campaign ID and promo code must contain 1-64 characters.');
        }

        $this->db->beginTransaction();
        try {
            // C3: Preserve an existing complete campaign before drawing any new recipients.
            $count = $this->db->prepare(
                'SELECT COUNT(*) FROM marketing_outbox WHERE campaign_id = ?'
            );
            $count->execute([$campaignId]);
            $existingCount = (int) $count->fetchColumn();
            if ($existingCount === 50) {
                $this->db->commit();
                return 0;
            }
            if ($existingCount !== 0) {
                throw new LogicException(
                    "Campaign {$campaignId} has {$existingCount} requests; expected 0 or 50. "
                    . 'Investigate the inconsistent selection before preparing it again.'
                );
            }

            $recipients = $this->selectRecipients();
            if (count($recipients) !== 50) {
                throw new RuntimeException('The campaign needs exactly 50 eligible customers.');
            }
            $insert = $this->db->prepare(
                'INSERT INTO marketing_outbox '
                . '(request_id, campaign_id, customer_id, promo_code, subject, body, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $subject = 'Laboratory promotion';
            $body = 'You have been selected to receive our promotional offer.';
            foreach ($recipients as $recipient) {
                $requestId = bin2hex(random_bytes(16));
                $customerId = $recipient['customer_id'];
                $createdAt = gmdate('Y-m-d H:i:s');
                // C1: Persist the request once, in the prepared statement's column order.
                $insert->execute([
                    $requestId, $campaignId, $customerId, $promoCode,
                    $subject, $body, $createdAt,
                ]);
            }
            $this->db->commit();
            return 50;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function selectRecipients(): array
    {
        // C2: Each primary-key customer row can appear only once in this sample.
        return $this->db->query(
            'SELECT customer_id FROM customers WHERE marketing_opt_in = 1 ORDER BY RAND() LIMIT 50'
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
