<?php
declare(strict_types=1);

namespace ProWay\Domain\Project;

use PDO;

class MySQLProjectRepository implements ProjectRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findAllForClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE projects SET status = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    public function create(array $data): int
    {
        $year  = date('Y');
        $count = (int) $this->db->query(
            "SELECT COUNT(*) FROM projects WHERE project_code LIKE 'PW-{$year}-%'"
        )->fetchColumn();
        $code  = sprintf('PW-%s-%03d', $year, $count + 1);

        $stmt = $this->db->prepare(
            'INSERT INTO projects
                (client_id, project_code, service_type, title, description, price_cop, status, start_date, deadline, notes)
             VALUES
                (:client_id, :project_code, :service_type, :title, :description, :price_cop, :status, :start_date, :deadline, :notes)'
        );
        $stmt->execute([
            'client_id'    => $data['client_id'],
            'project_code' => $code,
            'service_type' => $data['service_type'],
            'title'        => $data['title']       ?? null,
            'description'  => $data['description'] ?? null,
            'price_cop'    => $data['price_cop'],
            'status'       => $data['status']      ?? 'cotizacion',
            'start_date'   => $data['start_date']  ?? null,
            'deadline'     => $data['deadline']    ?? null,
            'notes'        => $data['notes']       ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT p.*, c.nombre AS client_name, c.code AS client_code
             FROM projects p
             LEFT JOIN clients c ON c.id = p.client_id
             ORDER BY p.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public function countActive(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM projects WHERE status IN ('confirmado','en_produccion','revision')"
        );
        return (int) $stmt->fetchColumn();
    }
}
