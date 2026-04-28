<?php
/**
 * EN: Handles API endpoint/business logic in `api/partnerships/DeploymentController.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/partnerships/DeploymentController.php`.
 */

class DeploymentController
{
    private PDO $conn;
    private array $allowedStatuses = ['processing', 'deployed', 'returned', 'issue', 'transferred'];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function index(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['country'])) {
            $where[] = 'wd.country = :country';
            $params['country'] = trim((string) $filters['country']);
        }
        if (!empty($filters['search'])) {
            $where[] = '(w.worker_name LIKE :search OR w.formatted_id LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }
        $wid = isset($filters['worker_id']) ? (int) $filters['worker_id'] : 0;
        if ($wid > 0) {
            $where[] = 'wd.worker_id = :worker_id';
            $params['worker_id'] = $wid;
        }
        if (!empty($filters['status'])) {
            $st = strtolower(trim((string) $filters['status']));
            if (in_array($st, $this->allowedStatuses, true)) {
                $where[] = 'wd.status = :filter_status';
                $params['filter_status'] = $st;
            }
        }
        if (!empty($filters['active_abroad'])) {
            $where[] = "wd.status IN ('processing', 'deployed', 'transferred')";
        }
        if (!empty($filters['expiring_within_days'])) {
            $days = (int) $filters['expiring_within_days'];
            if ($days > 0 && $days <= 730) {
                $where[] = 'wd.contract_end IS NOT NULL AND wd.contract_end >= CURDATE()'
                    . ' AND wd.contract_end <= DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY)';
            }
        }

        $sql = "SELECT wd.id, wd.worker_id, wd.partner_agency_id, wd.country, wd.job_title, wd.salary,
                       wd.contract_start, wd.contract_end, wd.status, wd.notes, wd.created_at,
                       COALESCE(w.worker_name, CONCAT('Worker #', wd.worker_id)) AS worker_name,
                       w.formatted_id AS worker_formatted_id,
                       pa.name AS partner_agency_name
                FROM worker_deployments wd
                LEFT JOIN workers w ON wd.worker_id = w.id
                LEFT JOIN partner_agencies pa ON wd.partner_agency_id = pa.id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY wd.id DESC';

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $payload): array
    {
        $data = $this->validateCreate($payload);
        $stmt = $this->conn->prepare(
            "INSERT INTO worker_deployments
            (worker_id, partner_agency_id, country, job_title, salary, contract_start, contract_end, status, notes)
            VALUES
            (:worker_id, :partner_agency_id, :country, :job_title, :salary, :contract_start, :contract_end, :status, :notes)"
        );
        $stmt->execute($data);
        return $this->find((int) $this->conn->lastInsertId());
    }

    public function byWorker(int $workerId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT wd.id, wd.worker_id, wd.partner_agency_id, wd.country, wd.job_title, wd.salary,
                    wd.contract_start, wd.contract_end, wd.status, wd.notes, wd.created_at,
                    pa.name AS partner_agency_name
             FROM worker_deployments wd
             LEFT JOIN partner_agencies pa ON wd.partner_agency_id = pa.id
             WHERE wd.worker_id = ?
             ORDER BY wd.id DESC"
        );
        $stmt->execute([$workerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $id, string $status): array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, $this->allowedStatuses, true)) {
            throw new InvalidArgumentException('Invalid deployment status');
        }
        $stmt = $this->conn->prepare("UPDATE worker_deployments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->find($id);
        $stmt = $this->conn->prepare('DELETE FROM worker_deployments WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) {
            try {
                $this->find($id);
            } catch (InvalidArgumentException $e) {
                return;
            }
            throw new RuntimeException('Deployment could not be deleted');
        }
    }

    public function stats(): array
    {
        $stmt = $this->conn->query(
            "SELECT
                COUNT(*) AS total_abroad,
                SUM(CASE WHEN status IN ('processing', 'deployed', 'transferred') THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS returned_count,
                SUM(CASE WHEN status = 'issue' THEN 1 ELSE 0 END) AS issue_count
             FROM worker_deployments"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total_abroad' => (int) ($row['total_abroad'] ?? 0),
            'active_count' => (int) ($row['active_count'] ?? 0),
            'returned_count' => (int) ($row['returned_count'] ?? 0),
            'issue_count' => (int) ($row['issue_count'] ?? 0),
        ];
    }

    private function find(int $id): array
    {
        $stmt = $this->conn->prepare(
            "SELECT wd.id, wd.worker_id, wd.partner_agency_id, wd.country, wd.job_title, wd.salary,
                    wd.contract_start, wd.contract_end, wd.status, wd.notes, wd.created_at,
                    COALESCE(w.worker_name, CONCAT('Worker #', wd.worker_id)) AS worker_name,
                    pa.name AS partner_agency_name
             FROM worker_deployments wd
             LEFT JOIN workers w ON wd.worker_id = w.id
             LEFT JOIN partner_agencies pa ON wd.partner_agency_id = pa.id
             WHERE wd.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Deployment not found');
        }
        return $row;
    }

    private function validateCreate(array $payload): array
    {
        $workerId = (int) ($payload['worker_id'] ?? 0);
        $partnerAgencyId = (int) ($payload['partner_agency_id'] ?? 0);
        $country = trim((string) ($payload['country'] ?? ''));
        $jobTitle = trim((string) ($payload['job_title'] ?? ''));
        if ($workerId <= 0 || $partnerAgencyId <= 0 || $country === '' || $jobTitle === '') {
            throw new InvalidArgumentException('Worker, agency, country and job title are required');
        }

        require_once __DIR__ . '/../../includes/government-labor.php';
        $govBlock = ratib_government_deploy_block_reason_pdo($this->conn, $workerId);
        if ($govBlock !== null) {
            throw new InvalidArgumentException($govBlock);
        }

        $status = strtolower(trim((string) ($payload['status'] ?? 'processing')));
        if (!in_array($status, $this->allowedStatuses, true)) {
            $status = 'processing';
        }

        return [
            'worker_id' => $workerId,
            'partner_agency_id' => $partnerAgencyId,
            'country' => mb_substr($country, 0, 100),
            'job_title' => mb_substr($jobTitle, 0, 255),
            'salary' => ($payload['salary'] ?? '') === '' ? null : (float) $payload['salary'],
            'contract_start' => $this->cleanDate($payload['contract_start'] ?? null),
            'contract_end' => $this->cleanDate($payload['contract_end'] ?? null),
            'status' => $status,
            'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
        ];
    }

    private function cleanDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}

