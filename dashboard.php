<?php
// dashboard.php
session_start();

// Simple Sales Dashboard (Chart.js + Bootstrap) using mysqli (no PDO)
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$DB_HOST = 'localhost';
$DB_USER = 's67160334';
$DB_PASS = 'xRka8xEU';
$DB_NAME = 's67160334';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  die('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) { return []; }
  $rows = [];
  while ($row = $res->fetch_assoc()) { $rows[] = $row; }
  $res->free();
  return $rows;
}

// เตรียมข้อมูลสำหรับกราฟต่าง ๆ
$monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
$category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
// [FIX] เพิ่ม ORDER BY DESC LIMIT 10 เพื่อให้แสดง Top 10 ตามความเป็นจริง
$topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products ORDER BY qty_sold DESC LIMIT 10");
$payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
$newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");
$kpis = fetch_all($mysqli, "
  SELECT
    (SELECT SUM(net_amount) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
    (SELECT SUM(quantity)   FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
    (SELECT COUNT(DISTINCT customer_id) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
");
$kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];

// Helper for number format
function nf($n) { return number_format((float)$n, 2); }
function n($n) { return number_format((int)$n); } // Helper for integer
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Retail DW Dashboard</title>
  
  <!-- [STYLE] ใช้ Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- [STYLE] ฟอนต์ 'Kanit' -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;700&display=swap" rel="stylesheet">

  <!-- [STYLE] เพิ่ม Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  
  <style>
    /* [STYLE][EDIT] ปรับปรุงธีมเป็น Vibrant Summer Light Theme */
    body { 
      /* [EDIT] พื้นหลังสีขาวสะอาดตา */
      background: #fcfcfc; 
      color: #1f2937;     /* Gray 800 (ตัวหนังสือหลัก) */
      font-family: 'Kanit', sans-serif; 
    }
    .card { 
      background: #ffffff; 
      /* [EDIT] ขอบสีอ่อนลง */
      border: 1px solid #f3f4f6; /* Gray 100 Border */
      border-radius: 1rem; /* โค้งมนมากขึ้น */
      height: 100%; 
      /* [EDIT] เงาที่ชัดเจนขึ้นเล็กน้อย */
      box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.02);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card:hover {
        transform: translateY(-3px);
        /* [EDIT] เพิ่มสีเขียวมิ้นต์อ่อนๆ ที่เงาเมื่อ hover */
        box-shadow: 0 15px 20px -5px rgba(20, 184, 166, 0.2), 0 6px 6px -3px rgba(0,0,0,0.05);
    }
    .card-title { 
      color: #374151; /* Gray 700 */
      font-weight: 600;
    }
    .kpi { 
      font-size: 1.8rem; /* ขยายขนาด KPI */
      font-weight: 700; 
      /* [EDIT] สีหลักสำหรับ KPI เป็น Teal */
      color: #14b8a6; /* Teal 500 */
    }
    .kpi-icon {
      font-size: 2rem;
      /* [EDIT] สีไอคอน Teal */
      color: #14b8a6; 
      opacity: 0.9;
    }
    .navbar {
      border-bottom: 1px solid #e5e7eb; /* Gray 200 Border */
      background-color: #ffffff !important;
      box-shadow: 0 2px 4px rgb(0 0 0 / 0.05);
    }
    .navbar-light .navbar-brand {
      color: #1f2937; /* Gray 800 */
      font-weight: 700;
    }
    canvas { 
      max-height: 400px; /* เพิ่มความสูงของกราฟหลัก */
      width: 100%;
    }
  </style>
</head>
<body class="p-3 p-md-4">
  <nav class="navbar navbar-expand-lg navbar-light mb-4">
    <div class="container-fluid">
      <span class="navbar-brand">Retail Dashboard</span>
      <div class="d-flex align-items-center gap-3">
        <span class="navbar-text small text-muted">
          Hi, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?>
        </span>
        <a class="btn btn-outline-secondary btn-sm" href="logout.php">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h2 class="mb-0 h4" style="font-weight: 700;">ภาพรวมยอดขาย (Retail DW)</h2>
      <span class="text-muted small">แหล่งข้อมูล: MySQL (mysqli)</span>
    </div>

    <!-- KPI -->
    <div class="row g-4 mb-4">
      <div class="col-lg-4 col-md-6 col-12">
        <div class="card">
          <div class="card-body d-flex flex-column justify-content-between">
            <h6 class="card-title text-muted">ยอดขาย 30 วัน</h6>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div>
              <i class="bi bi-cash-coin kpi-icon"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 col-12">
        <div class="card">
          <div class="card-body d-flex flex-column justify-content-between">
            <h6 class="card-title text-muted">จำนวนชิ้นขาย 30 วัน</h6>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <div class="kpi"><?= n($kpi['qty_30d']) ?> ชิ้น</div>
              <i class="bi bi-box-seam kpi-icon"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 col-12">
        <div class="card">
          <div class="card-body d-flex flex-column justify-content-between">
            <h6 class="card-title text-muted">จำนวนผู้ซื้อ 30 วัน</h6>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <div class="kpi"><?= n($kpi['buyers_30d']) ?> คน</div>
              <i class="bi bi-person-check kpi-icon"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts grid -->
    <div class="row g-4">

      <div class="col-lg-8 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">ยอดขายรายเดือน (2 ปี)</h6>
            <canvas id="chartMonthly"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">สัดส่วนยอดขายตามหมวด</h6>
            <canvas id="chartCategory"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-6 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">Top 10 สินค้าขายดี (ตามจำนวน)</h6>
            <canvas id="chartTopProducts"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-6 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">ยอดขายตามภูมิภาค</h6>
            <canvas id="chartRegion"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-6 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">วิธีการชำระเงิน</h6>
            <canvas id="chartPayment"></canvas>
          </div>
        </div>
      </div>

      <div class="col-lg-6 col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">ยอดขายรายชั่วโมง</h6>
            <canvas id="chartHourly"></canvas>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title mb-3">ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h6>
            <canvas id="chartNewReturning"></canvas>
          </div>
        </div>
      </div>

    </div>
  </div>

<script>
// เตรียมข้อมูลจาก PHP -> JS
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

// Utility: pick labels & values
const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => parseFloat(o[y])) });


// --- [STYLE][EDIT] Chart.js Vibrant Summer Theme ---
Chart.defaults.color = '#1f2937'; // สีฟอนต์สากล (Gray 800)
Chart.defaults.borderColor = 'rgba(0, 0, 0, 0.08)'; // สีเส้นกริด (จางกว่าเดิม)

// [EDIT] ชุดสีใหม่ Vibrant Teal, Amber, Indigo
const chartColors = [
  '#14b8a6', // Teal 500
  '#f59e0b', // Amber 500
  '#6366f1', // Indigo 500
  '#ef4444', // Red 500
  '#d946ef', // Fuchsia 500
  '#22c55e', // Green 500
  '#3b82f6', // Blue 500
  '#f472b6', // Pink 400
];

// Helper เพื่อใช้สี
const applyColors = (datasets) => {
  return datasets.map((d, i) => ({
    ...d,
    // [EDIT] เติม alpha (ความโปร่งใส) ให้อ่อนลง 40% ('66')
    backgroundColor: chartColors[i % chartColors.length] + '66', // '66' = ~40% alpha
    borderColor: chartColors[i % chartColors.length],
    borderWidth: 2,
    borderRadius: 6, // เพิ่มความโค้งมนให้ Bar Chart
  }));
};
// --- สิ้นสุดการตั้งค่า Chart.js ---


// Monthly (Line Chart)
(() => {
  const {labels, values} = toXY(monthly, 'ym', 'net_sales');
  new Chart(document.getElementById('chartMonthly'), {
    type: 'line',
    data: { 
      labels, 
      datasets: [{
        label: 'ยอดขาย (฿)', 
        data: values, 
        tension: .35, 
        fill: true,
        // [EDIT] ใช้สี Teal เป็นหลักสำหรับ Line Chart
        backgroundColor: '#14b8a6' + '30', // Teal 500 with high transparency
        borderColor: '#14b8a6', 
        borderWidth: 3,
        pointRadius: 4,
        pointBackgroundColor: '#14b8a6'
      }]
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { legend: { display: false } }
    }
  });
})();

// Category (Doughnut)
(() => {
  const {labels, values} = toXY(category, 'category', 'net_sales');
  new Chart(document.getElementById('chartCategory'), {
    type: 'doughnut',
    data: { 
      labels, 
      datasets: [{ 
        data: values,
        backgroundColor: chartColors, // Pie/Doughnut ใช้สีโดยตรง
        borderColor: '#ffffff', 
        borderWidth: 4, // ขอบหนาขึ้น
      }] 
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      cutout: '70%', // ช่องว่างกว้างขึ้น
      plugins: { legend: { position: 'bottom' } } 
    }
  });
})();

// Top products (Bar Chart - Horizontal)
(() => {
  const labels = topProducts.map(o => o.product_name);
  const qty = topProducts.map(o => parseInt(o.qty_sold));
  new Chart(document.getElementById('chartTopProducts'), {
    type: 'bar',
    data: { 
      labels, 
      datasets: applyColors([
        // [EDIT] ใช้สี Amber เป็นหลัก
        { label: 'ชิ้นที่ขาย', data: qty, backgroundColor: '#f59e0b' + '66', borderColor: '#f59e0b' }
      ])
    },
    options: {
      indexAxis: 'y', 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { legend: { display: false } } 
    }
  });
})();

// Region (Bar Chart - Vertical)
(() => {
  const {labels, values} = toXY(region, 'region', 'net_sales');
  new Chart(document.getElementById('chartRegion'), {
    type: 'bar',
    data: { 
      labels, 
      datasets: applyColors([
        // [EDIT] ใช้สี Indigo
        { label: 'ยอดขาย (฿)', data: values, backgroundColor: '#6366f1' + '66', borderColor: '#6366f1' }
      ])
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { legend: { display: false } } 
    }
  });
})();

// Payment (Pie Chart)
(() => {
  const {labels, values} = toXY(payment, 'payment_method', 'net_sales');
  new Chart(document.getElementById('chartPayment'), {
    type: 'pie', 
    data: { 
      labels, 
      datasets: [{ 
        data: values,
        backgroundColor: chartColors,
        borderColor: '#ffffff', 
        borderWidth: 4           
      }] 
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } } 
    }
  });
})();

// Hourly (Bar Chart)
(() => {
  const {labels, values} = toXY(hourly, 'hour_of_day', 'net_sales');
  new Chart(document.getElementById('chartHourly'), {
    type: 'bar',
    data: { 
      labels, 
      datasets: applyColors([
        // [EDIT] ใช้สี Fuchsia
        { label: 'ยอดขาย (฿)', data: values, backgroundColor: '#d946ef' + '66', borderColor: '#d946ef' }
      ])
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: { legend: { display: false } } 
    }
  });
})();

// New vs Returning (Multi-Line Chart)
(() => {
  const labels = newReturning.map(o => o.date_key);
  const newC = newReturning.map(o => parseFloat(o.new_customer_sales));
  const retC = newReturning.map(o => parseFloat(o.returning_sales));
  new Chart(document.getElementById('chartNewReturning'), {
    type: 'line',
    data: { 
      labels,
      datasets: [ // [EDIT] กำหนดสี Teal/Amber
        { 
            label: 'ลูกค้าใหม่ (฿)', 
            data: newC, 
            tension: .35, 
            fill: false, 
            borderColor: '#14b8a6', // Teal 500
            borderWidth: 3,
            pointRadius: 4
        },
        { 
            label: 'ลูกค้าเดิม (฿)', 
            data: retC, 
            tension: .35, 
            fill: false, 
            borderColor: '#f59e0b', // Amber 500
            borderWidth: 3,
            pointRadius: 4
        }
      ]
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      scales: {
        x: { ticks: { maxTicksLimit: 12 } } 
      }
    }
  });
})();
</script>

</body>
</html>
