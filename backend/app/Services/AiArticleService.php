<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiArticleService
{
    /**
     * Generate a full article from a keyword/title.
     */
    public function generateArticle(string $keyword, string $provider = 'chatgpt', ?int $wordCount = null): array
    {
        $wordCount = $wordCount ?: (int) Setting::get('ai_word_count', 1500);
        $language = Setting::get('ai_language', 'vi');

        $prompt = $this->buildPrompt($keyword, $wordCount, $language);

        if ($provider === 'gemini') {
            $content = $this->callGemini($prompt);
        } else {
            $content = $this->callChatGPT($prompt);
        }

        return $this->parseArticleResponse($content, $keyword);
    }

    /**
     * Generate an image using Gemini's image generation.
     */
    public function generateImage(string $prompt, string $provider = 'gemini'): ?string
    {
        try {
            if ($provider === 'gemini') {
                return $this->callGeminiImage($prompt);
            }
            // ChatGPT/DALL-E
            return $this->callDallE($prompt);
        } catch (\Exception $e) {
            Log::error('AI Image generation failed: ' . $e->getMessage());
            return null;
        }
    }

    private function buildPrompt(string $keyword, int $wordCount, string $language): string
    {
        $lang = $language === 'vi' ? 'tieng Viet' : 'English';

        return <<<PROMPT
Ban la mot chuyen gia viet bai SEO cho website ban PC va linh kien cong nghe.

Hay viet mot bai viet hoan chinh ve chu de: "{$keyword}"

Yeu cau:
- Viet bang {$lang}
- Do dai khoang {$wordCount} tu
- Co cau truc HTML voi cac the h2, h3, p, ul, li, strong
- Bai viet phai co it nhat 3-5 phan (section) voi cac heading h2
- Moi phan co 2-3 doan van
- Bao gom danh sach gach dau dong khi phu hop
- Noi dung phai chinh xac ve mat ky thuat cho linh vuc cong nghe/PC
- Toi uu SEO: tu khoa tu nhien, khong spam
- Khong su dung markdown, chi HTML thuan

Tra ve JSON voi format:
{
  "title": "Tieu de bai viet (55-60 ky tu)",
  "slug": "tieu-de-bai-viet-seo-friendly",
  "excerpt": "Mo ta ngan 150-160 ky tu cho SEO",
  "body": "<h2>...</h2><p>...</p>...",
  "meta_title": "Meta title (55-60 ky tu)",
  "meta_description": "Meta description (150-160 ky tu)",
  "image_prompt": "English prompt to generate a featured image for this article, describe visual scene"
}

Chi tra ve JSON, khong them gi khac.
PROMPT;
    }

    private function callChatGPT(string $prompt): string
    {
        $apiKey = Setting::get('chatgpt_api_key');
        $model = Setting::get('chatgpt_model', 'gpt-4o-mini');

        if (!$apiKey) {
            throw new \Exception('ChatGPT API Key chua duoc cau hinh. Vui long cap nhat trong Cai dat.');
        }

        $response = Http::timeout(120)->withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Ban la chuyen gia viet noi dung SEO cho nganh cong nghe. Luon tra ve JSON hop le.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 4000,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'API call failed');
            throw new \Exception("ChatGPT API Error: {$error}");
        }

        return $response->json('choices.0.message.content', '');
    }

    private function callGemini(string $prompt): string
    {
        $apiKey = Setting::get('gemini_api_key');
        $model = Setting::get('gemini_model', 'gemini-2.5-flash');

        if (!$apiKey) {
            throw new \Exception('Gemini API Key chua duoc cau hinh. Vui long cap nhat trong Cai dat.');
        }

        $response = Http::timeout(120)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 4000,
                    'responseMimeType' => 'application/json',
                ],
            ]
        );

        if ($response->failed()) {
            $error = $response->json('error.message', 'API call failed');
            throw new \Exception("Gemini API Error: {$error}");
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    private function callGeminiImage(string $prompt): ?string
    {
        $apiKey = Setting::get('gemini_api_key');
        if (!$apiKey) return null;

        $response = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$apiKey}",
            [
                'contents' => [
                    ['parts' => [['text' => "Generate an image: {$prompt}"]]],
                ],
                'generationConfig' => [
                    'responseModalities' => ['TEXT', 'IMAGE'],
                ],
            ]
        );

        if ($response->successful()) {
            $parts = $response->json('candidates.0.content.parts', []);
            foreach ($parts as $part) {
                if (isset($part['inlineData'])) {
                    $data = $part['inlineData']['data'];
                    $mime = $part['inlineData']['mimeType'] ?? 'image/png';
                    $ext = $mime === 'image/jpeg' ? 'jpg' : 'png';
                    $filename = 'ai-images/' . Str::random(20) . '.' . $ext;
                    $path = storage_path('app/public/' . $filename);
                    
                    if (!is_dir(dirname($path))) {
                        mkdir(dirname($path), 0755, true);
                    }
                    
                    file_put_contents($path, base64_decode($data));
                    return '/storage/' . $filename;
                }
            }
        }

        return null;
    }

    private function callDallE(string $prompt): ?string
    {
        $apiKey = Setting::get('chatgpt_api_key');
        if (!$apiKey) return null;

        $response = Http::timeout(60)->withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->post('https://api.openai.com/v1/images/generations', [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1792x1024',
            'quality' => 'standard',
        ]);

        if ($response->successful()) {
            $imageUrl = $response->json('data.0.url');
            if ($imageUrl) {
                // Download and save locally
                $imageData = Http::timeout(30)->get($imageUrl)->body();
                $filename = 'ai-images/' . Str::random(20) . '.png';
                $path = storage_path('app/public/' . $filename);
                
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0755, true);
                }
                
                file_put_contents($path, $imageData);
                return '/storage/' . $filename;
            }
        }

        return null;
    }

    private function parseArticleResponse(string $raw, string $keyword): array
    {
        // Clean markdown code fences if present
        $raw = trim($raw);
        $raw = preg_replace('/^```json\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/i', '', $raw);

        $data = json_decode($raw, true);

        if (!$data || !isset($data['title'])) {
            throw new \Exception('Khong the phan tich phan hoi tu AI. Vui long thu lai.');
        }

        return [
            'title' => $data['title'] ?? $keyword,
            'slug' => $data['slug'] ?? Str::slug($keyword),
            'excerpt' => $data['excerpt'] ?? '',
            'body' => $data['body'] ?? '',
            'meta_title' => $data['meta_title'] ?? $data['title'] ?? $keyword,
            'meta_description' => $data['meta_description'] ?? $data['excerpt'] ?? '',
            'image_prompt' => $data['image_prompt'] ?? "Technology article about {$keyword}",
        ];
    }
}
