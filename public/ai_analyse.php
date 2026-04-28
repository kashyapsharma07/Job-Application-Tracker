<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

header('Content-Type: application/json');

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
    
    $total = $appModel->countByStatus($userId)['total'] ?? 0;
    $thisMonth = $appModel->countThisMonth($userId) ?? 0;
    
    $statuses = $appModel->countByStatus($userId);
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
    $pdfPath = __DIR__ . '/uploads/resumes/' . $resume['filename'];
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
You are a professional Resume Coach and ATS (Applicant Tracking System) expert.

Analyze the resume below and return a structured JSON report that a NON-TECHNICAL person can easily understand and act on.

=== SCORING RUBRIC (use this to calculate the score consistently) ===
Start at 100 and deduct points:
- Missing or weak professional summary: -15
- No quantified achievements (numbers, percentages, dollar amounts): -15
- Poor or missing action verbs at start of bullet points: -10
- Missing important sections (Education, Skills, Experience): -10 each
- Formatting issues (inconsistent dates, no bullet points, walls of text): -10
- Too long (more than 2 pages worth of text): -5
- Missing contact info (email, phone, LinkedIn): -5 each
- Spelling/grammar errors: -5
- No relevant keywords for the industry: -10
Minimum score is 5.

=== RESUME TEXT ===
$resumeTextTruncated

=== JOB SEARCH DATA ===
Total applications: $total
Interview rate: $interviewPct%
Offer rate: $offerPct%
Stalled applications: $stalledCount

=== INSTRUCTIONS ===
Return valid JSON with this exact structure. Give exactly 4-6 suggestions, sorted by priority (high first).

Each suggestion MUST follow this format:
- "section": The exact resume section name (e.g., "Professional Summary", "Work Experience > Software Engineer at Google", "Skills", "Education", "Contact Info", "Overall Format")
- "title": A short, clear action title that tells the user WHAT to do (e.g., "Add numbers to show your impact", "Rewrite your summary to highlight your strengths")
- "type": One of "add", "improve", "remove", "warning"
- "priority": One of "high", "medium", "low"
- "current_text": The exact text or bullet point from the resume that needs changing. If adding something new, write "Not currently in your resume."
- "suggested_text": The exact rewritten text the user should replace it with, or the new text to add. Write it word-for-word so the user can copy-paste it.
- "explanation": In 1-2 simple sentences, explain WHY this change helps in plain English. Imagine you are talking to a friend who has never written a resume before.

{
  "score": <number 5-100>,
  "summary": "<2-3 sentences: What is the overall impression of this resume? What are the TOP 2 things to fix first? Write as if talking to a friend.>",
  "suggestions": [
    {
      "section": "<resume section>",
      "title": "<clear action>",
      "type": "<add|improve|remove|warning>",
      "priority": "<high|medium|low>",
      "current_text": "<exact text from resume or 'Not currently in your resume.'>",
      "suggested_text": "<exact replacement text, ready to copy-paste>",
      "explanation": "<why this matters, in plain English>"
    }
  ]
}
PROMPT;
    
    
    // ===== CALL OPENROUTER API =====
    $apiKey = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : '';
    $model = defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : '';
    
    if (!$apiKey || !$model) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'API configuration error.']);
        exit;
    }
    
    $client = curl_init();
    curl_setopt_array($client, [
        CURLOPT_URL => 'https://openrouter.ai/api/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a resume analysis API. You MUST respond with ONLY raw JSON. No markdown, no code fences, no explanations before or after the JSON. Just the pure JSON object.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.0,
            'top_p' => 0.1,
            'response_format' => ['type' => 'json_object']
        ]),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($client);
    $httpCode = curl_getinfo($client, CURLINFO_HTTP_CODE);
    $curlError = curl_error($client);
    curl_close($client);
    
    if ($curlError) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Network error. Please ensure your internet connection is active and try again.']);
        exit;
    }
    
    if (!$response || $httpCode !== 200) {
        http_response_code(500);
        $errorMsg = 'AI service error. Please try again in a moment.';
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Invalid response from AI service.']);
        exit;
    }
    
    $aiText = trim($data['choices'][0]['message']['content']);
    
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
        echo json_encode(['success' => false, 'error' => 'AI response parsing failed. Please try again.']);
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
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred. Please try again.'
    ]);
    error_log('AI Analyse Error: ' . $e->getMessage());
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
