<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Στατιστικά Περατωμένων Διπλωματικών</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  :root{
    --bg-grad-1:#e2e2e2;
    --bg-grad-2:#c9d6ff;
    --ink:#2b3642;
    --muted:#6b7280;
    --card:#ffffff;
    --ring:#e5e7eb;
    --shadow:0 8px 24px rgba(0,0,0,.08);
    --primary:#0b4ba6;          
    --primary-2:#5c8fd6;
    --primary-soft:rgba(11,75,166,.08);
  }

  /* reset */
  *,*::before,*::after{ box-sizing:border-box; }
  html,body{ margin:0; padding:0; width:100%; overflow-x:hidden; }

  body{
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: linear-gradient(to right, var(--bg-grad-1), var(--bg-grad-2));
    color:var(--ink);
  }
  body::before{
    content:""; position:fixed; inset:0;
    background: hsla(211, 32%, 51%, .35);
    z-index:-1;
  }

  /* Header */
  .site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            background-color: rgba(0, 51, 102, 0.92);
            color: white;
            box-shadow: 0 8px 8px -4px rgba(0, 0, 0, 0.2);
            font-family: 'Segoe UI', sans-serif;
            margin-bottom: 20px;
            height: 120px;
            position: relative;
            z-index: 10;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
        }
        .site-header .left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .site-header .logo {
            width:95px;
            height: 80px;
        }
        .system-name {
            font-size: 20px;
            font-weight: 600;
        }
        .site-header .right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .site-header .right nav a {
            color: white;
            text-decoration: none;
            margin-right: 15px;
        }
        .site-header .user-info {
            font-weight: 500;
        }

  /* Layout */
  .wrap{ max-width:1200px; margin:0 auto 28px; padding:0 16px 24px;}
  h1{ font-size:28px; margin:48px 0 8px; text-align:center; color:#0b376e; }
  .subtitle{ text-align:center; color:var(--muted); margin-bottom:18px; }

  /* KPIs */
  .summary{
    display:grid; grid-template-columns:repeat(3,1fr);
    gap:16px; margin-bottom:20px;
  }
  .kpi{
    background:var(--card);
    border:1px solid var(--ring);
    border-radius:14px;
    box-shadow:var(--shadow);
    padding:16px 18px;
    position:relative;
    overflow:hidden;
  }
  .kpi::after{
    content:""; position:absolute; inset:auto -30% -40% -30%;
    height:120px; background:linear-gradient(90deg, var(--primary-soft), transparent);
    transform:rotate(-3deg);
  }
  .kpi .lbl{ font-size:12px; letter-spacing:.2px; color:var(--muted); }
  .kpi .val{
    font-size:28px; font-weight:800; margin-top:6px;
    font-variant-numeric: tabular-nums;
  }

  /* Cards / Charts */
  .cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
    gap:18px; align-items:stretch;
  }
  .card{
    background:var(--card);
    border:1px solid var(--ring);
    border-radius:16px;
    box-shadow:var(--shadow);
    padding:18px;
    min-height:260px;
    transition:transform .15s ease, box-shadow .15s ease;
  }
  .card:hover{ transform:translateY(-2px); box-shadow:0 12px 28px rgba(0,0,0,.12); }
  .card canvas{ display:block; width:100%; height:200px; }
  .card--full{ grid-column:1/-1; }

  /* Loader */
  .loader{
    display:flex; align-items:center; justify-content:center;
    gap:10px; color:var(--muted); margin:18px 0 4px;
  }
  .dot{ width:8px; height:8px; border-radius:50%; background:var(--primary-2); opacity:.5; animation: bounce .9s infinite; }
  .dot:nth-child(2){ animation-delay:.15s }
  .dot:nth-child(3){ animation-delay:.3s }
  @keyframes bounce{ 0%,80%,100%{ transform:scale(0) } 40%{ transform:scale(1) } }

  /* Footer */
  footer{
    background-color: rgba(0,51,102,.92);
    color:#fff; text-align:center;
    padding:24px 12px; margin-top:24px;
    border-top-left-radius:0; border-top-right-radius:0;
  }

  @media (max-width: 900px){
    .summary{ grid-template-columns:1fr; }
    .card canvas{ height:220px; }
  }
</style>
</head>
<body>

<header class="site-header">
  <div class="left">
    <img src="ceid_logo.png" alt="Λογότυπο" class="logo">
    <span class="system-name">Σύστημα Υποστήριξης Διπλωματικών Εργασιών</span>
  </div>
  <div class="right">
    <nav>
      <a href="professor_home.php">Αρχική</a>
      <a href="listaDiplomatikon.php">Οι Διπλωματικές Μου</a>
      <a href="proskliseis.php">Προσκλήσεις</a>
      <a href="statistika.php">Στατιστικά</a>
    </nav>
    <span class="user-info"><a href="loginn.php" style="color: #ccc">Έξοδος</a></span>
  </div>
</header>

<div class="wrap">
  <h1>Στατιστικά Περατωμένων Διπλωματικών</h1>
  <div class="subtitle">Σύνοψη επίδοσης για ρόλο Επιβλέποντα και Μέλους Επιτροπής</div>

  <!-- KPIs -->
  <div class="summary">
    <div class="kpi">
      <div class="lbl">Μ.Ο. Χρόνου (ημέρες)</div>
      <div class="val" id="kpiTime">0</div>
    </div>
    <div class="kpi">
      <div class="lbl">Μ.Ο. Βαθμού</div>
      <div class="val" id="kpiGrade">0</div>
    </div>
    <div class="kpi">
      <div class="lbl">Σύνολο Περατωμένων</div>
      <div class="val" id="kpiCount">0</div>
    </div>
  </div>

  <!-- Loader -->
  <div id="loader" class="loader" aria-live="polite">
    <span>Φόρτωση δεδομένων</span>
    <span class="dot"></span><span class="dot"></span><span class="dot"></span>
  </div>

  <!-- Cards -->
  <div class="cards" id="cards" style="display:none;">
    <div class="card">
      <canvas id="chartTime"></canvas>
    </div>
    <div class="card">
      <canvas id="chartGrade"></canvas>
    </div>
    <div class="card card--full">
      <canvas id="chartCount"></canvas>
    </div>
  </div>
</div>

<footer>
  <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
  <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>

function animateKPI(id, target, asInteger=false){
  const el = document.getElementById(id);
  const T  = Number(target||0);
  let v=0, step = Math.max(T/40, 0.6);
  const tick = () => {
    v += step;
    if (v >= T) { el.textContent = asInteger ? Math.round(T) : T.toFixed(1); return; }
    el.textContent = asInteger ? Math.round(v) : v.toFixed(1);
    requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}

const baseOptions = (title) => ({
  responsive:true,
  maintainAspectRatio:false,
  animation:{ duration:600 },
  plugins:{
    title:{ display:true, text:title, color:'#0b376e', font:{ weight:'600' } },
    legend:{ labels:{ boxWidth:12 } },
    tooltip:{ backgroundColor:'#0b4ba6', titleColor:'#fff', bodyColor:'#fff' }
  },
  scales:{
    x:{ grid:{ display:false } },
    y:{ beginAtZero:true, grid:{ color:'rgba(11,75,166,.08)' } }
  }
});

/* draw charts */
function drawCharts(stats){
  document.getElementById('loader').style.display = 'none';
  document.getElementById('cards').style.display  = 'grid';

  const s = stats.supervised || {};
  const m = stats.member || {};
  const labels = ['Επιβλέπω','Συμμετέχω'];

  const avgTime  = ((s.avg_completion_days||0)+(m.avg_completion_days||0))/2;
  const avgGrade = ((s.avg_grade||0)+(m.avg_grade||0))/2;
  const total    = (s.total_count||0)+(m.total_count||0);

  animateKPI('kpiTime',  avgTime);
  animateKPI('kpiGrade', avgGrade);
  animateKPI('kpiCount', total, true);

  // Χρώματα γραφημάτων
  const fill = ['#5c8fd6','#98b6ea'];

  new Chart(document.getElementById('chartTime'), {
    type:'bar',
    data:{
      labels,
      datasets:[{
        label:'Ημέρες',
        data:[s.avg_completion_days||0, m.avg_completion_days||0],
        backgroundColor:fill,
        borderRadius:8,
        maxBarThickness:48
      }]
    },
    options: baseOptions('Μέσος Χρόνος Περάτωσης')
  });

  new Chart(document.getElementById('chartGrade'), {
    type:'bar',
    data:{
      labels,
      datasets:[{
        label:'Βαθμός',
        data:[s.avg_grade||0, m.avg_grade||0],
        backgroundColor:fill,
        borderRadius:8,
        maxBarThickness:48
      }]
    },
    options: baseOptions('Μέσος Βαθμός')
  });

  new Chart(document.getElementById('chartCount'), {
  type: 'pie',
  data: {
    labels,
    datasets: [{
      data: [s.total_count || 0, m.total_count || 0],
      backgroundColor: ['#0b4ba6', '#9bbce5'],   
      borderColor: '#ffffff',
      borderWidth: 2
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 500 },
    plugins: {
      title: { display: true, text: 'Σύνολο Περατωμένων Διπλωματικών' },
      legend: { position: 'bottom' }
    }
  }
});

}

/* ανάκτηση στατιστικών */
function loadFinishedStats(){
  fetch('listaDiplomatikon.php?action=prof_finished_stats&_=' + Date.now(), { cache:'no-store' })
    .then(r=>r.json())
    .then(js=>{
      if(js && !js.error){ drawCharts(js); }
      else{
        document.getElementById('loader').innerHTML = 'Δεν βρέθηκαν δεδομένα.';
      }
    })
    .catch(()=>{
      document.getElementById('loader').innerHTML = 'Σφάλμα φόρτωσης δεδομένων.';
    });
}
document.addEventListener('DOMContentLoaded', loadFinishedStats);
</script>
</body>
</html>
