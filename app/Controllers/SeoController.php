<?php

/*
=====================================================
SEO CONTROLLER
SEO content form: save, get, AI generate
=====================================================
*/

class SeoController {

    /* ── Get SEO content for a task ────────────── */
    public function getContent(): void {
        AuthMiddleware::requireAuth();
        $user   = current_user();
        $taskId = intval($_GET['task_id'] ?? 0);
        if(!$taskId) json_error('Invalid task ID');

        $allowed = ['seo_manager','administrator','eco_listing'];
        if(!in_array($user['role'], $allowed)) json_error('Unauthorized', 403);

        $data = SeoContent::get($taskId);
        json_success(['data' => $data]);
    }

    /* ── Save SEO content ───────────────────────── */
    public function saveContent(): void {
        AuthMiddleware::requireAuth();
        verify_csrf();
        $user   = current_user();
        $taskId = intval($_POST['task_id'] ?? 0);
        if(!$taskId) json_error('Invalid task ID');

        if($user['role'] !== 'seo_manager' && !is_admin()) json_error('Unauthorized', 403);

        $rawData = $_POST['seo_data'] ?? '{}';
        $data    = json_decode($rawData, true);
        if(!is_array($data)) json_error('Invalid data format');

        SeoContent::save($taskId, $data);
        json_success(['message' => 'SEO content saved!']);
    }

    /* ── AI Generate via Gemini / OpenAI ────────── */
    public function generateAI(): void {
        AuthMiddleware::requireAuth();
        verify_csrf();
        $user = current_user();

        $allowed = ['seo_manager','administrator'];
        if(!in_array($user['role'], $allowed)) json_error('Unauthorized', 403);

        $geminiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
        $openAIKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';

        if(!$geminiKey && !$openAIKey) {
            json_error('AI API keys not configured. Add GEMINI_API_KEY or OPENAI_API_KEY to .env');
        }

        $section      = trim($_POST['section']       ?? '');
        $productTitle = trim($_POST['product_title'] ?? '');
        $context      = trim($_POST['context']        ?? '');
        $imgBase64    = trim($_POST['image_base64']  ?? '');
        $imgLink      = trim($_POST['image_link']    ?? '');

        if(!$productTitle) json_error('Product title required');

        $prompt = self::buildPrompt($section, $productTitle, $context);

        // 1. Handle image if present
        $base64Image = null;
        $mimeType = null;

        if ($imgBase64) {
            if (preg_match('/^data:(image\/[a-zA-Z0-9+.-]+);base64,(.+)$/', $imgBase64, $matches)) {
                $mimeType = $matches[1];
                $base64Image = $matches[2];
            }
        } elseif ($imgLink) {
            $fetched = self::fetchImageAsBase64($imgLink);
            if ($fetched) {
                $mimeType = $fetched['mimeType'];
                $base64Image = $fetched['data'];
            }
        }

        // 2. Call the API (Prefer Gemini)
        if ($geminiKey) {
            $response = self::callGemini($geminiKey, $prompt, $base64Image, $mimeType);
            if (!$response['ok']) json_error($response['error'] ?? 'Gemini generation failed');
            json_success(['generated' => $response['text']]);
        } else {
            if ($base64Image) {
                json_error('OpenAI fallback does not support image analysis. Please configure GEMINI_API_KEY in .env.');
            }
            $response = self::callOpenAI($openAIKey, $prompt);
            if (!$response['ok']) json_error($response['error'] ?? 'OpenAI generation failed');
            json_success(['generated' => $response['text']]);
        }
    }

    /* ── Build prompt per section ────────────────── */
    private static function buildPrompt(string $section, string $product, string $context): string {
        $base = "You are an Amazon A+ content SEO expert. Product: \"{$product}\".\n";
        $base .= "CRITICAL: The content MUST comply with Amazon Listing and A+ Content Policies. It must sound natural and human-written to avoid being flagged as bot-generated.\n";
        $base .= "STRICT RULE: Do NOT use any hyphens ('-') in the output. For example, write 'spill free' instead of 'spill-free', 'snap on' instead of 'snap-on', 'leak resistant' instead of 'leak-resistant', 'tight fitting' instead of 'tight-fitting', etc. Replace all hyphens with normal spaces.\n";
        if($context && strpos($context, 'Generating...') === false) {
            $base .= "Existing content for reference:\n{$context}\n\n";
        }

        switch($section){
            case 'banner_desktop':
                return $base . "Write a concise, keyword-rich alt text / image description for a DESKTOP A+ banner image. Max 100 characters. Plain text only, no quotes, no markdown.";
            case 'banner_mobile':
                return $base . "Write a concise alt text for a MOBILE A+ banner image. Max 80 characters. Plain text only, no quotes.";
            case 'banner_heading':
                return $base . "Write a compelling, catchy headline based on this image and product. Max 140 characters. Plain text only.";
            case 'banner_subheading':
                return $base . "Write a short banner sub-heading based on this image and product. Max 80 characters. Plain text only.";
            case 'banner_body':
                return $base . "Write banner body text based on this image and product. 2-3 sentences highlighting key features. Max 250 characters. Plain text only.";
            case 'infographic':
                return $base . "Write a descriptive, keyword-rich alt text for this infographic image. Max 150 characters. Plain text only.";
            case 'backend_search_terms':
                return $base . "Generate Amazon backend search terms. Comma-separated keywords, max 250 bytes total. Focus on search terms customers use. Plain text only.";
            default:
                return $base . "Write relevant SEO content for this Amazon product. Plain text only.";
        }
    }

    /* ── Fetch Image from URL / Google Drive ─────── */
    private static function fetchImageAsBase64(string $url): ?array {
        // Resolve Google Drive sharing link
        if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $fileId = $matches[1];
            $url = "https://drive.google.com/uc?export=download&id=" . $fileId;
        } elseif (preg_match('/drive\.google\.com\/open\?id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $fileId = $matches[1];
            $url = "https://drive.google.com/uc?export=download&id=" . $fileId;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36'
        ]);

        $data = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 200 || !$data) return null;

        $mimeType = 'image/jpeg';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_buffer($finfo, $data);
            finfo_close($finfo);
            if ($detected && strpos($detected, 'image/') === 0) {
                $mimeType = $detected;
            }
        } else {
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if ($ext === 'png') $mimeType = 'image/png';
            elseif ($ext === 'webp') $mimeType = 'image/webp';
            elseif ($ext === 'gif') $mimeType = 'image/gif';
        }

        return [
            'data'     => base64_encode($data),
            'mimeType' => $mimeType
        ];
    }

    /* ── Call Gemini API ─────────────────────────── */
    private static function callGemini(string $apiKey, string $prompt, ?string $base64Image = null, ?string $mimeType = null): array {
        $model = 'gemini-3.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . $apiKey;

        $parts = [];
        $parts[] = ['text' => $prompt];

        if ($base64Image && $mimeType) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $mimeType,
                    'data'     => $base64Image
                ]
            ];
        }

        $payload = [
            'contents' => [
                [
                    'parts' => $parts
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.4,
                'topP'            => 0.95,
                'topK'            => 40,
                'maxOutputTokens' => 1024,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 35,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response) return ['ok' => false, 'error' => 'Gemini API connection failed'];

        $resData = json_decode($response, true);
        if ($statusCode !== 200) {
            $errMsg = $resData['error']['message'] ?? 'Unknown Gemini API error';
            return ['ok' => false, 'error' => $errMsg];
        }

        $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (empty($text)) return ['ok' => false, 'error' => 'Gemini returned empty candidate'];

        return ['ok' => true, 'text' => trim($text)];
    }

    /* ── Call OpenAI API ─────────────────────────── */
    private static function callOpenAI(string $apiKey, string $prompt): array {
        $payload = json_encode([
            'model'    => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a professional Amazon SEO content writer. Always respond with plain text only — no JSON, no markdown, no extra formatting.'],
                ['role' => 'user',   'content' => $prompt],
            ],
            'max_tokens'  => 300,
            'temperature' => 0.7,
        ]);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if(!$result) return ['ok' => false, 'error' => 'API connection failed'];

        $json = json_decode($result, true);
        if($httpCode !== 200 || empty($json['choices'][0]['message']['content'])){
            $errMsg = $json['error']['message'] ?? 'Unknown API error';
            return ['ok' => false, 'error' => $errMsg];
        }

        return ['ok' => true, 'text' => trim($json['choices'][0]['message']['content'])];
    }

}
