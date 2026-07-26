(function () {
	let razorpayReady = false;

	function waitForRazorpay(callback) {
		if (typeof Razorpay !== 'undefined') {
			razorpayReady = true;
			callback();
			return;
		}
		setTimeout(function () {
			waitForRazorpay(callback);
		}, 100);
	}

	function init() {
		console.log('Init function called');
		const btn = document.getElementById('rzp-button');
		if (!btn) {
			console.error('Button not found in DOM');
			return;
		}

		console.log('Button found, waiting for Razorpay...');

		// Wait for Razorpay, then enable button
		waitForRazorpay(function () {
			console.log('Razorpay ready, enabling button');
			btn.disabled = false;
			btn.innerHTML = '<i class="bi bi-credit-card me-2"></i> Pay Now with Razorpay';
			btn.addEventListener('click', handlePaymentClick, false);
			console.log('Click handler attached');
		});
	}

	function handlePaymentClick(e) {
		e.preventDefault();
		e.stopPropagation();

		console.log('Payment button clicked');

		if (typeof Razorpay === 'undefined') {
			console.error('Razorpay not defined');
			showError('Razorpay is not loaded. Please refresh the page.');
			return;
		}

		const btn = document.getElementById('rzp-button');
		if (!btn) {
			console.error('Button element not found');
			return;
		}

		console.log('Starting payment process');
		btn.disabled = true;
		btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Processing...';

		const base = (window.razorpayConfig.appUrl || '').replace(/\/$/, '');
		console.log('API Base URL:', base);

		fetch(base + '/create_order.php')
			.then(function (res) {
				console.log('Order response status:', res.status);
				return res.json();
			})
			.then(function (data) {
				console.log('Order data received:', data);

				if (!data.order_id) {
					console.error('No order_id in response', data);
					showError('Could not create payment order: ' + (data.error || 'Please try again.'));
					resetButton(btn);
					return;
				}

				console.log('Order ID:', data.order_id);

				var options = {
					key:         window.razorpayConfig.key,
					amount:      window.razorpayConfig.amount,
					currency:    window.razorpayConfig.currency,
					name:        window.razorpayConfig.name,
					description: window.razorpayConfig.description,
					order_id:    data.order_id,
					handler: function (response) {
						console.log('Payment successful, response:', response);
						handlePaymentSuccess(response, btn);
					},
					modal: {
						ondismiss: function () {
							console.log('Payment modal dismissed');
							resetButton(btn);
						}
					}
				};

				try {
					console.log('Opening Razorpay modal');
					var rzp = new Razorpay(options);
					rzp.open();
				} catch (err) {
					console.error('Error opening Razorpay:', err);
					showError('Could not open payment window: ' + err.message);
					resetButton(btn);
				}
			})
			.catch(function (err) {
				console.error('Fetch error:', err);
				showError('Network error while creating order. Please try again.');
				resetButton(btn);
			});
	}

	function handlePaymentSuccess(response, btn) {
		const base = (window.razorpayConfig.appUrl || '').replace(/\/$/, '');

		fetch(base + '/verify_payment.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				razorpay_payment_id: response.razorpay_payment_id,
				razorpay_order_id:   response.razorpay_order_id,
				razorpay_signature:  response.razorpay_signature
			})
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data.success) {
					window.location.href = window.razorpayConfig.redirectUrl;
				} else {
					showError('Payment verification failed: ' + (data.message || 'Unknown error'));
					resetButton(btn);
				}
			})
			.catch(function (err) {
				showError('Network error during verification. Please contact support.');
				resetButton(btn);
			});
	}

	function resetButton(btn) {
		btn.disabled = false;
		btn.innerHTML = '<i class="bi bi-credit-card me-2"></i> Pay Now with Razorpay';
	}

	function showError(msg) {
		alert(msg);
	}

	// Razorpay is loaded synchronously before this script — safe to init immediately
	if (document.readyState === 'loading') {
		console.log('Document still loading, waiting for DOMContentLoaded');
		document.addEventListener('DOMContentLoaded', init);
	} else {
		console.log('Document ready, calling init');
		init();
	}

	console.log('Payment.js loaded, razorpayConfig:', window.razorpayConfig);

})();