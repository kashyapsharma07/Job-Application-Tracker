<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/bootstrap.php';

Auth::require();

// Set your premium plan details
$amount = 100; // Amount in paise (₹1.00)
$currency = 'INR';
$name = 'JobTracker Premium';
$description = 'Unlock premium features!';
$key_id = RAZORPAY_KEY_ID;
$user = Auth::user();
$currentPage = 'go-premium';
$pageTitle = 'Go Premium';

ob_start();
?>

<!-- ── Page content ─────────────────────────────────────────────────────────── -->

<div class="premium-page-container">
	<div class="premium-card-wrapper">
		<div class="premium-card">
			<div class="premium-card-header">
				<i class="bi bi-star-fill premium-icon"></i>
				<div class="premium-icon-label">Upgrade to Premium</div>
			</div>
			<div class="premium-card-body">
				<h2 class="premium-title">Unlock Advanced Analytics</h2>
				<p class="premium-description">
					Get access to detailed insights, advanced analytics, smart recommendations, and more features to accelerate your job search.
				</p>
				
				<!-- Features list -->
				<div class="premium-features">
					<h4 class="premium-features-header">Premium Features Include:</h4>
					<ul class="premium-features-list">
						<li>Advanced Analytics Dashboard</li>
						<li>Detailed Interview Success Rates</li>
						<li>Company Performance Insights</li>
						<li>Resume Optimization Tips</li>
						<li>Priority Support</li>
						<li>Export Reports (PDF/CSV)</li>
					</ul>
				</div>
				
				<!-- Price -->
				<div class="premium-price-section">
					<div class="premium-price-label">One-time Payment</div>
					<div class="premium-price-amount">₹1.00</div>
					<div class="premium-price-note">Lifetime Premium Access</div>
				</div>
				
				<!-- Payment button -->
				<button id="rzp-button" class="btn btn-primary premium-button">
					<i class="bi bi-credit-card me-2"></i> Pay Now with Razorpay
				</button>
				
				<!-- Back link -->
				<div class="premium-back-link">
					<a href="<?= APP_URL ?>/dashboard.php">Back to Dashboard</a>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
$content = ob_get_clean();

include __DIR__ . '/../views/partials/header.php';

echo $content;
?>

<!-- Razorpay MUST load before payment.js — no async/defer -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
	window.razorpayConfig = {
		key: "<?= $key_id ?>",
		amount: <?= $amount ?>,
		currency: "<?= $currency ?>",
		name: "<?= $name ?>",
		description: "<?= $description ?>",
		appUrl: "<?= APP_URL ?>",
		redirectUrl: "<?= $_SERVER['HTTP_REFERER'] ?? APP_URL . '/resumes.php' ?>"
	};
</script>
<script src="<?= APP_URL ?>/js/payment.js"></script>

<?php
include __DIR__ . '/../views/partials/footer.php';