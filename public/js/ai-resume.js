(function() {
    'use strict';
    
    const PANEL_ID = 'ai-coach-panel';
    const BTN_ID = 'btn-ai-analyse';
    const CACHE_INPUT_ID = 'ai-cached-at';
    
    document.addEventListener('DOMContentLoaded', function() {
        const panel = document.getElementById(PANEL_ID);
        if (!panel) return;
        
        const btn = document.getElementById(BTN_ID);
        if (!btn) return;
        
        // Load cached data if available
        const cachedInput = document.getElementById(CACHE_INPUT_ID);
        if (cachedInput && window.AI_CACHED_DATA) {
            renderCachedData(window.AI_CACHED_DATA, cachedInput.value);
        }
        
        // Analyse button click
        btn.addEventListener('click', function() {
            analyseResume(btn, panel);
        });
    });
    
    function analyseResume(btn, panel) {
        const originalText = btn.textContent;
        const suggestionsList = panel.querySelector('.ai-suggestions-list');
        const scoreRing = panel.querySelector('.ai-score-ring-container');
        const summaryDiv = panel.querySelector('.ai-summary');
        
        // Show loading state
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analysing your resume...';
        
        if (suggestionsList) suggestionsList.innerHTML = renderLoadingSkeleton();
        if (scoreRing) scoreRing.innerHTML = '';
        if (summaryDiv) summaryDiv.innerHTML = '';
        
        // Get the base path from current location
        const basePath = window.location.pathname.split('/').slice(0, -1).join('/');
        
        fetch(basePath + '/ai_analyse.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(result => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Re-analyse';
            
            if (!result.success) {
                showError(panel, result.error || 'Analysis failed');
                return;
            }
            
            // Update cached data with fresh analysis
            window.AI_CACHED_DATA = result.data;
            renderAnalysis(panel, result.data);
        })
        .catch(error => {
            btn.disabled = false;
            btn.textContent = originalText;
            console.error('Error:', error);
            showError(panel, 'Network error. Please try again.');
        });
    }
    
    function renderAnalysis(panel, data) {
        const scoreRingContainer = panel.querySelector('.ai-score-ring-container');
        const summaryDiv = panel.querySelector('.ai-summary');
        const suggestionsList = panel.querySelector('.ai-suggestions-list');
        
        if (scoreRingContainer) {
            scoreRingContainer.innerHTML = renderScoreRing(data.score);
        }
        
        if (summaryDiv) {
            summaryDiv.innerHTML = `<p>${escapeHtml(data.summary)}</p>`;
        }
        
        if (suggestionsList && data.suggestions) {
            suggestionsList.innerHTML = data.suggestions.map((s, index) => 
                renderSuggestionCard(s, index + 1)
            ).join('');
            
            // Attach copy button listeners
            suggestionsList.querySelectorAll('.ai-copy-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const text = this.getAttribute('data-copy');
                    navigator.clipboard.writeText(text).then(() => {
                        const orig = this.innerHTML;
                        this.innerHTML = '<i class="bi bi-check2"></i> Copied!';
                        this.classList.add('copied');
                        setTimeout(() => {
                            this.innerHTML = orig;
                            this.classList.remove('copied');
                        }, 2000);
                    });
                });
            });
        }
    }
    
    function renderCachedData(data, timestamp) {
        const panel = document.getElementById(PANEL_ID);
        if (!panel) return;
        
        renderAnalysis(panel, data);
        
        // Show "Last analysed X mins ago"
        const cachedLabel = panel.querySelector('.ai-cached-label');
        if (cachedLabel && timestamp) {
            const mins = getMinutesAgo(timestamp);
            cachedLabel.textContent = `Last analysed ${mins} mins ago`;
            cachedLabel.style.display = 'block';
        }
    }
    
    function renderScoreRing(score) {
        const circumference = 2 * Math.PI * 45; // radius 45
        const offset = circumference - (score / 100) * circumference;
        
        let scoreColor = '#ef4444'; // red
        let scoreLabel = 'Needs Work';
        if (score >= 80) { scoreColor = '#10b981'; scoreLabel = 'Great'; }
        else if (score >= 60) { scoreColor = '#3b82f6'; scoreLabel = 'Good'; }
        else if (score >= 40) { scoreColor = '#f59e0b'; scoreLabel = 'Fair'; }
        
        return `
            <div class="ai-score-ring">
                <svg width="120" height="120" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="45" class="ai-score-ring-bg"></circle>
                    <circle cx="60" cy="60" r="45" class="ai-score-ring-fill" 
                        style="stroke-dasharray: ${circumference}; stroke-dashoffset: ${offset}; stroke: ${scoreColor}"></circle>
                </svg>
                <div class="ai-score-text" style="color: ${scoreColor}">${score}<span>/100</span></div>
            </div>
            <div class="ai-score-label" style="color: ${scoreColor}">${scoreLabel}</div>
        `;
    }
    
    function renderSuggestionCard(suggestion, stepNumber) {
        const icons = {
            'add': 'bi-plus-circle-fill',
            'improve': 'bi-pencil-fill',
            'remove': 'bi-dash-circle-fill',
            'warning': 'bi-exclamation-triangle-fill'
        };
        
        const typeLabels = {
            'add': 'Add',
            'improve': 'Improve',
            'remove': 'Remove',
            'warning': 'Warning'
        };
        
        const icon = icons[suggestion.type] || 'bi-info-circle-fill';
        const priority = suggestion.priority || 'medium';
        const typeLabel = typeLabels[suggestion.type] || 'Info';
        
        // Support both old format (detail/impact) and new format (current_text/suggested_text/explanation)
        const hasBeforeAfter = suggestion.current_text && suggestion.suggested_text;
        
        let bodyHtml = '';
        
        if (hasBeforeAfter) {
            // New format: show before/after blocks
            const isNew = suggestion.current_text === 'Not currently in your resume.' || suggestion.current_text === 'Not currently in your resume';
            
            if (isNew) {
                bodyHtml = `
                    <div class="ai-before-after">
                        <div class="ai-text-block ai-text-after">
                            <div class="ai-text-label"><i class="bi bi-plus-circle"></i> Add this to your resume:</div>
                            <div class="ai-text-content">${escapeHtml(suggestion.suggested_text)}</div>
                            <button class="ai-copy-btn" data-copy="${escapeHtml(suggestion.suggested_text)}" title="Copy to clipboard">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                `;
            } else {
                bodyHtml = `
                    <div class="ai-before-after">
                        <div class="ai-text-block ai-text-before">
                            <div class="ai-text-label"><i class="bi bi-x-circle"></i> Current text in your resume:</div>
                            <div class="ai-text-content">${escapeHtml(suggestion.current_text)}</div>
                        </div>
                        <div class="ai-arrow"><i class="bi bi-arrow-down"></i></div>
                        <div class="ai-text-block ai-text-after">
                            <div class="ai-text-label"><i class="bi bi-check-circle"></i> Replace it with:</div>
                            <div class="ai-text-content">${escapeHtml(suggestion.suggested_text)}</div>
                            <button class="ai-copy-btn" data-copy="${escapeHtml(suggestion.suggested_text)}" title="Copy to clipboard">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                `;
            }
            
            if (suggestion.explanation) {
                bodyHtml += `
                    <div class="ai-explanation">
                        <i class="bi bi-lightbulb"></i> <strong>Why?</strong> ${escapeHtml(suggestion.explanation)}
                    </div>
                `;
            }
        } else {
            // Old format fallback
            if (suggestion.detail) {
                bodyHtml += `<p class="ai-suggestion-detail">${escapeHtml(suggestion.detail)}</p>`;
            }
            if (suggestion.impact) {
                bodyHtml += `<p class="ai-suggestion-impact"><i class="bi bi-lightning-charge-fill"></i> ${escapeHtml(suggestion.impact)}</p>`;
            }
        }
        
        const sectionBadge = suggestion.section 
            ? `<span class="ai-section-badge"><i class="bi bi-file-earmark-text"></i> ${escapeHtml(suggestion.section)}</span>`
            : '';
        
        return `
            <div class="ai-suggestion-card ai-suggestion--${suggestion.type} ai-priority--${priority}">
                <div class="ai-suggestion-step">Step ${stepNumber}</div>
                <div class="ai-suggestion-header">
                    <span class="ai-suggestion-icon"><i class="bi ${icon}"></i></span>
                    <span class="ai-suggestion-title">${escapeHtml(suggestion.title)}</span>
                    <span class="ai-suggestion-priority">${priority}</span>
                    <span class="ai-suggestion-type-badge ai-type--${suggestion.type}">${typeLabel}</span>
                </div>
                ${sectionBadge}
                ${bodyHtml}
            </div>
        `;
    }
    
    function renderLoadingSkeleton() {
        let cards = '';
        for (let i = 0; i < 3; i++) {
            cards += `
                <div class="ai-suggestion-card ai-skeleton-card">
                    <div class="ai-skeleton ai-skeleton-line" style="width: 30%; height: 12px; margin-bottom: 12px;"></div>
                    <div class="ai-skeleton ai-skeleton-line" style="width: 70%; height: 16px; margin-bottom: 16px;"></div>
                    <div class="ai-skeleton ai-skeleton-block" style="height: 60px; margin-bottom: 10px;"></div>
                    <div class="ai-skeleton ai-skeleton-block" style="height: 60px; margin-bottom: 10px;"></div>
                    <div class="ai-skeleton ai-skeleton-line" style="width: 90%; height: 14px;"></div>
                </div>
            `;
        }
        return cards;
    }
    
    function showError(panel, message) {
        const suggestionsList = panel.querySelector('.ai-suggestions-list');
        if (suggestionsList) {
            suggestionsList.innerHTML = `
                <div class="ai-error-message">
                    <i class="bi bi-exclamation-circle"></i> ${escapeHtml(message)}
                </div>
            `;
        }
    }
    
    function getMinutesAgo(timestamp) {
        const now = new Date();
        const past = new Date(timestamp);
        const diff = (now - past) / (1000 * 60);
        return Math.floor(diff);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
})();
