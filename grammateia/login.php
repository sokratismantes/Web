
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Σύστημα Υποστήριξης Διπλωματικών Εργασιών</title>
<style>
body {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
    background: url('ggeffyra.png') no-repeat center center fixed;
    background-size: cover;
}
header {
    display: flex;
    flex-direction: column;
    align-items: left;
    justify-content: center;
    height: 100px;
    background-color: rgba(255, 255, 255, 0.1);
}
.header-content {
    display: flex;
    align-items: right;
    justify-content: space-between;
    width: 100%;
    max-width: 3000px;
}
.logo {
    display: flex;
    align-items: center;
}
.logo img {
    width: 200px;
    height: auto;
    margin-right: 15px;
}
.title {
    font-size: 1.4rem;
    margin: 200px;
    color: #003366;
    font-weight: bold;
    text-align: right;
}
.login-box {
    text-align: center;
    margin: 110px auto 0;
    padding: 20px;
    width: 280px;
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 70px;
}
.login-box h2 {
    color: #39608f;
    margin-bottom: 20px;
    font-size: 1.3rem;
}
.login-box img {
    width: 60px;
    height: 60px;
    margin-bottom: 10px;
    border-radius: 40%;
    background-color: #ddd;
}
.login-box input {
    display: block;
    width: 80%;
    margin: 10px auto;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 20px;
    font-size: 1rem;
    background-color: rgba(255, 255, 255, 0.3);
}
.login-box button {
    display: block;
    width: 80%;
    padding: 10px;
    background-color: #003366;
    color: white;
    border: none;
    font-size: 1rem;
    cursor: pointer;
    border-radius: 20px;
    margin: 10px auto;
}
.login-box button:hover {
    background-color: #003f7f;
}
.announcements-btn {
    position: fixed;
    top: 20px;
    right: 20px;
    background-color: #003366;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    font-size: 1rem;
}
.announcements-btn:hover {
    background-color: #003f7f;
}
</style>
</head>
<body>

<header>
    <div class="header-content">
        <div class="logo">
            <img src="ceidlogo.png" alt="Λογότυπο">
        </div>
        <div class="title">Σύστημα Υποστήριξης Διπλωματικών Εργασιών</div>
    </div>
</header>

<main>
    <div class="login-box">
        <h2>Log In</h2>
        <img src="User_image.png" alt="User Icon">
        <form id="loginForm">
            <input type="text" name="username" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Σύνδεση</button>
        </form>
        <div id="loginMessage" style="color:red; margin-top:10px; font-size:0.9rem;"></div>
    </div>
</main>

<a href="announcements.php?from=01012025&to=31122025&format=json"
   target="_blank"
   class="announcements-btn">
   Ανακοινώσεις
</a>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    const messageDiv = document.getElementById('loginMessage');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        messageDiv.textContent = '';

        const username = form.username.value.trim();
        const password = form.password.value.trim();

        if (!username || !password) {
            messageDiv.textContent = "Παρακαλώ συμπληρώστε email και κωδικό.";
            return;
        }

        try {
            const response = await fetch('fetch_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
            });

            const data = await response.json();

            if (data.status === 'success') {
                window.location.href = data.redirect;
            } else {
                messageDiv.textContent = data.message;
            }
        } catch (err) {
            console.error(err);
            messageDiv.textContent = "Σφάλμα κατά τη σύνδεση. Δοκιμάστε ξανά.";
        }
    });
});
</script>

</body>
</html>
