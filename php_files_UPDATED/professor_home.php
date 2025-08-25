<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];

// === PDO σύνδεση ===
$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$user = "root";
$pass = "";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Σφάλμα σύνδεσης στη βάση: " . $e->getMessage());
}

$stmt = $pdo->prepare("
    SELECT p.professor_id
    FROM professors p
    JOIN users u ON u.user_id = p.professor_id
    WHERE u.email = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['email']]);
$professor_id = (int)$stmt->fetchColumn();

// === AJAX: Οι ανακοινώσεις μου (όπου είμαι επιβλέπων) ===
if (isset($_GET['action']) && $_GET['action'] === 'my_announcements') {
    header('Content-Type: application/json; charset=utf-8');
    if ($professor_id <= 0) { echo json_encode([]); exit; }

    $sql = "
        SELECT 
            t.thesis_id,
            t.title,
            e.announcements,
            e.exam_date,
            e.exam_time,
            e.exam_mode,
            e.room,
            e.link
        FROM theses t
        JOIN examinations e ON e.thesis_id = t.thesis_id
        WHERE t.supervisor_id = :pid
          AND e.announcements IS NOT NULL
          AND TRIM(e.announcements) <> ''
        ORDER BY COALESCE(e.exam_date, '1970-01-01') DESC, t.thesis_id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':pid' => $professor_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows ?: []);
    exit;
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Πίνακας Έλεγχου</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #333;
        }


        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: rgba(245, 245, 245, 0.9);
            padding: 10px 20px;
            border-bottom: 1px solid #ddd;
        }


        .header a {
            text-decoration: none;
            display: flex;
            align-items: center;
            color: inherit;
        }


        .header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            cursor: pointer;
        }


        .header span {
            font-size: 1.2rem;
            color: #0056b3;
        }


        .logout-button {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }


        .logout-button:hover {
            background-color: #555;
        }


        .notifications-button {
            background-color: #007bff;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            transition: background-color 0.3s ease;
        }


        .notifications-button:hover {
            background-color: #0056b3;
        }


        .container {
            margin: 50px auto;
            padding: 20px;
            width: 90%;
            max-width: 800px;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: center;
        }


        .grid {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }


        .card {
            background-color: #0056b3;
            border-radius: 10px;
            color: white;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            text-align: center;
            transition: transform 0.2s;
            width: 200px;
        }


        .card:hover {
            transform: scale(1.05);
        }


        .card img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 15px;
        }


        .card-title {
            margin-top: 10px;
            font-size: 1rem;
        }

        #notifications-list h4 {
    margin-top: 20px;
    color: #0056b3;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}



        #notifications-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 20px;
            border-radius: 10px;
            z-index: 1000;
        }


        #notifications-popup h3 {
            margin-top: 0;
            color: #0056b3;
        }


        #notifications-popup ul {
            list-style: none;
            padding: 0;
            max-height: 300px;
            overflow-y: auto;
        }


        #notifications-popup ul li {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }


        #notifications-popup ul li a {
            text-decoration: none;
            color: #333;
        }


        #notifications-popup ul li a:hover {
            color: #007bff;
        }


        #notifications-popup .close-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }


        #notifications-popup .close-btn:hover {
            background-color: #c82333;
        }


        #overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }


        html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
}


.container {
    flex: 1;
}


footer {
    background-color: #333;
    color: white;
    text-align: center;
    padding: 15px;
    margin-top: auto;
}


#form-notifications-popup {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    padding: 20px;
    border-radius: 10px;
    z-index: 1000;
}

#form-notifications-popup h3 {
    margin-top: 0;
    color: #28a745;
}

#form-notifications-popup ul {
    list-style: none;
    padding: 0;
    max-height: 300px;
    overflow-y: auto;
}

#form-notifications-popup ul li {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

#form-notifications-popup ul li a {
    text-decoration: none;
    color: #333;
}

#form-notifications-popup ul li a:hover {
    color: #007bff;
}

#form-notifications-popup .close-btn {
    display: inline-block;
    margin-top: 10px;
    padding: 10px 20px;
    background-color: #dc3545;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

#form-notifications-popup .close-btn:hover {
    background-color: #c82333;
}
</style>
</head>
<body>
    
<div class="header">
    <a href="profile_edit.php" class="user-info">
        <img src="User_image.png" alt="User Icon">
        <span id="welcomeMessage">Καλώς ήλθατε, Χρήστη!</span>
    </a>
    <div class="notifications-container">
        <button class="notifications-button" onclick="showNotifications()">🔔</button>
        <button class="notifications-button form-notifications-button" onclick="showFormNotifications()">📩</button>
    </div>
    <button class="logout-button" onclick="logout()">Αποσύνδεση</button>
</div>

<div class="card">
  <h3>Βαθμολόγηση Ενεργών Διπλωματικών</h3>
  <p>Δες τις Ενεργές Διπλωματικές που επιβλέπεις, άλλαξε κατάσταση σε <em>Υπό Εξέταση</em> και καταχώρησε βαθμούς.</p>
  <a href="prof_grade.php" class="button">Μετάβαση</a>
</div>

    <div class="container">
        <h2>Πίνακας Έλεγχου Καθηγητή</h2>
        <p>Εδώ μπορείτε να διαχειρίσετε διπλωματικές, να απαντήσετε σε προσκλήσεις και να δείτε στατιστικά.</p>
        <div class="grid" id="dashboardGrid">
            <!-- Cards will be loaded here dynamically -->
        </div>
    </div>

    <!-- Ανακοινώσεις Μου -->
<div class="container" id="my-announcements" style="max-width:1100px;margin:30px auto;padding:24px;background:#fff;border-radius:14px;box-shadow:0 8px 20px rgba(0,0,0,.08)">
  <h2 style="margin:0 0 12px;color:#0b4ba6">Ανακοινώσεις Μου</h2>
  <p style="color:#555;margin:0 0 16px">Κείμενα ανακοινώσεων για παρουσιάσεις διπλωματικών που επιβλέπεις.</p>

  <div id="ann-list" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
    <div class="ann-card" style="background:#f9fbff;border:1px solid #e7eef7;border-radius:12px;padding:14px">
      <div style="color:#667">Φορτώνει...</div>
    </div>
  </div>
</div>

 <div id="overlay"></div>
    <div id="notifications-popup">
        <h3>Ειδοποιήσεις</h3>
    <div id="notifications-list">
    <!-- Οι ειδοποιήσεις θα φορτώνονται δυναμικά εδώ -->
 </div>

        <button class="close-btn" onclick="closeNotifications()">Κλείσιμο</button>
    </div>

    <!-- Popup για Ειδοποιήσεις από τη Φόρμα -->
<div id="form-notifications-popup">
    <h3>Μηνύματα από τη Φόρμα</h3>
    <div id="form-notifications-list">
        <!-- Οι ειδοποιήσεις θα φορτώνονται δυναμικά εδώ -->
    </div>
    <button class="close-btn" onclick="closeFormNotifications()">Κλείσιμο</button>
</div>

<script>
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function fmtDate(d){ if(!d) return '—'; try{const x=new Date(d); if(isNaN(x)) return d; return x.toLocaleDateString();}catch(_){return d;} }

function loadMyAnnouncements(){
  fetch('professor_home.php?action=my_announcements&_=' + Date.now(), {cache:'no-store'})
    .then(r=>r.json())
    .then(rows=>{
      const wrap = document.getElementById('ann-list');
      if (!wrap) return;
      wrap.innerHTML = '';

      if (!rows || !rows.length){
        wrap.innerHTML = `
          <div class="ann-card" style="background:#fff;border:1px solid #eef3f9;border-radius:12px;padding:16px">
            <div style="color:#667">Δεν υπάρχουν ακόμη ανακοινώσεις.</div>
          </div>`;
        return;
      }

      rows.forEach(row=>{
        const title = row.title || 'Διπλωματική';
        const ann   = row.announcements || '';
        const d     = fmtDate(row.exam_date);
        const tm    = row.exam_time ? row.exam_time.toString().slice(0,5) : '—';
        const mode  = row.exam_mode || '—';
        const place = (row.exam_mode && row.exam_mode.toLowerCase()==='online')
                      ? (row.link || '—')
                      : (row.room || '—');

        const card = document.createElement('div');
        card.className = 'ann-card';
        card.style.cssText = 'background:#fff;border:1px solid #e8eef7;border-radius:12px;padding:16px;box-shadow:0 6px 16px rgba(0,0,0,.04)';

        card.innerHTML = `
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
            <h3 style="margin:0 0 6px;color:#0b4ba6;font-size:1.05rem">${escapeHtml(title)}</h3>
            <span style="font-size:.8rem;background:#f3f6fb;border:1px solid #e3ebf6;border-radius:999px;padding:2px 8px;color:#234">${escapeHtml(mode)}</span>
          </div>
          <div style="font-size:.9rem;color:#445;margin:6px 0 8px">
            <strong>Ημ/νία:</strong> ${escapeHtml(d)} &nbsp;|&nbsp; <strong>Ώρα:</strong> ${escapeHtml(tm)} &nbsp;|&nbsp; <strong>Χώρος/Σύνδεσμος:</strong> ${escapeHtml(place)}
          </div>
          <div style="white-space:pre-wrap;color:#233;border:1px solid #f0f4fa;background:#fbfdff;padding:10px;border-radius:8px">${escapeHtml(ann)}</div>
          <div style="margin-top:8px;font-size:.8rem;color:#7a8594">thesis_id: ${Number(row.thesis_id)}</div>
        `;
        wrap.appendChild(card);
      });
    })
    .catch(()=>{
      const wrap = document.getElementById('ann-list');
      if (!wrap) return;
      wrap.innerHTML = `
        <div class="ann-card" style="background:#fff;border:1px solid #eef3f9;border-radius:12px;padding:16px">
          <div style="color:#b33">Σφάλμα φόρτωσης ανακοινώσεων.</div>
        </div>`;
    });
}

document.addEventListener('DOMContentLoaded', loadMyAnnouncements);
</script>

<script>
    function loadDashboard() {
        fetch('fetch_theses(professor_home).php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('welcomeMessage').textContent = `Καλώς ήλθατε, ${data.name}!`;


                const grid = document.getElementById('dashboardGrid');
                grid.innerHTML = '';


                data.cards.forEach(card => {
                    const cardElement = document.createElement('div');
                    cardElement.className = 'card';
                    cardElement.innerHTML = `
                        <a href="${card.link}" style="color: white;">
                            <img src="${card.image}" alt="">
                            <div class="card-title">${card.title}</div>
                        </a>
                    `;
                    grid.appendChild(cardElement);
                });
            })
            .catch(error => console.error('Error loading dashboard:', error));
    }


    function logout() {
        if (confirm("Θέλετε να αποσυνδεθείτε;")) {
            window.location.href = 'logout.php';
        }
    }


    function showNotifications() {
    document.getElementById('overlay').style.display = 'block';
    document.getElementById('notifications-popup').style.display = 'block';

    fetch('fetch_notifications.php')
    .then(response => response.json())
    .then(data => {
        const notificationsList = document.getElementById('notifications-list');
        notificationsList.innerHTML = '';

        if (data.length > 0) {
            let notificationsHTML = '';

            data.forEach(notification => {
                notificationsHTML += `
                    <li>
                        <strong>Πρόσκληση Επιτροπής</strong><br>
                        Φοιτητής ID: ${notification.student_id} - Διπλωματική ID: ${notification.thesis_id}<br>
                        Κατάσταση: ${notification.status} <br>
                        <small>Ημερομηνία: ${notification.sent_at}</small>
                    </li>`;
            });

            notificationsList.innerHTML = notificationsHTML;
        } else {
            notificationsList.innerHTML = '<li>Δεν υπάρχουν νέες ειδοποιήσεις.</li>';
        }
    })
    .catch(error => {
        notificationsList.innerHTML = `<li>Σφάλμα: ${error.message}</li>`;
    });

}

function showFormNotifications() {
    document.getElementById('overlay').style.display = 'block';
    document.getElementById('notifications-popup').style.display = 'block';

    fetch('fetch_form_notifications.php')
    .then(response => response.json())
    .then(data => {
        const notificationsList = document.getElementById('notifications-list');
        notificationsList.innerHTML = '';

        if (data.length > 0) {
            let notificationsHTML = '';

            data.forEach(notification => {
                notificationsHTML += `
                    <li>
                        <strong>${notification.student_name} ${notification.student_surname}</strong> - 
                        <em>${notification.thesis_title}</em><br>
                        <strong>Θέμα:</strong> ${notification.topic} <br>
                        <strong>Μήνυμα:</strong> ${notification.message} <br>
                        <small>Ημερομηνία: ${notification.created_at}</small>
                    </li>`;
            });

            notificationsList.innerHTML = notificationsHTML;
        } else {
            notificationsList.innerHTML = '<li>Δεν υπάρχουν νέες ειδοποιήσεις.</li>';
        }
    })
    .catch(error => {
        notificationsList.innerHTML = `<li>Σφάλμα: ${error.message}</li>`;
    });
}


function closeFormNotifications() {
    document.getElementById('overlay').style.display = 'none';
    document.getElementById('form-notifications-popup').style.display = 'none';
}


function markAsRead(notificationId) {
    fetch('mark_notification_as_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: notificationId }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Η ειδοποίηση μαρκάρεται ως διαβασμένη.');
                showNotifications(); // Επαναφόρτωση ειδοποιήσεων
            } else {
                alert('Αποτυχία ενημέρωσης.');
            }
        })
        .catch(error => console.error('Σφάλμα:', error));
}

    function closeNotifications() {
        document.getElementById('overlay').style.display = 'none';
        document.getElementById('notifications-popup').style.display = 'none';
    }


    window.addEventListener('popstate', function () {
        if (confirm("Θέλετε να αποσυνδεθείτε;")) {
            window.location.href = "logout.php";
        } else {
            history.pushState(null, document.title, location.href);
        }
    });


    window.addEventListener('load', function () {
        history.pushState(null, document.title, location.href);
    });


    document.addEventListener('DOMContentLoaded', loadDashboard);
</script>

<footer style="background-color: #333; color: white; text-align: center; padding: 15px; margin-top: 20px;">
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>  
</body>
</html>
