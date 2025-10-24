(function(){
  const root = document.documentElement;
  
  function setTheme(theme) {
    if (theme === 'dark') {
      root.classList.remove('light');
      root.classList.add('dark');
    } else {
      root.classList.remove('dark');
      root.classList.add('light');
    }
  }
  
  try {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark') {
      setTheme('dark');
    } else if (saved === 'light') {
      setTheme('light');
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      setTheme('dark');
    } else {
      setTheme('light');
    }
  } catch (e) {
    setTheme('light');
  }
  
  const btn = document.getElementById('theme-toggle');
  if (btn) {
    btn.addEventListener('click', function(){
      const isDark = root.classList.contains('dark');
      const newTheme = isDark ? 'light' : 'dark';
      setTheme(newTheme);
      try { localStorage.setItem('theme', newTheme); } catch (e) {}
    });
  }
})();
