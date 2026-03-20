<?php
declare(strict_types=1);

namespace ProWay\Domain\Deliverable;

class DeliverableService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'zip', 'mp4', 'mov', 'jpg', 'jpeg', 'png', 'svg'];
    private const MAX_FILE_SIZE      = 50 * 1024 * 1024; // 50 MB
    private const UPLOAD_BASE_DIR    = '/code/uploads/deliverables';

    public function __construct(private readonly DeliverableRepository $repo) {}

    public function listByProject(int $projectId): array
    {
        return $this->repo->findByProject($projectId);
    }

    /**
     * Handle a file upload for a project deliverable.
     *
     * @param int         $projectId   Target project
     * @param string      $type        Deliverable type (video, design, document, etc.)
     * @param string      $title       Human-readable title
     * @param array       $uploadedFile $_FILES['file'] array
     * @param string|null $description Optional description
     * @return array      The created deliverable row
     */
    public function uploadFile(
        int     $projectId,
        string  $type,
        string  $title,
        array   $uploadedFile,
        ?string $description = null
    ): array {
        // ── Validate upload ──────────────────────────────────────────────────
        if (empty($uploadedFile['tmp_name']) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('No file uploaded or upload error occurred');
        }

        if ($uploadedFile['size'] > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File exceeds maximum size of 50 MB');
        }

        $originalName = basename($uploadedFile['name']);
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(
                'File type not allowed. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS)
            );
        }

        // ── Generate safe filename & ensure directory ────────────────────────
        $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $safeName  = time() . '_' . $sanitized . '.' . $extension;
        $dir       = self::UPLOAD_BASE_DIR . '/' . $projectId;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $destPath = $dir . '/' . $safeName;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $destPath)) {
            throw new \RuntimeException('Failed to move uploaded file');
        }

        // ── Persist record ───────────────────────────────────────────────────
        $fileUrl = '/uploads/deliverables/' . $projectId . '/' . $safeName;

        $id = $this->repo->create([
            'project_id'  => $projectId,
            'type'        => $type,
            'title'       => $title,
            'file_url'    => $fileUrl,
            'description' => $description,
        ]);

        return $this->repo->findById($id);
    }
}
