<?php
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

header('Content-Type: application/json');
set_time_limit(120);

// Premium check
$user = Auth::user();
$isPremium = (bool)($user['is_premium'] ?? false);

if (!$isPremium) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'AI Coach is a premium feature. Please upgrade to use this.']);
    exit;
}

try {
    $userId = Auth::id();
    
    // ===== FETCH ANALYTICS DATA =====
    $appModel = new Application();
    
    $statuses = $appModel->countByStatus($userId);
    $total = array_sum($statuses);
    $thisMonth = $appModel->countThisMonth($userId) ?? 0;
    
    $wishlist = $statuses['wishlist'] ?? 0;
    $applied = $statuses['applied'] ?? 0;
    $interviewing = $statuses['interviewing'] ?? 0;
    $offer = $statuses['offer'] ?? 0;
    $rejected = $statuses['rejected'] ?? 0;
    
    $interviewPct = $total > 0 ? round(($interviewing / $total) * 100, 1) : 0;
    $offerPct = $total > 0 ? round(($offer / $total) * 100, 1) : 0;
    
    $stalledApps = $appModel->stalledApps($userId, 7);
    $stalledCount = count($stalledApps);
    
    $bestDay = $appModel->bestDayOfWeek($userId) ?? 'N/A';
    
    $topComps = $appModel->topCompanies($userId, 5);
    $topCompanies = !empty($topComps) ? implode(', ', array_map(fn($c) => $c['company'], $topComps)) : 'N/A';
    
    $monthlyData = $appModel->countByMonth($userId, 6);
    $monthlyStr = !empty($monthlyData) ? json_encode($monthlyData) : 'N/A';
    
    $funnel = $appModel->conversionFunnel($userId);
    $funnelStages = !empty($funnel) ? json_encode($funnel) : 'N/A';
    
    // ===== FETCH RESUME =====
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM resumes WHERE user_id = ? AND is_default = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $resume = $stmt->fetch();
    
    if (!$resume) {
        $stmt = $db->prepare('SELECT * FROM resumes WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$userId]);
        $resume = $stmt->fetch();
    }
    
    if (!$resume) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No resume found. Upload a resume first to get AI insights.']);
        exit;
    }
    
    // ===== EXTRACT PDF TEXT =====
    $pdfPath = UPLOAD_PATH . $resume['filename'];
    if (!file_exists($pdfPath)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Resume file not found.']);
        exit;
    }
    
    $resumeText = extractPdfText($pdfPath);
    if (!$resumeText) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Could not extract text from resume PDF. Ensure it is a valid PDF file.']);
        exit;
    }
    
    // ===== BUILD PROMPT =====
    // Truncate resume text to avoid token limit issues (keep first 6000 chars)
    $resumeTextTruncated = mb_substr($resumeText, 0, 6000);
    
    $prompt = <<<PROMPT
You are a brutal, highly critical FAANG Recruiter and ATS (Applicant Tracking System) expert. Your job is to rip this resume apart constructively. Do not sugarcoat your feedback. 

Analyze the resume below and return a structured JSON report that a NON-TECHNICAL person can understand. 

=== STRICT SCORING RUBRIC ===
Do NOT default to 80-85. Be brutally honest. Most resumes are average (50-65).
- 90-100: Exceptional. Perfect formatting, EVERY bullet has strong quantified metrics ($, %, #), zero fluff.
- 70-89: Good, but missing some metrics. Uses weak verbs in a few places. Needs better impact statements.
- 50-69: Average. Lists responsibilities instead of achievements. Missing numbers. Too much text.
- Below 50: Poor. Missing core sections, bad formatting, no metrics, typos.

=== RULES FOR SUGGESTIONS ===
1. You MUST find exactly 4-6 specific things to fix.
2. Focus on IMPACT. If a bullet says "Responsible for managing a team", your suggested text MUST rewrite it using the STAR method (e.g., "Directed a cross-functional team of 12 engineers, increasing delivery speed by 30%").
3. Do NOT give vague advice like "Add more numbers". You must literally rewrite the sentence for them in `suggested_text`.
4. Be specific and harsh if necessary. 

=== RESUME TEXT ===
$resumeTextTruncated

=== JOB SEARCH DATA ===
Total applications: $total
Interview rate: $interviewPct%
Offer rate: $offerPct%
Stalled applications: $stalledCount

=== INSTRUCTIONS ===
Return valid JSON with this exact structure:

{
  "score": <number 5-100 based strictly on the rubric>,
  "summary": "<2-3 sentences: Brutally honest overall impression. What are the biggest red flags? Write as if talking directly to the candidate.>",
  "suggestions": [
    {
      "section": "<resume section (e.g., Work Experience, Professional Summary)>",
      "title": "<clear action, e.g., Rewrite bullet to include metrics>",
      "type": "<add|improve|remove|warning>",
      "priority": "<high|medium|low>",
      "current_text": "<exact text from resume or 'Not currently in your resume.'>",
      "suggested_text": "<exact replacement text, ready to copy-paste. Must be a vast improvement.>",
      "explanation": "<why this specific change gets past ATS and impresses recruiters>"
    }
  ]
}
PROMPT;
    
    
    // ===== CALL GOOGLE GEMINI API =====
    $geminiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (empty($geminiKey)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Gemini API key is missing. Please check your config or .env configuration.']);
        exit;
    }
    
    $aiText = '';
    $geminiModels = ['gemini-2.5-flash', 'gemini-1.5-flash', 'gemini-2.5-flash-lite', 'gemini-1.5-flash-8b'];

    $lastGeminiError = '';
    foreach ($geminiModels as $gModel) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $gModel . ':generateContent?key=' . $geminiKey;
        $payload = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => "You are a resume analysis API. Respond strictly with raw JSON object matching the requested schema. Do not output markdown backticks.\n\n" . $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json'
            ]
        ]);

        $client = curl_init();
        curl_setopt_array($client, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $resp = curl_exec($client);
        $code = curl_getinfo($client, CURLINFO_HTTP_CODE);
        curl_close($client);

        if ($resp && $code === 200) {
            $decoded = json_decode($resp, true);
            if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                $aiText = trim($decoded['candidates'][0]['content']['parts'][0]['text']);
                break;
            }
        } else {
            $errData = json_decode($resp, true);
            $lastGeminiError = $errData['error']['message'] ?? ('HTTP ' . $code);
        }
    }

    if (empty($aiText)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Gemini API Error: ' . ($lastGeminiError ?: 'Analysis failed.')]);
        exit;
    }
    
    // Strip markdown code fences if present (```json ... ``` or ``` ... ```)
    $aiText = preg_replace('/^```(?:json)?\s*\n?/i', '', $aiText);
    $aiText = preg_replace('/\n?```\s*$/', '', $aiText);
    $aiText = trim($aiText);
    
    // Try direct JSON decode first
    $suggestions = json_decode($aiText, true);
    
    // If that fails, try to extract JSON object from the response
    if (!$suggestions || !isset($suggestions['summary']) || !isset($suggestions['score'])) {
        // Look for the first { ... } block in the response
        if (preg_match('/\{[\s\S]*\}/', $aiText, $matches)) {
            $suggestions = json_decode($matches[0], true);
        }
    }
    
    if (!$suggestions || !isset($suggestions['summary']) || !isset($suggestions['score'])) {
        http_response_code(500);
        error_log('AI raw response for debugging: ' . substr($aiText, 0, 2000));
        echo json_encode(['success' => false, 'error' => 'AI response parsing failed. Raw response: ' . substr($aiText, 0, 300)]);
        exit;
    }
    
    // ===== CACHE RESULT =====
    $stmt = $db->prepare('
        INSERT INTO ai_suggestions (user_id, suggestions, score, summary, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            suggestions = VALUES(suggestions),
            score = VALUES(score),
            summary = VALUES(summary),
            updated_at = NOW()
    ');
    $stmt->execute([
        $userId,
        json_encode($suggestions['suggestions']),
        $suggestions['score'],
        $suggestions['summary']
    ]);
    
    // ===== RETURN SUCCESS =====
    echo json_encode([
        'success' => true,
        'data' => [
            'summary' => $suggestions['summary'],
            'score' => $suggestions['score'],
            'suggestions' => $suggestions['suggestions'],
            'cached_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('AI Analyse Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
}

// ===== HELPER: EXTRACT PDF TEXT =====
function extractPdfText(string $pdfPath): ?string {
    // Try Smalot PDF Parser first (robust PHP solution)
    if (class_exists('\\Smalot\\PdfParser\\Parser')) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($pdfPath);
            $text = $pdf->getText();
            if ($text && strlen(trim($text)) > 10) {
                return trim($text);
            }
        } catch (\Exception $e) {
            error_log('Smalot PDF Parser Error: ' . $e->getMessage());
        }
    }

    // Try pdftotext command
    if (command_exists('pdftotext')) {
        $output = shell_exec('pdftotext ' . escapeshellarg($pdfPath) . ' -');
        if ($output && strlen(trim($output)) > 10) {
            return trim($output);
        }
    }
    
    // Fallback: extract readable ASCII from raw PDF bytes
    $raw = file_get_contents($pdfPath);
    if (!$raw) {
        return null;
    }
    
    // Remove binary noise, keep readable text
    $text = '';
    for ($i = 0; $i < strlen($raw); $i++) {
        $byte = ord($raw[$i]);
        if (($byte >= 32 && $byte <= 126) || $byte === 9 || $byte === 10 || $byte === 13) {
            $text .= $raw[$i];
        }
    }
    
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    
    return strlen(trim($text)) > 10 ? trim($text) : null;
}

function command_exists(string $cmd): bool {
    $output = shell_exec('which ' . escapeshellarg($cmd) . ' 2>/dev/null');
    return !empty($output);
}
