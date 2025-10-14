<?php
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$publishableKey = $_ENV['CLERK_PUBLISHABLE_KEY']
  ?? $_ENV['NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY']
  ?? getenv('CLERK_PUBLISHABLE_KEY')
  ?? getenv('NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY')
  ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign in</title>
  <script async crossorigin="anonymous"
    data-clerk-publishable-key="<?php echo htmlspecialchars($publishableKey, ENT_QUOTES, 'UTF-8'); ?>"
    src="https://cdn.jsdelivr.net/npm/@clerk/clerk-js@latest/dist/clerk.browser.js"></script>
  <style>
    html, body { height: 100%; margin: 0; }
    .center { min-height: 100%; display: grid; place-items: center; }
    .error { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: #b00020; padding: 1rem; border: 1px solid #b00020; border-radius: 6px; }
  </style>
  <noscript>This page requires JavaScript to display the sign in form.</noscript>
  <script>
    // Robust mount: wait for Clerk to be loaded, then mount Sign In explicitly
    window.addEventListener('clerk:loaded', () => {
      const root = document.getElementById('sign-in');
      try {
        window.Clerk.mountSignIn(root, {
          redirectUrl: '/index.php',
          signUpUrl: '/clerk-auth.php'
        });
      } catch (e) {
        console.error('[Clerk] mountSignIn failed', e);
        const host = document.getElementById('clerk-root');
        if (host) host.innerHTML = '<div class="error">Failed to mount Clerk Sign In.</div>';
      }
    });
    window.addEventListener('load', async () => {
      // Trigger Clerk to load if the script has arrived by now
      if (!window.Clerk) return;
      try {
        await window.Clerk.load();
      } catch (e) {
        console.error('[Clerk] load failed', e);
        const host = document.getElementById('clerk-root');
        if (host) host.innerHTML = '<div class="error">Failed to load Clerk.</div>';
      }
    });
  </script>
</head>
<body>
  <main class="center">
    <div id="clerk-root">
      <?php if (empty($publishableKey)) { ?>
        <div class="error">
          Missing Clerk publishable key. Set <code>CLERK_PUBLISHABLE_KEY</code> (or <code>NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY</code>) in your environment.
          <br/>Example .env:
          <pre>CLERK_PUBLISHABLE_KEY=pk_test_xxx
CLERK_SECRET_KEY=sk_test_xxx</pre>
        </div>
      <?php } else { ?>
        <div id="sign-in"></div>
      <?php } ?>
    </div>
  </main>
</body>
</html>
