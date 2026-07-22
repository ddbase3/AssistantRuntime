<?php
function icon_status($type) {

	switch ($type) {

		case 'ok':
			return '<svg class="status ok" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8"/><path d="M5 10l3 3l7-7"/></svg>';

		case 'invalid':
			return '<svg class="status invalid" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8"/><path d="M6 6l8 8M14 6l-8 8"/></svg>';

		case 'error':
			return '<svg class="status error" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8"/><path d="M10 5v6M10 14v1"/></svg>';

		case 'idle':
		default:
			return '<svg class="status idle" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8"/></svg>';
	}
}
?>

<section class="ai-dashboard">

	<?php foreach ($this->_['groups'] as $group) { ?>
		<div class="group">
			<div class="group-header">
				<h2><?php echo htmlspecialchars($group['name']); ?></h2>
				<div class="group-sub"><?php echo htmlspecialchars($group['id']); ?></div>
			</div>

			<div class="grid">
				<?php foreach ($group['services'] as $service) { ?>
				<div class="card" data-service="<?php echo $service['id']; ?>">

					<div class="header">
						<strong><?php echo htmlspecialchars($service['name']); ?></strong>
						<div class="service-id"><?php echo htmlspecialchars($service['id']); ?></div>
					</div>

					<div class="meta">
						<div>Endpoint: <?php echo htmlspecialchars($service['endpointShort']); ?></div>
						<div>API Key: <?php echo htmlspecialchars($service['apikeyShort']); ?></div>
					</div>

					<div class="status-line">
						<?php echo icon_status('idle'); ?>
						<span class="status-text">Idle</span>
					</div>

					<button type="button" class="test-button" data-service="<?php echo $service['id']; ?>">
						Test Connection
					</button>

				</div>
				<?php } ?>
			</div>
		</div>
	<?php } ?>

</section>

<style>
	.ai-dashboard .group {
		margin: 2em 0 2.5em 0;
	}

	.ai-dashboard .group-header {
		display: flex;
		align-items: baseline;
		gap: .8em;
		margin-bottom: .9em;
		border-bottom: 1px solid #eee;
		padding-bottom: .5em;
	}

	.ai-dashboard .group-header h2 {
		margin: 0;
		font-size: 1.2em;
	}

	.ai-dashboard .group-sub {
		font-size: .85em;
		color: #888;
	}

	/* Responsive grid: 3 → 2 → 1 cards */
	.ai-dashboard .grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, 280px);
		gap: 1.5em;
		justify-content: start;
	}

	.ai-dashboard .card {
		padding: 1.3em;
		border: 1px solid #ddd;
		border-radius: 8px;
		background: #fff;
		box-shadow: 0 1px 3px rgba(0,0,0,0.05);
	}

	.ai-dashboard .header {
		font-size: 1.1em;
		margin-bottom: .5em;
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		gap: .8em;
	}

	.ai-dashboard .service-id {
		font-size: .75em;
		color: #888;
		white-space: nowrap;
	}

	.ai-dashboard .meta {
		font-size: .9em;
		color: #666;
		margin-bottom: .8em;
		line-height: 1.4em;
	}

	.status-line {
		margin-bottom: 1em;
		font-size: .95em;
		display: flex;
		align-items: center;
	}

	.test-button {
		padding: .5em 1em;
		border: 1px solid #aaa;
		border-radius: 4px;
		background: #fafafa;
		cursor: pointer;
		transition: background .2s;
	}
	.test-button:hover {
		background: #eee;
	}

	/* ICON COLORS */
	.status {
		width: 20px;
		height: 20px;
		fill: none;
		stroke-width: 2px;
		display: inline-block;
		vertical-align: middle;
		margin-right: .5em;
	}

	.status.ok circle { stroke: #2ECC71; }
	.status.ok path   { stroke: #2ECC71; }

	.status.invalid circle { stroke: #E74C3C; }
	.status.invalid path   { stroke: #E74C3C; }

	.status.error circle { stroke: #F1C40F; }
	.status.error path   { stroke: #F1C40F; }

	.status.idle circle { stroke: #BDC3C7; }
</style>

<script>
function AiDashboardInit() {

	document.addEventListener("click", function(e) {
		let btn = e.target.closest(".test-button");
		if (!btn) return;

		let service = btn.dataset.service;
		let card = btn.closest(".card");

		updateStatus(card, "idle", "Testing...");

		fetch("?name=aiservicedashboarddisplay&action=test&service=" + encodeURIComponent(service))
			.then(r => r.json())
			.then(data => {

				if (data.ok && data.apikey_valid) {
					updateStatus(card, "ok", "OK");
				}
				else if (data.apikey_valid === false) {
					updateStatus(card, "invalid", "Invalid API Key");
				}
				else {
					updateStatus(card, "error", data.message || "Error");
				}
			})
			.catch(err => {
				updateStatus(card, "error", err.toString());
			});
	});
}

function updateStatus(card, status, message) {
	let statusNode = card.querySelector(".status");
	let textNode = card.querySelector(".status-text");

	statusNode.outerHTML = icon_for(status);
	textNode.textContent = message;
}

function icon_for(status) {
	switch(status) {
		case 'ok':
			return `<?php echo str_replace("\n","", icon_status('ok')); ?>`;
		case 'invalid':
			return `<?php echo str_replace("\n","", icon_status('invalid')); ?>`;
		case 'error':
			return `<?php echo str_replace("\n","", icon_status('error')); ?>`;
		default:
			return `<?php echo str_replace("\n","", icon_status('idle')); ?>`;
	}
}

// Important: initialize immediately.
AiDashboardInit();
</script>
