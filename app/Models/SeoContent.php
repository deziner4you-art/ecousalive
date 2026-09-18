<?php

/*
=====================================================
SEO CONTENT MODEL
Stores A+ banner alt text, infographic descriptions,
and backend search terms per task.
Table: eco_seo_content (JSON storage)
=====================================================
*/

class SeoContent {

    public static function get(int $taskId): array {
        $stmt = db()->prepare("SELECT * FROM eco_seo_content WHERE task_id=?");
        $stmt->execute([$taskId]);
        $row = $stmt->fetch();
        if(!$row) return self::emptyStructure();

        return [
            'id'                   => $row['id'],
            'task_id'              => $row['task_id'],
            'banners'              => json_decode($row['banners']               ?? '[]', true) ?: self::emptyBanners(),
            'infographics'         => json_decode($row['infographics']          ?? '[]', true) ?: self::emptyInfographics(),
            'backend_search_terms' => $row['backend_search_terms'] ?? '',
            'seo_notes'            => $row['seo_notes'] ?? '',
            'updated_at'           => $row['updated_at'] ?? null,
        ];
    }

    public static function save(int $taskId, array $data): void {
        $banners              = json_encode($data['banners']      ?? self::emptyBanners());
        $infographics         = json_encode($data['infographics'] ?? self::emptyInfographics());
        $backendSearchTerms   = trim($data['backend_search_terms'] ?? '');
        $seoNotes             = trim($data['seo_notes']            ?? '');

        db()->prepare("
            INSERT INTO eco_seo_content
                (task_id, banners, infographics, backend_search_terms, seo_notes)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                banners=VALUES(banners),
                infographics=VALUES(infographics),
                backend_search_terms=VALUES(backend_search_terms),
                seo_notes=VALUES(seo_notes),
                updated_at=NOW()
        ")->execute([$taskId, $banners, $infographics, $backendSearchTerms, $seoNotes]);
    }

    /* Empty structures for new tasks */
    public static function emptyBanners(): array {
        $banners = [];
        for($i = 1; $i <= 4; $i++){
            $banners[] = [
                'id'         => $i,
                'desktop'    => '',
                'mobile'     => '',
                'sub_heading'=> '',
                'heading'    => '',
                'body_text'  => '',
            ];
        }
        return $banners;
    }

    public static function emptyInfographics(): array {
        $pages = [];
        for($i = 1; $i <= 8; $i++){
            $pages[] = ['page' => $i, 'content' => ''];
        }
        return $pages;
    }

    public static function emptyStructure(): array {
        return [
            'id'                   => null,
            'task_id'              => null,
            'banners'              => self::emptyBanners(),
            'infographics'         => self::emptyInfographics(),
            'backend_search_terms' => '',
            'seo_notes'            => '',
            'updated_at'           => null,
        ];
    }

}
