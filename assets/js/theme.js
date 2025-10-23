(function(){
  const root = document.documentElement;
  try {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark') root.classList.add('dark');
  } catch (e) {}
  const btn = document.getElementById('theme-toggle');
  if (btn) {
    btn.addEventListener('click', function(){
      root.classList.toggle('dark');
      try { localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light'); } catch (e) {}
    });
  }
})();
