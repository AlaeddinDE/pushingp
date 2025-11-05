<?php
session_start();
if (!empty($_SESSION['user'])) {
  $redirect = !empty($_SESSION['is_admin']) ? 'admin/' : 'member.php';
  header('Location: ' . $redirect);
  exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pushing P — Login</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { display: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
          boxShadow: { glow: '0 0 32px rgba(99,102,241,0.25), inset 0 0 24px rgba(236,72,153,0.15)' },
          colors: { brand: {600:'#6366f1'} }
        }
      }
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
  <style>
    :root { color-scheme: dark; }
    html,body { height:100%; -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
    body {
      background: radial-gradient(1200px 800px at 10% 10%, rgba(99,102,241,0.12), transparent 55%),
                  radial-gradient(1000px 600px at 90% 20%, rgba(236,72,153,0.10), transparent 55%),
                  #0a0b10;
    }
    .grad-text {
      background: linear-gradient(120deg,#60a5fa,#a78bfa 30%,#f472b6 60%,#22d3ee 90%);
      -webkit-background-clip:text; color:transparent;
    }
    .glass { 
      background: rgba(255,255,255,.06); 
      border:1px solid rgba(255,255,255,.12); 
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
    }
    .glass-strong {
      background: rgba(255,255,255,.08);
      border:1px solid rgba(255,255,255,.15);
      backdrop-filter: blur(25px);
      -webkit-backdrop-filter: blur(25px);
    }
    .magnet {
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .magnet:hover {
      transform: scale(1.02) translateY(-2px);
    }
  </style>
</head>
<body class="font-display text-white">
  <main class="min-h-screen flex items-center justify-center px-4 relative overflow-hidden">
    <!-- Animated Background Elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute top-20 left-20 w-72 h-72 bg-blue-500/10 rounded-full blur-3xl animate-pulse"></div>
      <div class="absolute bottom-20 right-20 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
    </div>
    
    <section class="glass-strong rounded-3xl shadow-glow w-full max-w-md p-8 relative z-10">
      <div class="text-center mb-6">
        <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-blue-600 to-purple-600 grid place-items-center shadow-lg mx-auto mb-4">
          <span class="text-3xl font-black">P</span>
        </div>
        <h1 class="text-4xl font-black grad-text mb-2">Crew Login</h1>
        <p class="text-white/70">Name & PIN eingeben, um fortzufahren.</p>
      </div>
      <form id="loginForm" class="grid gap-4">
        <div>
          <label class="block text-sm text-white/70 mb-2">Name</label>
          <input id="name" name="name" list="names" placeholder="Name eingeben" required class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600/60 transition-all" autocomplete="off">
          <datalist id="names"></datalist>
        </div>
        <div>
          <label class="block text-sm text-white/70 mb-2">PIN</label>
          <input id="pin" name="pin" type="password" minlength="4" maxlength="6" placeholder="PIN eingeben" required class="w-full rounded-xl px-4 py-3 text-black outline-none focus:ring-2 focus:ring-brand-600/60 transition-all">
        </div>
        <button class="magnet rounded-2xl bg-gradient-to-r from-brand-600 to-purple-600 px-6 py-3 font-semibold shadow-lg hover:shadow-glow transition-all">
          Einloggen 🚀
        </button>
        <p id="msg" class="text-red-400 text-sm text-center min-h-[20px]"></p>
      </form>
    </section>
  </main>
  <script>
    async function loadNames(){
      try {
        const res = await fetch('api/get_members.php');
        const members = await res.json();
        const dl = document.getElementById('names');
        members.forEach(m => {
          const o = document.createElement('option');
          o.value = m.name; dl.appendChild(o);
        });
      } catch {}
    }
    document.getElementById('loginForm').addEventListener('submit', async e=>{
      e.preventDefault();
      const fd = new FormData(e.target);
      const res = await fetch('/api/login.php', {method:'POST', body:fd});
      const data = await res.json().catch(()=>({error:'Serverfehler'}));
      if(data.status==='ok') window.location.href = data.admin ? 'admin/' : 'member.php';
      else document.getElementById('msg').textContent = data.error || 'Login fehlgeschlagen';
    });
    loadNames();
  </script>
</body>
</html>
