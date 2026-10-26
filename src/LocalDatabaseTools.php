<?php
declare(strict_types=1);

namespace Lab;

use LogicException;
use PDO;
use RuntimeException;

final class LocalDatabaseTools
{
    public function __construct(private PDO $db) {}

    public static function readSchema(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Could not read schema.sql.');
        }
        $sql = preg_replace('~/\*.*?\*/~s', '', $sql) ?? '';
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
        $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
        if ($statements === []) {
            throw new LogicException('Complete TODO D1 in schema.sql before initializing tables.');
        }
        foreach ($statements as $statement) {
            if (!preg_match('/^CREATE\s+TABLE\b/i', $statement)) {
                throw new LogicException('schema.sql must contain table definitions only.');
            }
        }
        foreach (['customers', 'marketing_outbox', 'sent_marketing_emails'] as $table) {
            $pattern = '/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?'
                . preg_quote($table, '/') . '`?\s*\(/i';
            if (!array_filter($statements, static fn (string $statement): bool =>
                preg_match($pattern, $statement) === 1)) {
                throw new LogicException("TODO D1: missing table definition for {$table}.");
            }
        }
        return $statements;
    }

    public function initialize(array $statements): void
    {
        // MySQL/MariaDB DDL commits implicitly. Do not claim transactional schema creation.
        foreach ($statements as $statement) {
            $this->db->exec($statement);
        }
    }

    public function inspect(): array
    {
        return [
            'customer_count' => (int) $this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
            'consent_counts' => $this->db->query(
                'SELECT marketing_opt_in, COUNT(*) AS total FROM customers GROUP BY marketing_opt_in'
            )->fetchAll(),
            'sample_customers' => $this->db->query(
                'SELECT customer_id, full_name, email, marketing_opt_in '
                . 'FROM customers ORDER BY customer_id LIMIT 10'
            )->fetchAll(),
            'outbox_counts' => $this->db->query(
                'SELECT campaign_id, status, COUNT(*) AS total '
                . 'FROM marketing_outbox GROUP BY campaign_id, status'
            )->fetchAll(),
            'history_counts' => $this->db->query(
                'SELECT campaign_id, status, COUNT(*) AS total '
                . 'FROM sent_marketing_emails GROUP BY campaign_id, status'
            )->fetchAll(),
        ];
    }
}
