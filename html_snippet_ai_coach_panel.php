<!-- AI Career Coach Panel -->
<div class="card ai-coach-card mb-4" id="ai-coach-panel">
  <div class="card-header ai-coach-header">
    <i class="bi bi-cpu"></i> AI Career Coach
    <small class="ai-cached-label" style="display: none; font-size: 12px; color: rgba(255,255,255,0.7);"></small>
  </div>
  <div class="card-body">
    <?php if (!$isPremium): ?>
    <!-- Premium Gate -->
    <div class="ai-upgrade-prompt">
      <div class="ai-upgrade-icon">
        <i class="bi bi-lock-fill"></i>
      </div>
      <h5>Premium Feature</h5>
      <p>Get AI-powered insights into your resume and job search performance. Our career coach analyzes your actual data to give you specific, actionable fixes.</p>
      <a href="<?= APP_URL ?>/go-premium.php" class="btn btn-primary">Upgrade to Premium</a>
    </div>
    <?php else: ?>
    <!-- Analysis Panel -->
    <div class="ai-analysis-container">
      <div class="ai-score-ring-container"></div>
      
      <div class="ai-summary-container">
        <div class="ai-summary"></div>
      </div>
      
      <div class="ai-suggestions-list"></div>
      
      <button type="button" id="btn-ai-analyse" class="btn btn-primary mt-4" style="width: 100%;">
        <i class="bi bi-cpu"></i> Analyse My Resume
      </button>
    </div>
    
    <?php
    // Load cached data if available
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT suggestions, score, summary, updated_at FROM ai_suggestions WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $cached = $stmt->fetch();
    
    if ($cached) {
        $suggestions = json_decode($cached['suggestions'], true) ?? [];
        $score = $cached['score'] ?? 0;
        $summary = $cached['summary'] ?? '';
        $cachedAt = $cached['updated_at'];
        ?>
        <script>
        window.AI_CACHED_DATA = {
            summary: <?= json_encode($summary) ?>,
            score: <?= (int)$score ?>,
            suggestions: <?= json_encode($suggestions) ?>
        };
        </script>
        <input type="hidden" id="ai-cached-at" value="<?= h($cachedAt) ?>">
        <?php
    }
    ?>
    
    <?php endif; ?>
  </div>
</div>

<style>
/* AI Coach Styling */
.ai-coach-card {
  border: 1px solid var(--border);
  background: linear-gradient(135deg, rgba(var(--brand-rgb, 25, 103, 210), 0.03) 0%, transparent 100%);
  backdrop-filter: blur(10px);
  border-radius: 12px;
  overflow: hidden;
}

.ai-coach-header {
  background: linear-gradient(90deg, var(--brand), rgba(var(--brand-rgb, 25, 103, 210), 0.8));
  color: #fff;
  font-weight: 600;
  border-bottom: none;
  padding: 1rem 1.25rem;
  font-size: 15px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.ai-coach-header i {
  margin-right: 8px;
}

.ai-upgrade-prompt {
  text-align: center;
  padding: 3rem 2rem;
}

.ai-upgrade-icon {
  font-size: 48px;
  color: var(--brand);
  margin-bottom: 1rem;
}

.ai-upgrade-prompt h5 {
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.ai-upgrade-prompt p {
  color: var(--text-secondary);
  margin-bottom: 1.5rem;
  font-size: 14px;
  max-width: 400px;
  margin-left: auto;
  margin-right: auto;
}

/* Analysis Container */
.ai-analysis-container {
  position: relative;
}

.ai-score-ring-container {
  margin-bottom: 2rem;
}

.ai-score-ring {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 0 auto 0.5rem;
}

.ai-score-ring svg {
  transform: rotate(-90deg);
  width: 100%;
  height: 100%;
}

.ai-score-ring-bg {
  fill: none;
  stroke: var(--border);
  stroke-width: 3;
}

.ai-score-ring-fill {
  fill: none;
  stroke: var(--brand);
  stroke-width: 3;
  stroke-linecap: round;
  transition: stroke-dashoffset 1.5s ease-out;
}

.ai-score-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  font-weight: 700;
  font-size: 28px;
  color: var(--brand);
}

.ai-score-text span {
  font-size: 14px;
  color: var(--text-secondary);
  display: block;
  margin-top: 2px;
}

.ai-score-label {
  text-align: center;
  font-weight: 600;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 1rem;
}

/* Summary */
.ai-summary-container {
  background: rgba(var(--brand-rgb, 25, 103, 210), 0.05);
  border-left: 4px solid var(--brand);
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;
}

.ai-summary {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.6;
}

.ai-summary p {
  margin: 0;
}

/* Suggestions - single column for readability */
.ai-suggestions-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.ai-suggestion-card {
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 1.25rem;
  background: #fff;
  border-left: 4px solid var(--border);
  transition: all 0.2s ease;
}

.ai-suggestion-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.ai-suggestion--add {
  border-left-color: #10b981;
  background: rgba(16, 185, 129, 0.02);
}

.ai-suggestion--improve {
  border-left-color: #f59e0b;
  background: rgba(245, 158, 11, 0.02);
}

.ai-suggestion--remove {
  border-left-color: #ef4444;
  background: rgba(239, 68, 68, 0.02);
}

.ai-suggestion--warning {
  border-left-color: #f97316;
  background: rgba(249, 115, 22, 0.02);
}

/* Step Number */
.ai-suggestion-step {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary);
  margin-bottom: 0.5rem;
}

.ai-suggestion-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 0.5rem;
  flex-wrap: wrap;
}

.ai-suggestion-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.ai-suggestion--add .ai-suggestion-icon {
  color: #10b981;
}

.ai-suggestion--improve .ai-suggestion-icon {
  color: #f59e0b;
}

.ai-suggestion--remove .ai-suggestion-icon {
  color: #ef4444;
}

.ai-suggestion--warning .ai-suggestion-icon {
  color: #f97316;
}

.ai-suggestion-title {
  font-weight: 600;
  font-size: 15px;
  flex-grow: 1;
  color: #111;
}

.ai-suggestion-priority {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 2px 8px;
  border-radius: 3px;
  background: var(--border);
  color: var(--text-secondary);
  flex-shrink: 0;
}

.ai-priority--high .ai-suggestion-priority {
  background: rgba(239, 68, 68, 0.15);
  color: #dc2626;
}

.ai-priority--medium .ai-suggestion-priority {
  background: rgba(245, 158, 11, 0.15);
  color: #b45309;
}

.ai-priority--low .ai-suggestion-priority {
  background: rgba(59, 130, 246, 0.15);
  color: #1e40af;
}

.ai-suggestion-type-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 3px;
  flex-shrink: 0;
}

.ai-type--add {
  background: rgba(16, 185, 129, 0.15);
  color: #059669;
}

.ai-type--improve {
  background: rgba(245, 158, 11, 0.15);
  color: #b45309;
}

.ai-type--remove {
  background: rgba(239, 68, 68, 0.15);
  color: #dc2626;
}

.ai-type--warning {
  background: rgba(249, 115, 22, 0.15);
  color: #c2410c;
}

/* Section Badge */
.ai-section-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--brand);
  background: rgba(var(--brand-rgb, 25, 103, 210), 0.08);
  padding: 3px 10px;
  border-radius: 20px;
  margin-bottom: 0.75rem;
  font-weight: 500;
}

.ai-section-badge i {
  font-size: 11px;
}

/* Before / After Blocks */
.ai-before-after {
  margin: 0.75rem 0;
}

.ai-text-block {
  border-radius: 8px;
  padding: 0.75rem 1rem;
  font-size: 13px;
  line-height: 1.6;
  position: relative;
}

.ai-text-before {
  background: #fef2f2;
  border: 1px solid #fecaca;
}

.ai-text-after {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
}

.ai-text-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 5px;
}

.ai-text-before .ai-text-label {
  color: #dc2626;
}

.ai-text-after .ai-text-label {
  color: #16a34a;
}

.ai-text-content {
  color: #333;
  white-space: pre-wrap;
  word-break: break-word;
}

.ai-arrow {
  text-align: center;
  padding: 4px 0;
  color: var(--text-secondary);
  font-size: 16px;
}

/* Copy Button */
.ai-copy-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(255,255,255,0.9);
  border: 1px solid #bbf7d0;
  border-radius: 6px;
  padding: 3px 10px;
  font-size: 11px;
  color: #16a34a;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.15s ease;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.ai-copy-btn:hover {
  background: #dcfce7;
  transform: scale(1.02);
}

.ai-copy-btn.copied {
  background: #16a34a;
  color: #fff;
  border-color: #16a34a;
}

/* Explanation */
.ai-explanation {
  font-size: 13px;
  color: var(--text-secondary);
  margin-top: 0.75rem;
  padding: 0.6rem 0.8rem;
  background: rgba(var(--brand-rgb, 25, 103, 210), 0.04);
  border-radius: 6px;
  line-height: 1.5;
}

.ai-explanation i {
  color: #f59e0b;
  margin-right: 2px;
}

/* Old format fallback */
.ai-suggestion-detail {
  font-size: 13px;
  color: #000;
  margin: 0.5rem 0;
  line-height: 1.5;
}

.ai-suggestion-impact {
  font-size: 12px;
  color: var(--text-secondary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 500;
}

.ai-suggestion-impact i {
  font-size: 11px;
}

/* Error Message */
.ai-error-message {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 6px;
  padding: 1rem;
  color: #dc2626;
  font-size: 14px;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.ai-error-message i {
  font-size: 18px;
}

/* Loading spinner */
.spinner-border-sm {
  width: 1rem;
  height: 1rem;
  border-width: 0.2em;
}

/* Skeleton Loading */
.ai-skeleton-card {
  background: #f9fafb !important;
  border-left-color: var(--border) !important;
  pointer-events: none;
}

.ai-skeleton {
  background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s ease-in-out infinite;
  border-radius: 4px;
}

.ai-skeleton-line {
  height: 14px;
}

.ai-skeleton-block {
  border-radius: 8px;
}

@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Responsive */
@media (max-width: 768px) {
  .ai-suggestion-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .ai-suggestion-priority {
    align-self: flex-start;
  }
  
  .ai-copy-btn {
    position: static;
    margin-top: 8px;
  }
}
</style>

<script src="<?= APP_URL ?>/js/ai-resume.js"></script>
