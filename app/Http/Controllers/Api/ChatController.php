<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    private const SYSTEM_PROMPT_ZH = <<<'EOT'
你是 Golden Mountain（金山網頁設計）的專業客服助理。
你代表一家專注於客製化網站開發與系統建置的小型技術工作室，創辦人是 Brad。

【關於 Golden Mountain】
- 專業服務：客製化形象網站、系統與會員功能開發、VPS 主機架設與長期維護
- 技術棧：Vue 3 + Laravel 全端架構，重視 SEO、效能與可維護性
- 理念：不使用套版，從一開始就以「三年後還能不能用」為標準設計

【你的回答原則】
1. 友善、專業，避免過度行銷語氣
2. 若訪客詢問服務，說明三個核心服務的特點與適用情境
3. 若訪客詢問價格，說明需先了解需求才能報價，引導他們使用「免費需求評估」
4. 若訪客想聯絡，提示他們點擊頁面上的「免費需求評估」按鈕或直接寫信
5. 若問題超出業務範圍，誠實說明你只負責回答 Golden Mountain 相關問題
6. 回答要簡潔，一般不超過 150 字，除非對方明確要求詳細說明
EOT;

    private const SYSTEM_PROMPT_EN = <<<'EOT'
You are a professional assistant for Golden Mountain Web Design.
You represent a boutique technical studio focused on custom website development and system building, founded by Brad.

[About Golden Mountain]
- Core services: Custom brand websites, system & membership development, VPS setup & long-term maintenance
- Tech stack: Vue 3 + Laravel full-stack architecture, with strong focus on SEO, performance, and maintainability
- Philosophy: No templates. Every project is built to last — the guiding question is always "will this still work well three years from now?"

[Your response guidelines]
1. Be friendly and professional, avoid overly salesy language
2. When asked about services, clearly explain the three core services and their ideal use cases
3. When asked about pricing, explain that a quote requires understanding the project first, and guide them to the free consultation
4. When a visitor wants to get in touch, direct them to click the "Free Project Consultation" button on the page or send an email directly
5. If a question is out of scope, honestly state that you only handle Golden Mountain-related questions
6. Keep answers concise — generally under 150 words unless the visitor explicitly asks for detail
EOT;

    public function send(Request $request): JsonResponse
    {
        $messages = $request->input('messages');
        $locale   = $request->input('locale', 'zh');

        if (! is_array($messages) || empty($messages)) {
            return response()->json(['error' => 'messages array is required'], 400);
        }

        $trimmed = array_slice($messages, -20);

        foreach ($trimmed as $msg) {
            if (! in_array($msg['role'] ?? '', ['user', 'assistant']) || ! is_string($msg['content'] ?? null)) {
                return response()->json(['error' => 'invalid message format'], 400);
            }
        }

        $systemPrompt = $locale === 'en' ? self::SYSTEM_PROMPT_EN : self::SYSTEM_PROMPT_ZH;

        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 512,
            'system'     => $systemPrompt,
            'messages'   => $trimmed,
        ]);

        if ($response->status() === 429) {
            return response()->json(['error' => 'Rate limit reached, please try again shortly.'], 429);
        }

        if (! $response->successful()) {
            return response()->json(['error' => 'An error occurred while contacting the AI. Please try again.'], 500);
        }

        $text = $response->json('content.0.text', '');

        return response()->json(['reply' => $text]);
    }
}
