<?php
// ...existing code...
?>
<style>
body { background: linear-gradient(120deg, #e0eafc, #cfdef3); min-height: 100vh; }
.welcome { max-width: 600px; margin: 60px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px #0002; padding: 2.5rem 2rem; text-align: center; }
.welcome h1 { color: #2980b9; margin-bottom: 1rem; }
.welcome p { color: #666; margin-bottom: 2rem; font-size: 1.1rem; }
.btn-primary { padding: 0.75rem 2rem; background: linear-gradient(90deg, #2980b9, #6dd5fa); color: #fff; border: none; border-radius: 6px; font-size: 1.1rem; text-decoration: none; font-weight: bold; box-shadow: 0 2px 8px #0001; transition: background 0.2s; }
.btn-primary:hover { background: linear-gradient(90deg, #2574a9, #4ca1af); }
@media (max-width: 600px) {
  .welcome { padding: 1.2rem 0.5rem; }
}
</style>
<div class="welcome">
  <h1>Bienvenue sur notre site!</h1>
  <p>Nous sommes ravis de vous voir ici. Explorez nos services et n'hésitez pas à nous contacter pour toute question.</p>
  <a href="#services" class="btn-primary">Nos Services</a>
</div>
<div id="services">
  <!-- Section des services -->
</div>
<?php
// ...existing code...
?>