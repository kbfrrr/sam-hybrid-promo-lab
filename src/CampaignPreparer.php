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
            // TODO C3: Count this campaign's existing outbox rows BEFORE selection.
            // If exactly 50 exist, commit and return 0 without changing their IDs/code.
            // If a nonzero partial count exists, throw an inconsistency exception.
            throw new LogicException('Complete TODO C3: preserve the original 50-recipient selection.');

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
                // TODO C1: Execute $insert with these seven variables in column order:
                // requestId, campaignId, customerId, promoCode, subject, body, createdAt.
                throw new LogicException('Complete TODO C1: persist each prepared request.');
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
        // TODO C2: Write a SELECT for exactly 50 distinct random opted-in customers.
        // Return rows containing customer_id. Sampling must be without replacement.
        throw new LogicException('Complete TODO C2: select 50 random eligible customers.');
    }
}
