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
<meta name="viewport" content="width=device-width,initial-scale=1.0" />
<title>Στατιστικά Περατωμένων Διπλωματικών</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f6f8fb;color:#333}
  .wrap{max-width:1000px;margin:24px auto;padding:16px}
  h1{font-size:22px;margin:0 0 12px}
  .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin:16px 0}
  .card{background:#fff;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.08);padding:16px}
  .summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:12px}
  .kpi{background:#fff;border:1px solid #eee;border-radius:10px;padding:12px;text-align:center}
  .kpi .lbl{font-size:12px;color:#666}
  .kpi .val{font-size:20px;font-weight:700;margin-top:6px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Στατιστικά Περατωμένων Διπλωματικών</h1>

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

  <div class="cards">
    <div class="card">
      <canvas id="chartTime" height="140"></canvas>
    </div>
    <div class="card">
      <canvas id="chartGrade" height="140"></canvas>
    </div>
    <div class="card" style="grid-column:1/-1">
      <canvas id="chartCount" height="160"></canvas>
    </div>
  </div>
</div>

<script>
function animateKPI(id, target){
  const el = document.getElementById(id);
  let v=0, step = Math.max(target/40, 0.5);
  const tick = () => {
    v += step;
    if (v >= target) { el.textContent = target.toFixed(1); return; }
    el.textContent = v.toFixed(1);
    requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}

function drawCharts(stats){
  const s = stats.supervised || {};
  const m = stats.member || {};
  const labels = ['Επιβλέπω','Συμμετέχω'];

  // KPIs (απλή σύνοψη)
  const avgTime  = ((s.avg_completion_days||0)+(m.avg_completion_days||0))/2;
  const avgGrade = ((s.avg_grade||0)+(m.avg_grade||0))/2;
  const total    = (s.total_count||0)+(m.total_count||0);

  animateKPI('kpiTime',  Number(avgTime||0));
  animateKPI('kpiGrade', Number(avgGrade||0));
  animateKPI('kpiCount', Number(total||0));

  const baseOptions = (title) => ({
    responsive:true,
    plugins:{ title:{ display:true, text:title } },
    scales:{ y:{ beginAtZero:true } }
  });

  new Chart(document.getElementById('chartTime'), {
    type:'bar',
    data:{
      labels,
      datasets:[{ label:'Ημέρες', data:[s.avg_completion_days||0, m.avg_completion_days||0] }]
    },
    options: baseOptions('Μέσος Χρόνος Περάτωσης (Περατωμένες)')
  });

  new Chart(document.getElementById('chartGrade'), {
    type:'bar',
    data:{
      labels,
      datasets:[{ label:'Βαθμός', data:[s.avg_grade||0, m.avg_grade||0] }]
    },
    options: baseOptions('Μέσος Βαθμός (Περατωμένες)')
  });

  new Chart(document.getElementById('chartCount'), {
    type:'bar',
    data:{
      labels,
      datasets:[{ label:'Πλήθος', data:[s.total_count||0, m.total_count||0] }]
    },
    options: baseOptions('Σύνολο Περατωμένων Διπλωματικών')
  });
}

function loadFinishedStats(){
  fetch('listaDiplomatikon.php?action=prof_finished_stats&_=' + Date.now(), { cache:'no-store' })
    .then(r=>r.json())
    .then(js=>{
      if (js && !js.error) { drawCharts(js); }
      else { console.error('prof_finished_stats error:', js); }
    })
    .catch(err=>console.error(err));
}

document.addEventListener('DOMContentLoaded', loadFinishedStats);
</script>
</body>
</html>
