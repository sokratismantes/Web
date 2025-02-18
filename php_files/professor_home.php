<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}


$email = $_SESSION['email'];
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


    

    <div class="container">
        <h2>Πίνακας Έλεγχου Καθηγητή</h2>
        <p>Εδώ μπορείτε να διαχειρίσετε διπλωματικές, να απαντήσετε σε προσκλήσεις και να δείτε στατιστικά.</p>
        <div class="grid" id="dashboardGrid">
            <!-- Cards will be loaded here dynamically -->
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


<script>
    let chatbotVisible = false;


    function toggleChatbot() {
        chatbotVisible = !chatbotVisible;
        document.getElementById("chatbot-body").style.display = chatbotVisible ? "flex" : "none";
    }


    function handleQuestion(question) {
        addChatMessage("Χρήστης", question);
        getChatbotResponse(question);
    }


    function addChatMessage(sender, message) {
        const messageContainer = document.createElement("div");
        messageContainer.textContent = `${sender}: ${message}`;
        messageContainer.style.margin = "5px 0";
        document.getElementById("chatbot-messages").appendChild(messageContainer);
    }


    function getChatbotResponse(question) {
    const responses = {
        "Πώς μπορώ να προσθέσω διπλωματική;": "Για να προσθέσετε διπλωματική, επισκεφθείτε τη σελίδα 'Διαχείριση Διπλωματικών'.",
        "Πού βλέπω τις ειδοποιήσεις μου;": "Οι ειδοποιήσεις σας εμφανίζονται στο καμπανάκι.",
        "Πώς να επικοινωνήσω με τη γραμματεία;": "Μπορείτε να επικοινωνήσετε μέσω email: secretary@ceid.upatras.gr.",
    };


    const botResponse = responses[question] || "Λυπάμαι, δεν καταλαβαίνω την ερώτηση.";
    addChatMessage("Chatbot", botResponse);
}


</script>
</body>
</html>




</body>
 
<div id="chatbot-toggle" onclick="toggleChatbot()">💬</div>
<div id="chatbot-body">
    <div id="chatbot-header">
        <span>Βοήθεια Chatbot</span>
        <button onclick="toggleChatbot()">✖</button>
    </div>
    <div id="chatbot-messages"></div>
    <div id="chatbot-questions">
        <button onclick="handleQuestion('Πώς μπορώ να προσθέσω διπλωματική;')">Πώς μπορώ να προσθέσω διπλωματική;</button>
        <button onclick="handleQuestion('Πού βλέπω τις ειδοποιήσεις μου;')">Πού βλέπω τις ειδοποιήσεις μου;</button>
        <button onclick="handleQuestion('Πώς να επικοινωνήσω με τη γραμματεία;')">Πώς να επικοινωνήσω με τη γραμματεία;</button>
    </div>
</div>




<style>
/* Chatbot toggle button */
#chatbot-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 60px;
    height: 60px;
    background-color: #007bff;
    color: white;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 2rem;
    cursor: pointer;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
    z-index: 1000;
}


/* Chatbot body */
#chatbot-body {
    position: fixed;
    bottom: 90px;
    right: 20px;
    width: 300px;
    height: 400px;
    background-color: white;
    border: 1px solid #ddd;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    display: none;
    flex-direction: column;
    z-index: 1000;
}


/* Chatbot header */
#chatbot-header {
    background-color: #007bff;
    color: white;
    padding: 10px;
    border-radius: 10px 10px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}


/* Chatbot messages */
#chatbot-messages {
    flex: 1;
    padding: 10px;
    overflow-y: auto;
    font-size: 14px;
    border-bottom: 1px solid #ddd;
}


/* Chatbot questions */
#chatbot-questions {
    padding: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
}


#chatbot-questions button {
    padding: 8px 10px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 12px;
    cursor: pointer;
}


#chatbot-questions button:hover {
    background-color: #0056b3;
}


</style>


<footer style="background-color: #333; color: white; text-align: center; padding: 15px; margin-top: 20px;">
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>  
</body>
</html>
