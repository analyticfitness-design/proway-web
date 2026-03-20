<?php
declare(strict_types=1);

namespace ProWay\Domain\Client;

use PDO;

class MySQLClientRepository implements ClientRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE code = ?');
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findAllActive(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE status = 'activo' ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO clients (code, name, email, phone, company, plan_type, status)
                 VALUES (:code, :name, :email, :phone, :company, :plan_type, :status)'
            );
            $stmt->execute([
                'code'      => $data['code'],
                'name'      => $data['name'],
                'email'     => $data['email'],
                'phone'     => $data['phone'] ?? null,
                'company'   => $data['company'] ?? null,
                'plan_type' => $data['plan_type'] ?? 'starter',
                'status'    => $data['status'] ?? 'activo',
            ]);

            $clientId = (int) $this->db->lastInsertId();

            $stmt = $this->db->prepare(
                'INSERT INTO client_profiles (client_id, password_hash) VALUES (?, ?)'
            );
            $stmt->execute([$clientId, password_hash($data['password'], PASSWORD_DEFAULT)]);

            $this->db->commit();
            return $clientId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['name', 'email', 'phone', 'company', 'plan_type', 'status'];
        $fields  = array_intersect_key($data, array_flip($allowed));

        if (empty($fields)) {
            return false;
        }

        $set    = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $values = array_values($fields);
        $values[] = $id;

        $stmt = $this->db->prepare("UPDATE clients SET $set WHERE id = ?");
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }
}
