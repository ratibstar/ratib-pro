<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\Database;

/** Phase WEBSITE-04 — Media folders + optional image optimization + lazy-ready paths. */
final class WebsiteMediaManagerService
{
    private TenantWebsiteRepository $repo;
    private TenantMediaService $media;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->media = new TenantMediaService($this->repo);
    }

    /** @return list<array<string, mixed>> */
    public function folders(?int $parentId = null): array
    {
        [$where, $params] = $this->repo->companyWhere();
        if ($parentId === null) {
            $sql = "SELECT * FROM rateb_website_media_folders WHERE {$where} AND parent_id IS NULL ORDER BY name ASC";
        } else {
            $params['pid'] = $parentId;
            $sql = "SELECT * FROM rateb_website_media_folders WHERE {$where} AND parent_id = :pid ORDER BY name ASC";
        }

        return $this->repo->fetchAll($sql, $params);
    }

    public function createFolder(string $name, ?int $parentId = null): int
    {
        $cid = $this->repo->companyId();
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', strtolower(trim($name))) ?: 'folder';
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_website_media_folders (company_id, parent_id, name, slug) VALUES (:cid, :pid, :name, :slug)'
        )->execute([
            'cid' => $cid,
            'pid' => $parentId,
            'name' => trim($name),
            'slug' => $slug,
        ]);

        return (int) $db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listMedia(?int $folderId = null, int $limit = 100): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['lim'] = max(1, min(500, $limit));
        if ($folderId === null) {
            $sql = "SELECT * FROM rateb_cms_media WHERE {$where} ORDER BY id DESC LIMIT {$params['lim']}";
            unset($params['lim']);

            return $this->repo->fetchAll(
                "SELECT * FROM rateb_cms_media WHERE {$where} ORDER BY id DESC LIMIT " . max(1, min(500, $limit)),
                $params
            );
        }
        $params['fid'] = $folderId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_media WHERE {$where} AND folder_id = :fid ORDER BY id DESC LIMIT " . max(1, min(500, $limit)),
            $params
        );
    }

    /**
     * @return array{ok:bool,path?:string,id?:int,error?:string}
     */
    public function upload(array $file, ?int $folderId = null, ?int $userId = null): array
    {
        $result = $this->media->upload($file, $userId);
        if (empty($result['ok']) || empty($result['id'])) {
            return $result;
        }
        $id = (int) $result['id'];
        if ($folderId !== null && $folderId > 0) {
            $this->repo->execute(
                'UPDATE rateb_cms_media SET folder_id = :fid WHERE id = :id AND company_id = :cid',
                ['fid' => $folderId, 'id' => $id, 'cid' => $this->repo->companyId()]
            );
        }
        $path = (string) ($result['path'] ?? '');
        if ($path !== '') {
            $this->optimizeImage(RATEB_STORAGE_PATH . '/' . preg_replace('#^storage/#', '', $path));
        }

        return $result;
    }

    private function optimizeImage(string $absolute): void
    {
        if (!is_file($absolute) || !function_exists('imagecreatefromstring')) {
            return;
        }
        $bin = @file_get_contents($absolute);
        if ($bin === false || $bin === '') {
            return;
        }
        $img = @imagecreatefromstring($bin);
        if ($img === false) {
            return;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $max = 1920;
        if ($w > $max || $h > $max) {
            $scale = min($max / max(1, $w), $max / max(1, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($dst !== false) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $dst;
            }
        }
        $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            imagepng($img, $absolute, 6);
        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
            imagewebp($img, $absolute, 82);
        } else {
            imagejpeg($img, $absolute, 82);
        }
        imagedestroy($img);
    }
}
