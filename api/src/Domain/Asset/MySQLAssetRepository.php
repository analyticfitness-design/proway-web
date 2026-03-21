<?php
declare(strict_types=1);

namespace ProWay\Domain\Asset;

use PDO;

class MySQLAssetRepository implements AssetRepository
{
    public function __construct(private readonly PDO $db) {}

    public function search(array $filters, int $page, int $perPage): array
    {
        $where  = [];
        $params = [];

        $this->buildWhere($filters, $where, $params);

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT d.id, d.project_id, d.type, d.title, d.file_url,
                       d.preview_url, d.thumbnail_url, d.description,
                       d.version, d.delivered_at,
                       p.title AS project_title, p.service_type AS project_code,
                       c.id AS client_id, c.name AS client_name,
                       GROUP_CONCAT(DISTINCT at_tag.name ORDER BY at_tag.name SEPARATOR ', ') AS tag_names,
                       GROUP_CONCAT(DISTINCT at_tag.id ORDER BY at_tag.name SEPARATOR ',') AS tag_ids
                FROM deliverables d
                JOIN projects p ON p.id = d.project_id
                JOIN clients  c ON c.id = p.client_id
                LEFT JOIN deliverable_tags dt ON dt.deliverable_id = d.id
                LEFT JOIN asset_tags at_tag ON at_tag.id = dt.tag_id
                {$whereClause}
                GROUP BY d.id
                ORDER BY d.delivered_at DESC, d.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countSearch(array $filters): int
    {
        $where  = [];
        $params = [];

        $this->buildWhere($filters, $where, $params);

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT COUNT(DISTINCT d.id)
                FROM deliverables d
                JOIN projects p ON p.id = d.project_id
                JOIN clients  c ON c.id = p.client_id
                LEFT JOIN deliverable_tags dt ON dt.deliverable_id = d.id
                LEFT JOIN asset_tags at_tag ON at_tag.id = dt.tag_id
                {$whereClause}";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findAllTags(): array
    {
        $stmt = $this->db->query(
            'SELECT t.id, t.name, t.created_at, COUNT(dt.deliverable_id) AS usage_count
             FROM asset_tags t
             LEFT JOIN deliverable_tags dt ON dt.tag_id = t.id
             GROUP BY t.id
             ORDER BY t.name ASC'
        );
        return $stmt->fetchAll();
    }

    public function attachTags(int $deliverableId, array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }

        $sql = 'INSERT IGNORE INTO deliverable_tags (deliverable_id, tag_id) VALUES ';
        $placeholders = [];
        $params       = [];

        foreach ($tagIds as $i => $tagId) {
            $placeholders[] = "(:did{$i}, :tid{$i})";
            $params[":did{$i}"] = $deliverableId;
            $params[":tid{$i}"] = (int) $tagId;
        }

        $stmt = $this->db->prepare($sql . implode(', ', $placeholders));
        $stmt->execute($params);
    }

    public function detachTags(int $deliverableId, array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $stmt = $this->db->prepare(
            "DELETE FROM deliverable_tags WHERE deliverable_id = ? AND tag_id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$deliverableId], array_map('intval', $tagIds)));
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, p.title AS project_title, p.service_type AS project_code,
                    c.id AS client_id, c.name AS client_name
             FROM deliverables d
             JOIN projects p ON p.id = d.project_id
             JOIN clients  c ON c.id = p.client_id
             WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getTagsForDeliverable(int $deliverableId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.id, t.name
             FROM asset_tags t
             JOIN deliverable_tags dt ON dt.tag_id = t.id
             WHERE dt.deliverable_id = ?
             ORDER BY t.name ASC'
        );
        $stmt->execute([$deliverableId]);
        return $stmt->fetchAll();
    }

    public function createTag(string $name): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO asset_tags (name) VALUES (:name)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $stmt->execute([':name' => $name]);
        return (int) $this->db->lastInsertId();
    }

    // ── Private helper ─────────────────────────────────────────────────────────

    private function buildWhere(array $filters, array &$where, array &$params): void
    {
        if (!empty($filters['client_id'])) {
            $where[]              = 'c.id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }

        if (!empty($filters['type'])) {
            $where[]         = 'd.type = :type';
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['tag_id'])) {
            $where[]           = 'dt.tag_id = :tag_id';
            $params[':tag_id'] = (int) $filters['tag_id'];
        }

        if (!empty($filters['q'])) {
            $where[]     = '(d.title LIKE :q OR d.description LIKE :q OR p.title LIKE :q OR c.name LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
    }
}
