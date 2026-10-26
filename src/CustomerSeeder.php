<?php
declare(strict_types=1);

namespace Lab;

use Faker\Generator;
use LogicException;
use PDO;
use Throwable;

final class CustomerSeeder
{
    public function __construct(private PDO $db, private Generator $faker) {}

    public function seed(): int
    {
        throw new LogicException('Complete TODO S1-S2 in CustomerSeeder::seed(), then remove this guard.');

        $insert = $this->db->prepare(
            'INSERT INTO customers (customer_id, full_name, email, marketing_opt_in, created_at) '
            . 'VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE customer_id = customer_id'
        );
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            for ($i = 1; $i <= 10000; $i++) {
                // TODO S1: Generate a name using the injected Faker Generator.
                $name = null;
                // TODO S2: Consent is 1 for 1-8000 and 0 for 8001-10000.
                $consent = null;
                $insert->execute([
                    sprintf('CUST-%05d', $i), $name,
                    sprintf('customer%05d@example.com', $i),
                    $consent, gmdate('Y-m-d H:i:s'),
                ]);
                $inserted += $insert->rowCount();
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
        return $inserted;
    }
}
