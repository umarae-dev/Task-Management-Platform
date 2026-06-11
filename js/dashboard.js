
// --- Sidebar control (mobile-safe) ---
const sidebar = document.getElementById('sidebar');
const backdrop = document.getElementById('sidebarBackdrop');

function openSidebar(){
  sidebar.classList.add('open');
  document.body.classList.add('sidebar-open');
  backdrop.style.display = 'block';
  sidebar.style.left = '0';
}
function closeSidebar(){
  sidebar.classList.remove('open');
  document.body.classList.remove('sidebar-open');
  backdrop.style.display = 'none';
  sidebar.style.left = '-280px';
}
window.addEventListener('resize', ()=> {
  if(window.innerWidth > 900){
    backdrop.style.display = 'none';
    document.body.classList.remove('sidebar-open');
    sidebar.style.left = '0';
  } else {
    if(!sidebar.classList.contains('open')) sidebar.style.left = '-280px';
  }
});

// --- Theme (Dark/Light) ---
(function attachLightThemeVars(){
  const css = `
  :root[data-theme="light"]{
    --bg:#f7f8fa; --glass:rgba(255,255,255,1);
    --cyan:#006eff; --yellow:#a67c00; --red:#cc2b2e; --green:#0a8f5a; --muted:#5c6773;
    --white:#0b1220; --cardHover:rgba(0,0,0,0.04);
  }
  :root[data-theme="light"] body{ color:var(--white); background:var(--bg); }
  :root[data-theme="light"] .sidebar a{ color:#142033; }
  :root[data-theme="light"] .sidebar a:hover,
  :root[data-theme="light"] .sidebar a.active{ background:var(--cyan); color:#fff; }
  :root[data-theme="light"] .table th{ color:var(--cyan); }
  :root[data-theme="light"] .smallmuted{ color:var(--muted); }
  `;
  const tag = document.createElement('style');
  tag.id = 'lightThemeVars';
  tag.appendChild(document.createTextNode(css));
  document.head.appendChild(tag);
})();

function updateThemeIcon(theme){
  const btn = document.getElementById('themeToggle');
  if(!btn) return;
  btn.classList.remove('fa-sun','fa-moon');
  btn.classList.add(theme === 'dark' ? 'fa-moon' : 'fa-sun');
  btn.title = theme === 'dark' ? 'Switch to light' : 'Switch to dark';
}

(function themeInit(){
  const root = document.documentElement;
  const saved = localStorage.getItem('theme');
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const theme = saved || (prefersDark ? 'dark' : 'light');
  root.setAttribute('data-theme', theme);
  updateThemeIcon(theme);
})();

function toggleTheme(){
  const root = document.documentElement;
  const current = root.getAttribute('data-theme') || 'dark';
  const next = current === 'dark' ? 'light' : 'dark';
  root.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  updateThemeIcon(next);
  if(window._earnChartInstance) applyChartTheme(window._earnChartInstance);
}

document.getElementById('themeToggle')?.addEventListener('click', toggleTheme);

// --- Chart ---
const labels = <?php echo json_encode($trendLabels); ?>;
const credit = <?php echo json_encode($trendCredit); ?>;
const debit  = <?php echo json_encode($trendDebit); ?>;

const ctx = document.getElementById('earnChart');
if (ctx) {
  const getThemeColors = ()=> {
    const t = document.documentElement.getAttribute('data-theme');
    if(t === 'light') {
      return { text: '#0b1220', grid: 'rgba(0,0,0,0.08)', credit: getComputedStyle(document.documentElement).getPropertyValue('--chart-credit') || '#0aa2ff', debit: getComputedStyle(document.documentElement).getPropertyValue('--chart-debit') || '#e03a3d' };
    } else {
      return { text: '#cfe', grid: 'rgba(255,255,255,0.08)', credit: getComputedStyle(document.documentElement).getPropertyValue('--chart-credit') || '#00ffff', debit: getComputedStyle(document.documentElement).getPropertyValue('--chart-debit') || '#ff4d4f' };
    }
  };

  const colors = getThemeColors();

  const config = {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        { label: 'Credits', data: credit, tension: .35, borderWidth: 2, fill: false, borderColor: colors.credit },
        { label: 'Debits',  data: debit,  tension: .35, borderWidth: 2, fill: false, borderColor: colors.debit }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { labels: { color: colors.text } } },
      scales: {
        x: { ticks: { color: colors.text }, grid: { color: colors.grid } },
        y: { ticks: { color: colors.text }, grid: { color: colors.grid }, beginAtZero:true }
      }
    }
  };

  const chart = new Chart(ctx, config);
  window.__earnChartInstance = chart;

  function applyChartTheme(chartRef){
    const c = getThemeColors();
    chartRef.data.datasets[0].borderColor = c.credit;
    chartRef.data.datasets[1].borderColor = c.debit;
    chartRef.options.plugins.legend.labels.color = c.text;
    chartRef.options.scales.x.ticks.color = c.text;
    chartRef.options.scales.y.ticks.color = c.text;
    chartRef.options.scales.x.grid.color = c.grid;
    chartRef.options.scales.y.grid.color = c.grid;
    chartRef.update();
  }

  document.getElementById('themeToggle')?.addEventListener('click', ()=>applyChartTheme(chart));
  window.addEventListener('storage', (e)=>{ if(e.key === 'theme') applyChartTheme(chart); });
}
</script>
<!--notifications js here -->
<script>
const notifBtn = document.querySelector('.notification-btn');
const notifContent = document.querySelector('.notification-content');
const notifClose = document.querySelector('.notif-close');
const messageDiv = document.getElementById('rotating-message');

let messages = [], msgIndex = 0, msgInterval;

// Shuffle array utility
function shuffleArray(array) {
  for (let i = array.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [array[i], array[j]] = [array[j], array[i]];
  }
  return array;
}

// Load messages from external file
async function loadMessages() {
  try {
    const res = await fetch('../includes/messages.php');
    messages = await res.json(); // Must be valid JSON array
    messages = shuffleArray(messages); // Shuffle messages
    msgIndex = Math.floor(Math.random() * messages.length); // Random start
  } catch (err) {
    console.error('Failed to load messages:', err);
    messages = ["💡 No messages available"];
    msgIndex = 0;
  }
}

// Start rotating messages
function startRotation() {
  if (!messageDiv || !messages.length) return;
  messageDiv.innerHTML = messages[msgIndex];
  msgInterval = setInterval(() => {
    msgIndex = (msgIndex + 1) % messages.length;
    messageDiv.innerHTML = messages[msgIndex];
  }, 5000);
}

// Stop rotation
function stopRotation() { clearInterval(msgInterval); }

// Open dropdown
function openDropdown() {
  notifBtn.style.transform = 'scale(1.1)';
  notifContent.style.display = 'block';
  setTimeout(() => {
    notifContent.style.opacity = '1';
    notifContent.style.transform = 'translateY(0)';
    startRotation();
  }, 10);
}

// Close dropdown
function closeDropdown() {
  notifBtn.style.transform = 'scale(1)';
  notifContent.style.opacity = '0';
  notifContent.style.transform = 'translateY(-20px)';
  stopRotation();
  setTimeout(() => { notifContent.style.display = 'none'; }, 300);
}

// Toggle dropdown on bell click
notifBtn.addEventListener('click', async () => {
  if (notifContent.style.display === 'block') closeDropdown();
  else {
    if (!messages.length) await loadMessages();
    openDropdown();
  }
});

// Close dropdown on close icon
notifClose.addEventListener('click', closeDropdown);

// Close if clicked outside
document.addEventListener('click', e => {
  if (!notifBtn.contains(e.target) && !notifContent.contains(e.target)) closeDropdown();
});
