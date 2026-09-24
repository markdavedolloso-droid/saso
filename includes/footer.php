<?php // Closes the shared page layout and loads the common client-side behavior. ?>
</main>
</div>
</div>
<script>document.getElementById('themeToggle')?.addEventListener('click',function(){const theme=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=theme;this.setAttribute('aria-label',theme==='dark'?'Switch to light mode':'Switch to dark mode');this.setAttribute('title',theme==='dark'?'Switch to light mode':'Switch to dark mode');localStorage.setItem(document.documentElement.dataset.themeKey,theme);});</script>
<script src="assets/js/app.js?v=3"></script>
</body>
</html>