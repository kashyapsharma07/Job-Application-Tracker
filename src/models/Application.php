<?php
// src/models/Application.php

class Application {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(int $userId, array $filters = []): array {
        $sql = 'SELECT a.*, r.version_label as resume_label, r.original_name as resume_name
                FROM applications a
                LEFT JOIN resumes r ON a.resume_id = r.id
                WHERE a.user_id = ?';
        $params = [$userId];

        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (a.company LIKE ? OR a.job_title LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= ' ORDER BY a.updated_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id, int $userId): ?array {
        $stmt = $this->db->prepare(
            'SELECT a.*, r.version_label as resume_label, r.original_name as resume_name
             FROM applications a
             LEFT JOIN resumes r ON a.resume_id = r.id
             WHERE a.id = ? AND a.user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $userId, array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO applications (user_id, company, job_title, job_url, job_type, status, salary_range, resume_id, notes, applied_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['company'],
            $data['job_title'],
            $data['job_url'] ?? null,
            $data['job_type'] ?? 'not_specified',
            $data['status'] ?? 'wishlist',
            $data['salary_range'] ?? null,
            $data['resume_id'] ?? null,
            $data['notes'] ?? null,
            $data['applied_at'] ?? null,
        ]);
        $appId = (int)$this->db->lastInsertId();
        // Log creation event
        $this->addEvent($appId, 'status_change', 'Application created', 'Status: ' . ($data['status'] ?? 'wishlist'));
        return $appId;
    }

    public function update(int $id, int $userId, array $data): bool {
        // Fetch old status to log change
        $old = $this->getById($id, $userId);
        $stmt = $this->db->prepare(
            'UPDATE applications SET company=?, job_title=?, job_url=?, job_type=?, status=?,
             salary_range=?, resume_id=?, notes=?, applied_at=?
             WHERE id=? AND user_id=?'
        );
        $ok = $stmt->execute([
            $data['company'],
            $data['job_title'],
            $data['job_url'] ?? null,
            $data['job_type'] ?? 'not_specified',
            $data['status'],
            $data['salary_range'] ?? null,
            $data['resume_id'] ?? null,
            $data['notes'] ?? null,
            $data['applied_at'] ?? null,
            $id,
            $userId,
        ]);
        // Log status change
        if ($ok && $old && $old['status'] !== $data['status']) {
            $this->addEvent($id, 'status_change', 'Status updated',
                'Changed from ' . $old['status'] . ' to ' . $data['status']);
        }
        return $ok;
    }

    public function delete(int $id, int $userId): bool {
        $stmt = $this->db->prepare('DELETE FROM applications WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }

    public function updateStatus(int $id, int $userId, string $status): bool {
        $old = $this->getById($id, $userId);
        $stmt = $this->db->prepare('UPDATE applications SET status = ? WHERE id = ? AND user_id = ?');
        $ok = $stmt->execute([$status, $id, $userId]);
        if ($ok && $old && $old['status'] !== $status) {
            $this->addEvent($id, 'status_change', 'Status updated',
                'Changed from ' . $old['status'] . ' to ' . $status);
        }
        return $ok;
    }

    public function countByStatus(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) as count FROM applications WHERE user_id = ? GROUP BY status'
        );
        $stmt->execute([$userId]);
        $result = ['wishlist'=>0,'applied'=>0,'interviewing'=>0,'offer'=>0,'rejected'=>0];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int)$row['count'];
        }
        return $result;
    }

    public function countByMonth(int $userId, int $months = 6): array {
        $stmt = $this->db->prepare(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count
             FROM applications
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY month ORDER BY month ASC'
        );
        $stmt->execute([$userId, $months]);
        return $stmt->fetchAll();
    }

    public function topCompanies(int $userId, int $limit = 5): array {
        $stmt = $this->db->prepare(
            'SELECT company, COUNT(*) as count, status
             FROM applications WHERE user_id = ?
             GROUP BY company ORDER BY count DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    // Application events / timeline
    public function addEvent(int $appId, string $type, string $title, ?string $desc = null, ?string $date = null): int {
        $stmt = $this->db->prepare(
            'INSERT INTO application_events (application_id, event_type, title, description, event_date)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$appId, $type, $title, $desc, $date ?? date('Y-m-d H:i:s')]);
        return (int)$this->db->lastInsertId();
    }

    public function getEvents(int $appId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM application_events WHERE application_id = ? ORDER BY event_date DESC'
        );
        $stmt->execute([$appId]);
        return $stmt->fetchAll();
    }

    // Calendar events for a user
    public function getCalendarEvents(int $userId, string $year, string $month): array {
        $stmt = $this->db->prepare(
            'SELECT ae.*, a.company, a.job_title
             FROM application_events ae
             JOIN applications a ON ae.application_id = a.id
             WHERE a.user_id = ?
               AND DATE_FORMAT(ae.event_date, "%Y-%m") = ?
             ORDER BY ae.event_date ASC'
        );
        $stmt->execute([$userId, "$year-$month"]);
        return $stmt->fetchAll();
    }

    public function getUpcomingEvents(int $userId, int $limit = 5): array {
        $stmt = $this->db->prepare(
            'SELECT ae.*, a.company, a.job_title
             FROM application_events ae
             JOIN applications a ON ae.application_id = a.id
             WHERE a.user_id = ? AND ae.event_date >= NOW()
             ORDER BY ae.event_date ASC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}
