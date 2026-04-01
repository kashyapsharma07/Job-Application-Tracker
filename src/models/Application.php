<?php
// src/models/Application.php

class Application {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ── Existing methods (unchanged) ─────────────────────────────────────────

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
        $this->addEvent($appId, 'status_change', 'Application created', 'Status: ' . ($data['status'] ?? 'wishlist'));
        return $appId;
    }

    public function update(int $id, int $userId, array $data): bool {
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
        // Validate status against whitelist
        $validStatuses = ['wishlist', 'applied', 'interviewing', 'offer', 'rejected'];
        if (!in_array($status, $validStatuses, true)) {
            error_log('Invalid status attempted: ' . $status);
            return false;
        }
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
        $result = ['wishlist' => 0, 'applied' => 0, 'interviewing' => 0, 'offer' => 0, 'rejected' => 0];
        foreach ($stmt->fetchAll() as $row) {
            if (isset($result[$row['status']])) {
                $result[$row['status']] = (int)$row['count'];
            }
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

    public function countByWeek(int $userId, int $weeks = 12): array {
        $stmt = $this->db->prepare(
            'SELECT DATE_FORMAT(created_at, "%Y-W%u") as week, COUNT(*) as count
             FROM applications
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
             GROUP BY week ORDER BY week ASC'
        );
        $stmt->execute([$userId, $weeks]);
        return $stmt->fetchAll();
    }

    public function countByYear(int $userId, int $years = 3): array {
        $stmt = $this->db->prepare(
            'SELECT YEAR(created_at) as year, COUNT(*) as count
             FROM applications
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? YEAR)
             GROUP BY year ORDER BY year ASC'
        );
        $stmt->execute([$userId, $years]);
        return $stmt->fetchAll();
    }

    public function topCompanies(int $userId, int $limit = 5): array {
        $stmt = $this->db->prepare(
            'SELECT company, COUNT(*) as count, MAX(status) as status
             FROM applications
             WHERE user_id = ?
             GROUP BY company
             ORDER BY count DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    // ── NEW: Conversion funnel ────────────────────────────────────────────────
    // Returns 3 funnel steps: total → interviewed → offered
    public function conversionFunnel(int $userId): array {
        $counts = $this->countByStatus($userId);
        $total       = array_sum($counts);
        $interviewed = $counts['interviewing'] + $counts['offer'];
        $offered     = $counts['offer'];

        return [
            ['label' => 'Applied',        'count' => $total,       'color' => '#1a73e8', 'status' => 'applied'],
            ['label' => 'Interviewed',    'count' => $interviewed, 'color' => '#f59e0b', 'status' => 'interviewing'],
            ['label' => 'Offer received', 'count' => $offered,     'color' => '#10b981', 'status' => 'offer'],
        ];
    }

    // ── NEW: Applications stalled for more than $days days ───────────────────
    public function stalledApps(int $userId, int $days = 7): array {
        $stmt = $this->db->prepare(
            "SELECT id, company, job_title, status, updated_at
             FROM applications
             WHERE user_id = ?
               AND status IN ('applied', 'interviewing')
               AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY updated_at ASC"
        );
        $stmt->execute([$userId, $days]);
        return $stmt->fetchAll();
    }

    // ── NEW: Best day of week (by progression rate) ───────────────────────────
    public function bestDayOfWeek(int $userId): ?string {
                $stmt = $this->db->prepare(
                        "SELECT
                             DAYNAME(created_at) AS day,
                             COUNT(*) AS total,
                             SUM(CASE WHEN status IN ('interviewing','offer') THEN 1 ELSE 0 END) AS progressed
                         FROM applications
                         WHERE user_id = ?
                         GROUP BY DAYNAME(created_at)
                         HAVING total >= 2
                         ORDER BY (SUM(CASE WHEN status IN ('interviewing','offer') THEN 1 ELSE 0 END) / COUNT(*)) DESC
                         LIMIT 1"
                );
                $stmt->execute([$userId]);
                $row = $stmt->fetch();
                return $row ? $row['day'] : null;
    }

    // ── NEW: Count apps created this calendar month ───────────────────────────
    public function countThisMonth(int $userId): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM applications
             WHERE user_id = ?
               AND MONTH(created_at) = MONTH(NOW())
               AND YEAR(created_at)  = YEAR(NOW())'
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    // ── Application events / timeline (existing) ─────────────────────────────

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