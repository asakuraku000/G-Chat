<?php
session_start();

$host = 'localhost';
$dbname = 'gchat';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_username (username)
    )");
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_username':
            $user = trim($_POST['username']);
            if (!empty($user)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$user]);
                $exists = $stmt->fetch() ? true : false;
                echo json_encode(['exists' => $exists]);
            } else {
                echo json_encode(['exists' => false]);
            }
            exit;
            
        case 'register':
            $user = trim($_POST['username']);
            $pass = $_POST['password'];
            
            if (empty($user) || empty($pass)) {
                echo json_encode(['success' => false, 'error' => 'Username and password required']);
                exit;
            }
            
            if (strlen($user) < 2) {
                echo json_encode(['success' => false, 'error' => 'Username must be at least 2 characters']);
                exit;
            }
            
            if (strlen($pass) < 6) {
                echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$user]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Username already exists']);
                exit;
            }
            
            $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, created_at) VALUES (?, ?, NOW())");
            
            if ($stmt->execute([$user, $hashedPassword])) {
                $_SESSION['username'] = $user;
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Registration failed']);
            }
            exit;
            
        case 'login':
            $user = trim($_POST['username']);
            $pass = $_POST['password'];
            
            if (empty($user) || empty($pass)) {
                echo json_encode(['success' => false, 'error' => 'Username and password required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
            $stmt->execute([$user]);
            $userData = $stmt->fetch();
            
            if ($userData && password_verify($pass, $userData['password'])) {
                $_SESSION['username'] = $user;
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
            }
            exit;
            
        case 'send_message':
            if (!isset($_SESSION['username'])) {
                echo json_encode(['success' => false, 'error' => 'Not logged in']);
                exit;
            }
            
            $message = trim($_POST['message']);
            $temp_id = $_POST['temp_id'] ?? '';
            
            if (!empty($message)) {
                $stmt = $pdo->prepare("INSERT INTO messages (username, message, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$_SESSION['username'], $message]);
                $message_id = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true, 
                    'message_id' => $message_id,
                    'temp_id' => $temp_id
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            }
            exit;
            
        case 'get_messages':
            $last_id = $_POST['last_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT id, username, message, created_at FROM messages WHERE id > ? ORDER BY created_at ASC LIMIT 50");
            $stmt->execute([$last_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($messages);
            exit;
            
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            exit;
    }
}

$current_user = $_SESSION['username'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gchat - Global Chat</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0FA4AF 0%, #003135 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            padding: 20px 0;
        }
        
        .header h1 {
            font-size: 3.5em;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .auth-container {
            background: #AFDDE5;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 450px;
            margin: 0 auto;
            color: #003135;
        }
        
        .auth-container h2 {
            margin-bottom: 30px;
            color: #003135;
            font-size: 2em;
        }
        
        .chat-container {
            display: none;
            background: #AFDDE5;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            flex: 1;
            flex-direction: column;
            max-height: 80vh;
        }
        
        .chat-header {
            background: #003135;
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-header h2 {
            font-size: 1.5em;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .online-indicator {
            width: 12px;
            height: 12px;
            background: #4CAF50;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            background: #AFDDE5;
            min-height: 400px;
        }
        
        .message {
            margin-bottom: 20px;
            padding: 15px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #0FA4AF;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .message.own {
            background: #003135;
            color: white;
            margin-left: 80px;
            border-left: 4px solid #024950;
        }
        
        .message.sending {
            opacity: 0.6;
            transform: scale(0.98);
        }
        
        .message.failed {
            background: #964734;
            color: white;
            border-left: 4px solid #ff4757;
        }
        
        .message-header {
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.1em;
        }
        
        .message.own .message-header {
            color: #AFDDE5;
        }
        
        .message-time {
            font-size: 0.85em;
            opacity: 0.7;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .message-content {
            line-height: 1.5;
            font-size: 1em;
            word-wrap: break-word;
        }
        
        .message-input-container {
            padding: 25px;
            background: #003135;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .message-input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #024950;
            border-radius: 25px;
            outline: none;
            transition: all 0.3s ease;
            font-size: 16px;
            background: white;
        }
        
        .message-input:focus {
            border-color: #0FA4AF;
            box-shadow: 0 0 0 3px rgba(15, 164, 175, 0.1);
        }
        
        .message-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .send-btn {
            background: #0FA4AF;
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 16px;
            min-width: 80px;
        }
        
        .send-btn:hover:not(:disabled) {
            background: #024950;
            transform: scale(1.05);
        }
        
        .send-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .input-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .input-group input {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid #024950;
            border-radius: 15px;
            font-size: 16px;
            outline: none;
            transition: all 0.3s ease;
            background: white;
        }
        
        .input-group input:focus {
            border-color: #0FA4AF;
            box-shadow: 0 0 0 3px rgba(15, 164, 175, 0.1);
        }
        
        .input-group label {
            position: absolute;
            left: 20px;
            top: 18px;
            color: #666;
            transition: all 0.3s ease;
            pointer-events: none;
            background: white;
            padding: 0 5px;
        }
        
        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: -8px;
            font-size: 12px;
            color: #0FA4AF;
        }
        
        .btn {
            background: #003135;
            color: white;
            border: none;
            padding: 18px 35px;
            border-radius: 15px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn:hover:not(:disabled) {
            background: #024950;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid #003135;
            color: #003135;
            margin-top: 15px;
        }
        
        .btn-secondary:hover:not(:disabled) {
            background: #003135;
            color: white;
        }
        
        .retry-btn {
            background: #964734;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8em;
            margin-left: 10px;
            transition: all 0.3s ease;
        }
        
        .retry-btn:hover {
            background: #7a3529;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(45deg, #0FA4AF, #024950);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2em;
            margin-right: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .logout-btn {
            background: transparent;
            border: 2px solid white;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: white;
            color: #003135;
        }
        
        .no-messages {
            text-align: center;
            color: #024950;
            font-style: italic;
            margin-top: 50px;
        }
        
        .error-message {
            color: #ff4757;
            font-size: 14px;
            margin-top: 10px;
            padding: 10px;
            background: rgba(255, 71, 87, 0.1);
            border-radius: 8px;
            display: none;
        }
        
        .success-message {
            color: #4CAF50;
            font-size: 14px;
            margin-top: 10px;
            padding: 10px;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 8px;
            display: none;
        }
        
        .countdown-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .countdown-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .countdown-number {
            font-size: 4em;
            font-weight: bold;
            color: #0FA4AF;
            margin: 20px 0;
        }
        
        .form-toggle {
            text-align: center;
            margin-top: 20px;
            color: #024950;
        }
        
        .form-toggle a {
            color: #0FA4AF;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        .form-toggle a:hover {
            text-decoration: underline;
        }
        
        .cooldown-timer {
            font-size: 12px;
            color: #666;
            margin-left: 10px;
        }
        
        .messages-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .messages-container::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
        }
        
        .messages-container::-webkit-scrollbar-thumb {
            background: #024950;
            border-radius: 4px;
        }
        
        .messages-container::-webkit-scrollbar-thumb:hover {
            background: #003135;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 2.5em;
            }
            
            .message.own {
                margin-left: 20px;
            }
            
            .auth-container {
                padding: 30px 20px;
            }
        }
        
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gchat</h1>
            <p>Connect with the world in real-time</p>
        </div>
        
        <div class="auth-container" id="authContainer">
            <h2 id="authTitle">Join the Conversation</h2>
            
            <div class="error-message" id="errorMessage"></div>
            <div class="success-message" id="successMessage"></div>
            
            <div class="input-group">
                <input type="text" id="usernameInput" placeholder=" " maxlength="20" autocomplete="username">
                <label for="usernameInput">Username</label>
            </div>
            
            <div class="input-group" id="passwordGroup">
                <input type="password" id="passwordInput" placeholder=" " autocomplete="current-password">
                <label for="passwordInput">Password</label>
            </div>
            
            <button class="btn" id="authBtn" onclick="handleAuth()">Join Chat</button>
            
            <div class="form-toggle" id="formToggle">
                <span id="toggleText">Already have an account? <a onclick="toggleAuthMode()">Login here</a></span>
            </div>
        </div>
        
        <div class="chat-container" id="chatContainer">
            <div class="chat-header">
                <div class="user-info">
                    <div class="user-avatar" id="userAvatar"></div>
                    <div>
                        <h2>Global Chat</h2>
                        <span>Welcome, <strong id="currentUser"></strong></span>
                        <span class="online-indicator"></span>
                    </div>
                </div>
                <button class="logout-btn" onclick="logout()">Logout</button>
            </div>
            
            <div class="messages-container" id="messagesContainer">
                <div class="no-messages" id="noMessages">
                    No messages yet. Be the first to say hello!
                </div>
            </div>
            
            <div class="message-input-container">
                <input type="text" class="message-input" id="messageInput" placeholder="Type your message here..." maxlength="500">
                <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                    <span id="sendBtnText">Send</span>
                    <span class="cooldown-timer" id="cooldownTimer"></span>
                </button>
            </div>
        </div>
        
        <div class="countdown-overlay" id="countdownOverlay">
            <div class="countdown-content">
                <h3>Please wait before sending another message</h3>
                <div class="countdown-number" id="countdownNumber">2</div>
                <p>This helps keep the chat enjoyable for everyone</p>
            </div>
        </div>
    </div>

    <script>
        let lastMessageId = 0;
        let currentUser = null;
        let pendingMessages = new Map();
        let messageQueue = [];
        let pollInterval;
        let messageCooldown = false;
        let cooldownTimer = null;
        let isLoginMode = false;
        
        $(document).ready(function() {
            <?php if ($current_user): ?>
                currentUser = '<?php echo addslashes($current_user); ?>';
                showChat();
            <?php endif; ?>
            
            $('#messageInput').keypress(function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            
            $('#usernameInput, #passwordInput').keypress(function(e) {
                if (e.which === 13) {
                    handleAuth();
                }
            });
            
            $('#usernameInput').on('input', function() {
                const username = $(this).val().trim();
                if (username.length >= 2) {
                    checkUsername(username);
                }
            });
        });
        
        function checkUsername(username) {
            $.post('', {
                action: 'check_username',
                username: username
            }, function(response) {
                const data = JSON.parse(response);
                if (data.exists) {
                    switchToLoginMode();
                } else {
                    switchToSignupMode();
                }
            });
        }
        
        function switchToLoginMode() {
            if (!isLoginMode) {
                isLoginMode = true;
                $('#authTitle').text('Welcome Back!');
                $('#authBtn').text('Login');
                $('#toggleText').html('New to Gchat? <a onclick="toggleAuthMode()">Create account</a>');
                $('#passwordInput').attr('placeholder', ' ').attr('autocomplete', 'current-password');
            }
        }
        
        function switchToSignupMode() {
            if (isLoginMode) {
                isLoginMode = false;
                $('#authTitle').text('Create Account');
                $('#authBtn').text('Create Account');
                $('#toggleText').html('Already have an account? <a onclick="toggleAuthMode()">Login here</a>');
                $('#passwordInput').attr('placeholder', ' ').attr('autocomplete', 'new-password');
            }
        }
        
        function toggleAuthMode() {
            if (isLoginMode) {
                switchToSignupMode();
            } else {
                switchToLoginMode();
            }
            clearMessages();
        }
        
        function handleAuth() {
            const username = $('#usernameInput').val().trim();
            const password = $('#passwordInput').val();
            
            if (!username || !password) {
                showError('Please fill in all fields');
                return;
            }
            
            if (username.length < 2) {
                showError('Username must be at least 2 characters long');
                return;
            }
            
            if (!isLoginMode && password.length < 6) {
                showError('Password must be at least 6 characters long');
                return;
            }
            
            const action = isLoginMode ? 'login' : 'register';
            $('#authBtn').prop('disabled', true).text('Please wait...');
            
            $.post('', {
                action: action,
                username: username,
                password: password
            }, function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    currentUser = username;
                    showSuccess(isLoginMode ? 'Login successful!' : 'Account created successfully!');
                    setTimeout(() => {
                        showChat();
                    }, 1000);
                } else {
                    showError(data.error || 'Authentication failed');
                    $('#authBtn').prop('disabled', false).text(isLoginMode ? 'Login' : 'Create Account');
                }
            }).fail(function() {
                showError('Connection failed. Please try again.');
                $('#authBtn').prop('disabled', false).text(isLoginMode ? 'Login' : 'Create Account');
            });
        }
        
        function showChat() {
            $('#currentUser').text(currentUser);
            $('#userAvatar').text(currentUser.charAt(0).toUpperCase());
            $('#authContainer').hide();
            $('#chatContainer').css('display', 'flex').show();
            
            startPolling();
            loadInitialMessages();
        }
        
        function logout() {
            if (pollInterval) {
                clearInterval(pollInterval);
            }
            
            $.post('', {
                action: 'logout'
            }, function() {
                currentUser = null;
                lastMessageId = 0;
                messageQueue = [];
                pendingMessages.clear();
                location.reload();
            });
        }
        
        function loadInitialMessages() {
            $.post('', {
                action: 'get_messages',
                last_id: 0
            }, function(response) {
                const messages = JSON.parse(response);
                $('#messagesContainer').empty();
                
                if (messages.length === 0) {
                    $('#messagesContainer').append('<div class="no-messages">No messages yet. Be the first to say hello!</div>');
                } else {
                    messages.forEach(function(msg) {
                        lastMessageId = Math.max(lastMessageId, msg.id);
                        displayMessage(msg);
                    });
                }
            });
        }
        
        function sendMessage() {
            if (messageCooldown) {
                showCountdown();
                return;
            }
            
            const message = $('#messageInput').val().trim();
            if (!message) return;
            
            const tempId = 'temp_' + Date.now();
            const messageObj = {
                id: tempId,
                username: currentUser,
                message: message,
                created_at: new Date().toISOString(),
                status: 'sending'
            };
            
            $('#noMessages').remove();
            
            addMessageToCache(messageObj);
            displayMessage(messageObj);
            $('#messageInput').val('');
            
            pendingMessages.set(tempId, messageObj);
            
            $.post('', {
                action: 'send_message',
                message: message,
                temp_id: tempId
            }, function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    messageObj.id = data.message_id;
                    messageObj.status = 'sent';
                    pendingMessages.delete(tempId);
                    updateMessageInCache(tempId, messageObj);
                    updateMessageDisplay(tempId, 'sent');
                    
                    startMessageCooldown();
                } else {
                    messageObj.status = 'failed';
                    updateMessageInCache(tempId, messageObj);
                    updateMessageDisplay(tempId, 'failed');
                }
            }).fail(function() {
                messageObj.status = 'failed';
                updateMessageInCache(tempId, messageObj);
                updateMessageDisplay(tempId, 'failed');
            });
        }
        
        function startMessageCooldown() {
            messageCooldown = true;
            let timeLeft = 2;
            
            $('#sendBtn').prop('disabled', true);
            $('#messageInput').prop('disabled', true);
            
            cooldownTimer = setInterval(function() {
                $('#cooldownTimer').text(`(${timeLeft}s)`);
                timeLeft--;
                
                if (timeLeft < 0) {
                    clearInterval(cooldownTimer);
                    messageCooldown = false;
                    $('#sendBtn').prop('disabled', false);
                    $('#messageInput').prop('disabled', false);
                    $('#cooldownTimer').text('');
                }
            }, 1000);
        }
        
        function showCountdown() {
            let count = 2;
            $('#countdownNumber').text(count);
            $('#countdownOverlay').css('display', 'flex');
            
            const countdownInterval = setInterval(function() {
                count--;
                $('#countdownNumber').text(count);
                
                if (count <= 0) {
                    clearInterval(countdownInterval);
                    $('#countdownOverlay').hide();
                }
            }, 1000);
            
            setTimeout(function() {
                $('#countdownOverlay').hide();
            }, 2000);
        }
        
        function retryMessage(tempId) {
            const messageObj = getMessageFromCache(tempId);
            if (messageObj) {
                messageObj.status = 'sending';
                updateMessageDisplay(tempId, 'sending');
                
                $.post('', {
                    action: 'send_message',
                    message: messageObj.message,
                    temp_id: tempId
                }, function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        messageObj.id = data.message_id;
                        messageObj.status = 'sent';
                        pendingMessages.delete(tempId);
                        updateMessageInCache(tempId, messageObj);
                        updateMessageDisplay(tempId, 'sent');
                        startMessageCooldown();
                    } else {
                        messageObj.status = 'failed';
                        updateMessageDisplay(tempId, 'failed');
                    }
                }).fail(function() {
                    messageObj.status = 'failed';
                    updateMessageDisplay(tempId, 'failed');
                });
            }
        }
        
        function startPolling() {
            pollInterval = setInterval(function() {
                $.post('', {
                    action: 'get_messages',
                    last_id: lastMessageId
                }, function(response) {
                    const messages = JSON.parse(response);
                    messages.forEach(function(msg) {
                        if (msg.id > lastMessageId) {
                            lastMessageId = msg.id;
                            addMessageToCache(msg);
                            
                            if (!pendingMessages.has('temp_' + msg.id) && msg.username !== currentUser) {
                                $('#noMessages').remove();
                                displayMessage(msg);
                            }
                        }
                    });
                });
            }, 1000);
        }
        
        function displayMessage(msg) {
            const isOwn = msg.username === currentUser;
            const messageClass = isOwn ? 'message own' : 'message';
            const statusClass = msg.status === 'sending' ? ' sending' : (msg.status === 'failed' ? ' failed' : '');
            
            const retryButton = msg.status === 'failed' ? 
                `<button class="retry-btn" onclick="retryMessage('${msg.id}')">Retry</button>` : '';
            
            const messageHtml = `
                <div class="${messageClass}${statusClass}" data-id="${msg.id}">
                    <div class="message-header">
                        <span>${isOwn ? 'You' : msg.username}</span>
                        <span class="message-time">
                            ${formatTime(msg.created_at)}
                            ${retryButton}
                        </span>
                    </div>
                    <div class="message-content">${escapeHtml(msg.message)}</div>
                </div>
            `;
            
            $('#messagesContainer').append(messageHtml);
            $('#messagesContainer').scrollTop($('#messagesContainer')[0].scrollHeight);
        }
        
        function updateMessageDisplay(tempId, status) {
            const messageEl = $(`[data-id="${tempId}"]`);
            messageEl.removeClass('sending failed');
            
            if (status === 'sending') {
                messageEl.addClass('sending');
                messageEl.find('.retry-btn').remove();
            } else if (status === 'failed') {
                messageEl.addClass('failed');
                const retryBtn = `<button class="retry-btn" onclick="retryMessage('${tempId}')">Retry</button>`;
                messageEl.find('.message-time').append(retryBtn);
            } else if (status === 'sent') {
                messageEl.find('.retry-btn').remove();
            }
        }
        
        function addMessageToCache(message) {
            messageQueue.push(message);
            if (messageQueue.length > 100) {
                messageQueue.shift();
            }
        }
        
        function updateMessageInCache(tempId, updatedMessage) {
            const index = messageQueue.findIndex(msg => msg.id === tempId);
            if (index !== -1) {
                messageQueue[index] = updatedMessage;
            }
        }
        
        function getMessageFromCache(messageId) {
            return messageQueue.find(msg => msg.id === messageId);
        }
        
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) {
                return 'Just now';
            } else if (diffMins < 60) {
                return `${diffMins}m ago`;
            } else if (diffMins < 1440) {
                return `${Math.floor(diffMins / 60)}h ago`;
            } else {
                return date.toLocaleDateString();
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showError(message) {
            $('#errorMessage').text(message).show();
            $('#successMessage').hide();
        }
        
        function showSuccess(message) {
            $('#successMessage').text(message).show();
            $('#errorMessage').hide();
        }
        
        function clearMessages() {
            $('#errorMessage, #successMessage').hide();
        }
        
        $(window).on('beforeunload', function() {
            if (pollInterval) {
                clearInterval(pollInterval);
            }
            if (cooldownTimer) {
                clearInterval(cooldownTimer);
            }
        });
        
        $('#messageInput').on('focus', function() {
            setTimeout(() => {
                $('#messagesContainer').scrollTop($('#messagesContainer')[0].scrollHeight);
            }, 300);
        });
        
        $('#countdownOverlay').click(function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
    </script>
</body>
</html>