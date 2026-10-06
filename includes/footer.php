<?php // Closes the shared page layout and loads the common client-side behavior. ?>
</main>
</div>
</div>
<div class="modal confirmation-modal" id="confirmationModal" role="dialog" aria-modal="true" aria-labelledby="confirmationTitle" aria-describedby="confirmationMessage">
		<div class="modal-box" style="max-width: 600px;">
			<h2 id="confirmationTitle">Confirm action</h2>
			<p id="confirmationMessage" class="muted"></p>
			<div class="form-end">
				<button class="btn secondary" type="button" data-confirm-cancel>Cancel</button>
				<button class="btn primary" type="button" id="confirmActionButton">Continue</button>
		</div>
	</div>
</div>
<script>document.getElementById('themeToggle')?.addEventListener('click',function(){const theme=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=theme;this.setAttribute('aria-label',theme==='dark'?'Switch to light mode':'Switch to dark mode');this.setAttribute('title',theme==='dark'?'Switch to light mode':'Switch to dark mode');localStorage.setItem(document.documentElement.dataset.themeKey,theme);});</script>
<script src="assets/js/app.js?v=4"></script>
</body>
</html>