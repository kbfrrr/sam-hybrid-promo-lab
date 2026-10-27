<?php
declare(strict_types=1);

namespace Lab;

use Faker\Generator;
use PDO;
use Throwable;

final class CustomerSeeder
{
    public function __construct(private PDO $db, private Generator $faker) {}

    public function seed(): int
    {
        $insert = $this->db->prepare(
            'INSERT INTO customers (customer_id, full_name, email, marketing_opt_in, created_at) '
            . 'VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE customer_id = customer_id'
        );
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            for ($i = 1; $i <= 10000; $i++) {
                // S1: Generate a fictional name using the injected Faker Generator.
                $name = $this->faker->name();
                // S2: Exactly 8,000 opted-in and 2,000 opted-out customers.
                $consent = $i <= 8000 ? 1 : 0;
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
