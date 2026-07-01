<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$user_id = (int) $_SESSION['user_id'];

// Ambil daftar user lain
$users_stmt = $conn->prepare("SELECT id, nama, profile_picture FROM users WHERE id != ?");
$users_stmt->bind_param('i', $user_id);
$users_stmt->execute();
$users = $users_stmt->get_result();

// Cek apakah ada tabel messages
$check_table = $conn->query("SHOW TABLES LIKE 'messages'");
if ($check_table->num_rows == 0) {
    // Buat tabel messages jika belum ada
    $create_table = "CREATE TABLE messages (
        id INT(11) NOT NULL AUTO_INCREMENT,
        sender_id INT(11) NOT NULL,
        receiver_id INT(11) NOT NULL,
        message TEXT NOT NULL,
        is_notification TINYINT(1) DEFAULT 0,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY sender_id (sender_id),
        KEY receiver_id (receiver_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($create_table);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --bg-color: #f8fafc;
            --sidebar-bg: #ffffff;
            --chat-bg: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --border-color: #e2e8f0;
            --hover-bg: #f7fafc;
            --active-bg: #edf2f7;
            --sent-bg: #667eea;
            --sent-color: #ffffff;
            --received-bg: #f1f5f9;
            --received-color: #2d3748;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-color);
            color: var(--text-primary);
            height: 100vh;
            overflow: hidden;
        }
        
        .chat-dashboard {
            display: flex;
            height: 100vh;
            background: var(--bg-color);
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 380px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow);
        }
        
        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .sidebar-header h4 {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .sidebar-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .search-container {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--hover-bg);
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        .contacts-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            padding: 16px 24px;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .contact-item:hover {
            background: var(--hover-bg);
        }
        
        .contact-item.active {
            background: var(--active-bg);
            border-left: 4px solid var(--primary-color);
        }
        
        .contact-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
            margin-right: 16px;
            flex-shrink: 0;
        }
        
        .contact-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .contact-info {
            flex: 1;
            min-width: 0;
        }
        
        .contact-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            font-size: 15px;
        }
        
        .contact-status {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .contact-meta {
            text-align: right;
            margin-left: 12px;
        }
        
        .contact-time {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .unread-badge {
            background: var(--primary-color);
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
        }
        
        /* Main Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--chat-bg);
        }
        
        .chat-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .chat-header-left {
            display: flex;
            align-items: center;
        }
        
        .chat-header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 12px;
        }
        
        .chat-header-info h5 {
            font-weight: 600;
            margin-bottom: 2px;
            color: var(--text-primary);
        }
        
        .chat-header-info p {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .chat-header-actions {
            display: flex;
            gap: 8px;
        }
        
        .chat-header-actions button {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            background: var(--hover-bg);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .chat-header-actions button:hover {
            background: var(--active-bg);
            color: var(--text-primary);
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            background: var(--bg-color);
        }
        
        .message {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-end;
        }
        
        .message.sent {
            justify-content: flex-end;
        }
        
        .message.received {
            justify-content: flex-start;
        }
        
        .message-content {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }
        
        .message.sent .message-content {
            background: var(--sent-bg);
            color: var(--sent-color);
            border-bottom-right-radius: 4px;
        }
        
        .message.received .message-content {
            background: var(--received-bg);
            color: var(--received-color);
            border-bottom-left-radius: 4px;
        }
        
        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 4px;
            text-align: right;
        }
        
        .message.received .message-time {
            text-align: left;
        }
        
        .chat-input-container {
            padding: 20px 24px;
            background: white;
            border-top: 1px solid var(--border-color);
            position: relative;
        }
        
        .chat-input-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--hover-bg);
            border-radius: 24px;
            padding: 8px 16px;
            border: 1px solid var(--border-color);
        }
        
        .chat-input-wrapper:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .chat-input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 8px 0;
            font-size: 14px;
            color: var(--text-primary);
        }
        
        .chat-input:focus {
            outline: none;
        }
        
        .chat-input::placeholder {
            color: var(--text-secondary);
        }
        
        .chat-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 50%;
            background: transparent;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: var(--active-bg);
            color: var(--text-primary);
        }
        
        .send-btn {
            background: var(--primary-color) !important;
            color: white !important;
        }
        
        .send-btn:hover {
            background: var(--secondary-color) !important;
            transform: scale(1.05);
        }
        
        /* Emoji Picker Styles */
        .emoji-picker {
            position: absolute;
            bottom: 100%;
            left: 24px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow);
            width: 320px;
            max-height: 300px;
            overflow: hidden;
            z-index: 1000;
            display: none;
        }
        
        .emoji-picker.show {
            display: block;
        }
        
        .emoji-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .emoji-header h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .emoji-search {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 8px;
        }
        
        .emoji-search:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .emoji-categories {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            background: var(--hover-bg);
        }
        
        .emoji-category {
            flex: 1;
            padding: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
        }
        
        .emoji-category:hover {
            background: var(--active-bg);
        }
        
        .emoji-category.active {
            background: var(--primary-color);
            color: white;
        }
        
        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 4px;
            padding: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .emoji-item {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 20px;
        }
        
        .emoji-item:hover {
            background: var(--hover-bg);
            transform: scale(1.1);
        }
        
        .emoji-section {
            display: none;
        }
        
        .emoji-section.active {
            display: block;
        }
        
        /* Empty State */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
            text-align: center;
            padding: 40px;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .empty-state p {
            font-size: 14px;
            max-width: 300px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: absolute;
                z-index: 10;
                height: 100%;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .chat-area {
                width: 100%;
            }
            
            .mobile-toggle {
                display: block !important;
            }
            
            .emoji-picker {
                width: 280px;
                left: 12px;
            }
        }
        
        .mobile-toggle {
            display: none;
        }
        
        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message {
            animation: fadeIn 0.3s ease;
        }
        
        /* Back button */
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 100;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            box-shadow: var(--shadow);
        }
        
        .back-btn:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-btn">
        <i class='bx bx-arrow-back'></i> Kembali
    </a>

    <div class="chat-dashboard">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h4>Pesan</h4>
                <p>Pilih kontak untuk memulai percakapan</p>
            </div>
            
            <div class="search-container">
                <div class="search-box">
                    <i class='bx bx-search'></i>
                    <input type="text" placeholder="Cari kontak..." id="searchContact">
                </div>
            </div>
            
            <div class="contacts-list" id="userList">
                <?php while($u = $users->fetch_assoc()): ?>
                    <div class="contact-item" data-id="<?= $u['id'] ?>">
                        <div class="contact-avatar">
                            <?php if (!empty($u['profile_picture'])): ?>
                                <img src="<?= htmlspecialchars($u['profile_picture']) ?>" alt="<?= htmlspecialchars($u['nama']) ?>">
                            <?php else: ?>
                                <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="contact-info">
                            <div class="contact-name"><?= htmlspecialchars($u['nama']) ?></div>
                            <div class="contact-status">Klik untuk memulai chat</div>
                        </div>
                        <div class="contact-meta">
                            <div class="contact-time"></div>
                            <div class="unread-badge" style="display:none;">0</div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- Main Chat Area -->
        <div class="chat-area">
            <!-- Chat Header -->
            <div class="chat-header" id="chatHeader" style="display:none;">
                <div class="chat-header-left">
                    <div class="chat-header-avatar" id="current-chat-avatar">
                        U
                    </div>
                    <div class="chat-header-info">
                        <h5 id="current-chat-name">Nama User</h5>
                        <p>Online</p>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <button class="mobile-toggle" onclick="toggleSidebar()">
                        <i class='bx bx-menu'></i>
                    </button>
                    <button type="button" id="callBtn">
                        <i class='bx bx-phone'></i>
                    </button>
                    <button type="button" id="videoCallBtn">
                        <i class='bx bx-video'></i>
                    </button>
                    <button type="button" id="chatInfoBtn">
                        <i class='bx bx-info-circle'></i>
                    </button>
                </div>
            </div>
            
            <!-- Chat Messages -->
            <div class="chat-messages" id="chat-box"></div>
            
            <!-- Chat Input -->
            <div class="chat-input-container" id="chatInputContainer" style="display:none;">
                <form id="chatForm" class="d-flex align-items-center">
                    <button type="button" class="action-btn" id="uploadImageBtn">
                        <i class='bx bx-image'></i>
                    </button>
                    <button type="button" class="action-btn" id="voiceMessageBtn">
                        <i class='bx bx-microphone'></i>
                    </button>
                    <input type="text" class="chat-input form-control" id="chatInput" placeholder="Ketik pesan..." autocomplete="off">
                    <button type="button" class="action-btn" id="emojiBtn">
                        <i class='bx bx-smile'></i>
                    </button>
                    <button type="submit" class="action-btn send-btn">
                        <i class='bx bx-send'></i>
                    </button>
                </form>
                <!-- Emoji Picker -->
                <div class="emoji-picker" id="emojiPicker">
                    <div class="emoji-header">
                        <h6>Emoji</h6>
                        <button type="button" class="action-btn" onclick="toggleEmojiPicker()">
                            <i class='bx bx-x'></i>
                        </button>
                    </div>
                    <div style="padding: 12px;">
                        <input type="text" class="emoji-search" placeholder="Cari emoji..." id="emojiSearch">
                        <div class="emoji-categories">
                            <div class="emoji-category active" data-category="smileys">😊</div>
                            <div class="emoji-category" data-category="animals">🐶</div>
                            <div class="emoji-category" data-category="food">🍕</div>
                            <div class="emoji-category" data-category="activities">⚽</div>
                            <div class="emoji-category" data-category="travel">🚗</div>
                            <div class="emoji-category" data-category="objects">💡</div>
                            <div class="emoji-category" data-category="symbols">❤️</div>
                            <div class="emoji-category" data-category="flags">🏁</div>
                        </div>
                        
                        <!-- Smileys -->
                        <div class="emoji-section active" id="smileys">
                            <div class="emoji-grid">
                                <div class="emoji-item">😀</div>
                                <div class="emoji-item">😃</div>
                                <div class="emoji-item">😄</div>
                                <div class="emoji-item">😁</div>
                                <div class="emoji-item">😅</div>
                                <div class="emoji-item">😂</div>
                                <div class="emoji-item">🤣</div>
                                <div class="emoji-item">😊</div>
                                <div class="emoji-item">😇</div>
                                <div class="emoji-item">🙂</div>
                                <div class="emoji-item">🙃</div>
                                <div class="emoji-item">😉</div>
                                <div class="emoji-item">😌</div>
                                <div class="emoji-item">😍</div>
                                <div class="emoji-item">🥰</div>
                                <div class="emoji-item">😘</div>
                                <div class="emoji-item">😗</div>
                                <div class="emoji-item">😙</div>
                                <div class="emoji-item">😚</div>
                                <div class="emoji-item">😋</div>
                                <div class="emoji-item">😛</div>
                                <div class="emoji-item">😝</div>
                                <div class="emoji-item">😜</div>
                                <div class="emoji-item">🤪</div>
                                <div class="emoji-item">🤨</div>
                                <div class="emoji-item">🧐</div>
                                <div class="emoji-item">🤓</div>
                                <div class="emoji-item">😎</div>
                                <div class="emoji-item">🤩</div>
                                <div class="emoji-item">🥳</div>
                                <div class="emoji-item">😏</div>
                                <div class="emoji-item">😒</div>
                                <div class="emoji-item">😞</div>
                                <div class="emoji-item">😔</div>
                                <div class="emoji-item">😟</div>
                                <div class="emoji-item">😕</div>
                                <div class="emoji-item">🙁</div>
                                <div class="emoji-item">☹️</div>
                                <div class="emoji-item">😣</div>
                                <div class="emoji-item">😖</div>
                                <div class="emoji-item">😫</div>
                                <div class="emoji-item">😩</div>
                                <div class="emoji-item">🥺</div>
                                <div class="emoji-item">😢</div>
                                <div class="emoji-item">😭</div>
                                <div class="emoji-item">😤</div>
                                <div class="emoji-item">😠</div>
                                <div class="emoji-item">😡</div>
                                <div class="emoji-item">🤬</div>
                                <div class="emoji-item">🤯</div>
                                <div class="emoji-item">😳</div>
                                <div class="emoji-item">🥵</div>
                                <div class="emoji-item">🥶</div>
                                <div class="emoji-item">😱</div>
                                <div class="emoji-item">😨</div>
                                <div class="emoji-item">😰</div>
                                <div class="emoji-item">😥</div>
                                <div class="emoji-item">😓</div>
                                <div class="emoji-item">🤗</div>
                                <div class="emoji-item">🤔</div>
                                <div class="emoji-item">🤭</div>
                                <div class="emoji-item">🤫</div>
                                <div class="emoji-item">🤥</div>
                                <div class="emoji-item">😶</div>
                                <div class="emoji-item">😐</div>
                                <div class="emoji-item">😑</div>
                                <div class="emoji-item">😯</div>
                                <div class="emoji-item">😦</div>
                                <div class="emoji-item">😧</div>
                                <div class="emoji-item">😮</div>
                                <div class="emoji-item">😲</div>
                                <div class="emoji-item">🥱</div>
                                <div class="emoji-item">😴</div>
                                <div class="emoji-item">🤤</div>
                                <div class="emoji-item">😪</div>
                                <div class="emoji-item">😵</div>
                                <div class="emoji-item">🤐</div>
                                <div class="emoji-item">🥴</div>
                                <div class="emoji-item">🤢</div>
                                <div class="emoji-item">🤮</div>
                                <div class="emoji-item">🤧</div>
                                <div class="emoji-item">😷</div>
                                <div class="emoji-item">🤒</div>
                                <div class="emoji-item">🤕</div>
                                <div class="emoji-item">🤑</div>
                                <div class="emoji-item">🤠</div>
                                <div class="emoji-item">💩</div>
                                <div class="emoji-item">👻</div>
                                <div class="emoji-item">👽</div>
                                <div class="emoji-item">👾</div>
                                <div class="emoji-item">🤖</div>
                                <div class="emoji-item">😺</div>
                                <div class="emoji-item">😸</div>
                                <div class="emoji-item">😹</div>
                                <div class="emoji-item">😻</div>
                                <div class="emoji-item">😼</div>
                                <div class="emoji-item">😽</div>
                                <div class="emoji-item">🙀</div>
                                <div class="emoji-item">😿</div>
                                <div class="emoji-item">😾</div>
                            </div>
                        </div>
                        
                        <!-- Animals -->
                        <div class="emoji-section" id="animals">
                            <div class="emoji-grid">
                                <div class="emoji-item">🐶</div>
                                <div class="emoji-item">🐱</div>
                                <div class="emoji-item">🐭</div>
                                <div class="emoji-item">🐹</div>
                                <div class="emoji-item">🐰</div>
                                <div class="emoji-item">🦊</div>
                                <div class="emoji-item">🐻</div>
                                <div class="emoji-item">🐼</div>
                                <div class="emoji-item">🐨</div>
                                <div class="emoji-item">🐯</div>
                                <div class="emoji-item">🦁</div>
                                <div class="emoji-item">🐮</div>
                                <div class="emoji-item">🐷</div>
                                <div class="emoji-item">🐸</div>
                                <div class="emoji-item">🐵</div>
                                <div class="emoji-item">🐔</div>
                                <div class="emoji-item">🐧</div>
                                <div class="emoji-item">🐦</div>
                                <div class="emoji-item">🦆</div>
                                <div class="emoji-item">🦅</div>
                                <div class="emoji-item">🦉</div>
                                <div class="emoji-item">🦇</div>
                                <div class="emoji-item">🐺</div>
                                <div class="emoji-item">🐗</div>
                                <div class="emoji-item">🐴</div>
                                <div class="emoji-item">🦄</div>
                                <div class="emoji-item">🐝</div>
                                <div class="emoji-item">🐛</div>
                                <div class="emoji-item">🦋</div>
                                <div class="emoji-item">🐌</div>
                                <div class="emoji-item">🐞</div>
                                <div class="emoji-item">🐜</div>
                                <div class="emoji-item">🦗</div>
                                <div class="emoji-item">🕷️</div>
                                <div class="emoji-item">🕸️</div>
                                <div class="emoji-item">🦂</div>
                                <div class="emoji-item">🦟</div>
                                <div class="emoji-item">🦠</div>
                                <div class="emoji-item">🐢</div>
                                <div class="emoji-item">🐍</div>
                                <div class="emoji-item">🦎</div>
                                <div class="emoji-item">🦖</div>
                                <div class="emoji-item">🦕</div>
                                <div class="emoji-item">🐙</div>
                                <div class="emoji-item">🦑</div>
                                <div class="emoji-item">🦐</div>
                                <div class="emoji-item">🦞</div>
                                <div class="emoji-item">🦀</div>
                                <div class="emoji-item">🐡</div>
                                <div class="emoji-item">🐠</div>
                                <div class="emoji-item">🐟</div>
                                <div class="emoji-item">🐬</div>
                                <div class="emoji-item">🐳</div>
                                <div class="emoji-item">🐋</div>
                                <div class="emoji-item">🦈</div>
                                <div class="emoji-item">🐊</div>
                                <div class="emoji-item">🐅</div>
                                <div class="emoji-item">🐆</div>
                                <div class="emoji-item">🦓</div>
                                <div class="emoji-item">🦍</div>
                                <div class="emoji-item">🦧</div>
                                <div class="emoji-item">🐘</div>
                                <div class="emoji-item">🦛</div>
                                <div class="emoji-item">🦏</div>
                                <div class="emoji-item">🐪</div>
                                <div class="emoji-item">🐫</div>
                                <div class="emoji-item">🦙</div>
                                <div class="emoji-item">🦒</div>
                                <div class="emoji-item">🐃</div>
                                <div class="emoji-item">🐂</div>
                                <div class="emoji-item">🐄</div>
                                <div class="emoji-item">🐎</div>
                                <div class="emoji-item">🐖</div>
                                <div class="emoji-item">🐏</div>
                                <div class="emoji-item">🐑</div>
                                <div class="emoji-item">🐐</div>
                                <div class="emoji-item">🦌</div>
                                <div class="emoji-item">🐕</div>
                                <div class="emoji-item">🐩</div>
                                <div class="emoji-item">🦮</div>
                                <div class="emoji-item">🐕‍🦺</div>
                                <div class="emoji-item">🐈</div>
                                <div class="emoji-item">🐈‍⬛</div>
                                <div class="emoji-item">🐓</div>
                                <div class="emoji-item">🦃</div>
                                <div class="emoji-item">🦚</div>
                                <div class="emoji-item">🦜</div>
                                <div class="emoji-item">🦢</div>
                                <div class="emoji-item">🦩</div>
                                <div class="emoji-item">🕊️</div>
                                <div class="emoji-item">🐇</div>
                                <div class="emoji-item">🦝</div>
                                <div class="emoji-item">🦨</div>
                                <div class="emoji-item">🦡</div>
                                <div class="emoji-item">🦫</div>
                                <div class="emoji-item">🦦</div>
                                <div class="emoji-item">🦥</div>
                                <div class="emoji-item">🐁</div>
                                <div class="emoji-item">🐀</div>
                                <div class="emoji-item">🐿️</div>
                                <div class="emoji-item">🦔</div>
                            </div>
                        </div>
                        
                        <!-- Food -->
                        <div class="emoji-section" id="food">
                            <div class="emoji-grid">
                                <div class="emoji-item">🍎</div>
                                <div class="emoji-item">🍐</div>
                                <div class="emoji-item">🍊</div>
                                <div class="emoji-item">🍋</div>
                                <div class="emoji-item">🍌</div>
                                <div class="emoji-item">🍉</div>
                                <div class="emoji-item">🍇</div>
                                <div class="emoji-item">🍓</div>
                                <div class="emoji-item">🫐</div>
                                <div class="emoji-item">🍈</div>
                                <div class="emoji-item">🍒</div>
                                <div class="emoji-item">🍑</div>
                                <div class="emoji-item">🥭</div>
                                <div class="emoji-item">🍍</div>
                                <div class="emoji-item">🥥</div>
                                <div class="emoji-item">🥝</div>
                                <div class="emoji-item">🍅</div>
                                <div class="emoji-item">🍆</div>
                                <div class="emoji-item">🥑</div>
                                <div class="emoji-item">🥦</div>
                                <div class="emoji-item">🥬</div>
                                <div class="emoji-item">🥒</div>
                                <div class="emoji-item">🌶️</div>
                                <div class="emoji-item">🫑</div>
                                <div class="emoji-item">🌽</div>
                                <div class="emoji-item">🥕</div>
                                <div class="emoji-item">🫒</div>
                                <div class="emoji-item">🧄</div>
                                <div class="emoji-item">🧅</div>
                                <div class="emoji-item">🥔</div>
                                <div class="emoji-item">🍠</div>
                                <div class="emoji-item">🥐</div>
                                <div class="emoji-item">🥯</div>
                                <div class="emoji-item">🍞</div>
                                <div class="emoji-item">🥖</div>
                                <div class="emoji-item">🥨</div>
                                <div class="emoji-item">🧀</div>
                                <div class="emoji-item">🥚</div>
                                <div class="emoji-item">🍳</div>
                                <div class="emoji-item">🧈</div>
                                <div class="emoji-item">🥞</div>
                                <div class="emoji-item">🧇</div>
                                <div class="emoji-item">🥓</div>
                                <div class="emoji-item">🥩</div>
                                <div class="emoji-item">🍗</div>
                                <div class="emoji-item">🍖</div>
                                <div class="emoji-item">🦴</div>
                                <div class="emoji-item">🌭</div>
                                <div class="emoji-item">🍔</div>
                                <div class="emoji-item">🍟</div>
                                <div class="emoji-item">🍕</div>
                                <div class="emoji-item">🥪</div>
                                <div class="emoji-item">🥙</div>
                                <div class="emoji-item">🧆</div>
                                <div class="emoji-item">🌮</div>
                                <div class="emoji-item">🌯</div>
                                <div class="emoji-item">🫔</div>
                                <div class="emoji-item">🥗</div>
                                <div class="emoji-item">🥘</div>
                                <div class="emoji-item">🫕</div>
                                <div class="emoji-item">🥫</div>
                                <div class="emoji-item">🍝</div>
                                <div class="emoji-item">🍜</div>
                                <div class="emoji-item">🍲</div>
                                <div class="emoji-item">🍛</div>
                                <div class="emoji-item">🍣</div>
                                <div class="emoji-item">🍱</div>
                                <div class="emoji-item">🥟</div>
                                <div class="emoji-item">🦪</div>
                                <div class="emoji-item">🍤</div>
                                <div class="emoji-item">🍙</div>
                                <div class="emoji-item">🍚</div>
                                <div class="emoji-item">🍘</div>
                                <div class="emoji-item">🍥</div>
                                <div class="emoji-item">🥠</div>
                                <div class="emoji-item">🥮</div>
                                <div class="emoji-item">🍢</div>
                                <div class="emoji-item">🍡</div>
                                <div class="emoji-item">🍧</div>
                                <div class="emoji-item">🍨</div>
                                <div class="emoji-item">🍦</div>
                                <div class="emoji-item">🥧</div>
                                <div class="emoji-item">🧁</div>
                                <div class="emoji-item">🍰</div>
                                <div class="emoji-item">🎂</div>
                                <div class="emoji-item">🍮</div>
                                <div class="emoji-item">🍭</div>
                                <div class="emoji-item">🍬</div>
                                <div class="emoji-item">🍫</div>
                                <div class="emoji-item">🍿</div>
                                <div class="emoji-item">🍪</div>
                                <div class="emoji-item">🌰</div>
                                <div class="emoji-item">🥜</div>
                                <div class="emoji-item">🍯</div>
                                <div class="emoji-item">🥛</div>
                                <div class="emoji-item">🍼</div>
                                <div class="emoji-item">☕</div>
                                <div class="emoji-item">🫖</div>
                                <div class="emoji-item">🍵</div>
                                <div class="emoji-item">🧃</div>
                                <div class="emoji-item">🥤</div>
                                <div class="emoji-item">🧋</div>
                                <div class="emoji-item">🍶</div>
                                <div class="emoji-item">🍺</div>
                                <div class="emoji-item">🍷</div>
                                <div class="emoji-item">🍸</div>
                                <div class="emoji-item">🍹</div>
                                <div class="emoji-item">🍾</div>
                                <div class="emoji-item">🥂</div>
                                <div class="emoji-item">🥃</div>
                                <div class="emoji-item">🍻</div>
                            </div>
                        </div>
                        
                        <!-- Activities -->
                        <div class="emoji-section" id="activities">
                            <div class="emoji-grid">
                                <div class="emoji-item">⚽</div>
                                <div class="emoji-item">🏀</div>
                                <div class="emoji-item">🏈</div>
                                <div class="emoji-item">⚾</div>
                                <div class="emoji-item">🥎</div>
                                <div class="emoji-item">🎾</div>
                                <div class="emoji-item">🏐</div>
                                <div class="emoji-item">🏉</div>
                                <div class="emoji-item">🥏</div>
                                <div class="emoji-item">🎱</div>
                                <div class="emoji-item">🪀</div>
                                <div class="emoji-item">🏓</div>
                                <div class="emoji-item">🏸</div>
                                <div class="emoji-item">🏒</div>
                                <div class="emoji-item">🏑</div>
                                <div class="emoji-item">🥍</div>
                                <div class="emoji-item">🏏</div>
                                <div class="emoji-item">🥅</div>
                                <div class="emoji-item">⛳</div>
                                <div class="emoji-item">🪁</div>
                                <div class="emoji-item">🏹</div>
                                <div class="emoji-item">🎣</div>
                                <div class="emoji-item">🤿</div>
                                <div class="emoji-item">🥊</div>
                                <div class="emoji-item">🥋</div>
                                <div class="emoji-item">🎽</div>
                                <div class="emoji-item">🛹</div>
                                <div class="emoji-item">🛷</div>
                                <div class="emoji-item">⛸️</div>
                                <div class="emoji-item">🥌</div>
                                <div class="emoji-item">🎿</div>
                                <div class="emoji-item">⛷️</div>
                                <div class="emoji-item">🏂</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden file inputs -->
    <input type="file" id="imageUpload" accept="image/*" style="display:none;">
    
    <!-- Audio for notifications -->
    <audio id="notification-sound" preload="auto">
        <source src="../asset/pesan.mp3" type="audio/mpeg">
    </audio>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let receiverId = null;
        let lastMessageCount = 0;
        let mediaRecorder = null;
        let audioChunks = [];

        function loadMessages() {
            if (!receiverId) return;
            $.get('fetch_messages.php', {receiver_id: receiverId}, function(data) {
                $('#chat-box').html('');
                data.forEach(function(msg) {
                    let messageClass = msg.sender_id == <?= $user_id ?> ? 'sent' : 'received';
                    let time = new Date(msg.created_at).toLocaleTimeString('id-ID', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    $('#chat-box').append(`
                        <div class="message ${messageClass}">
                            <div class="message-content">
                                ${msg.message}
                                <div class="message-time">${time}</div>
                            </div>
                        </div>
                    `);
                });
                $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
                checkNotifications();
            }, 'json');
        }

        $('.contact-item').click(function(){
            $('.contact-item').removeClass('active');
            $(this).addClass('active');
            
            receiverId = $(this).data('id');
            $('#chatHeader').show();
            $('#chatInputContainer').show();
            $('#emptyState').hide();
            
            const userName = $(this).find('.contact-name').text();
            const userInitial = userName.charAt(0).toUpperCase();
            $('#current-chat-name').text(userName);
            $('#current-chat-avatar').text(userInitial);
            $(this).find('.unread-badge').hide();
            
            loadMessages();
        });

        function checkNotifications() {
            $.get('check_notifications.php', function(data) {
                if (data.unread > 0) {
                    data.notifications.forEach(function(notification) {
                        let userId = notification.sender_id;
                        let unread = notification.unread;
                        let $badge = $('.contact-item[data-id="'+userId+'"] .unread-badge');
                        if (unread > 0) {
                            $badge.text(unread).show();
                        } else {
                            $badge.hide();
                        }
                    });
                    if (data.unread > lastMessageCount) {
                        $('#notification-sound')[0].play();
                    }
                    lastMessageCount = data.unread;
                } else {
                    $('.unread-badge').hide();
                    lastMessageCount = 0;
                }
            }, 'json');
        }

        function toggleSidebar() {
            $('#sidebar').toggleClass('show');
        }

        function toggleEmojiPicker() {
            $('#emojiPicker').toggleClass('show');
        }

        function getSupportedAudioMimeType() {
            const candidates = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/ogg',
                'audio/mp4'
            ];

            if (typeof MediaRecorder === 'undefined' || typeof MediaRecorder.isTypeSupported !== 'function') {
                return '';
            }

            for (const type of candidates) {
                if (MediaRecorder.isTypeSupported(type)) {
                    return type;
                }
            }
            return '';
        }

        function getExtensionFromMimeType(mimeType) {
            const normalized = (mimeType || '').toLowerCase();
            if (normalized.includes('audio/webm')) return 'webm';
            if (normalized.includes('audio/ogg')) return 'ogg';
            if (normalized.includes('audio/mp4')) return 'mp4';
            if (normalized.includes('audio/wav')) return 'wav';
            return 'webm';
        }

        $(function(){
            checkNotifications();
            
            $('#callBtn').on('click', function() {
                if (!receiverId) {
                    alert('Pilih kontak terlebih dahulu');
                    return;
                }
                alert('Fitur telepon belum tersedia.');
            });

            $('#videoCallBtn').on('click', function() {
                if (!receiverId) {
                    alert('Pilih kontak terlebih dahulu');
                    return;
                }
                alert('Fitur video call belum tersedia.');
            });

            $('#chatInfoBtn').on('click', function() {
                if (!receiverId) {
                    alert('Pilih kontak terlebih dahulu');
                    return;
                }
                alert('Info chat belum tersedia.');
            });

            $('#chatForm').submit(function(e){
                e.preventDefault();
                let msg = $('#chatInput').val();
                if (!msg.trim() || !receiverId) return;
                
                // Optimistic UI update
                let time = new Date().toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                $('#chat-box').append(`
                    <div class="message sent">
                        <div class="message-content">
                            ${msg}
                            <div class="message-time">${time}</div>
                            </div>
                        </div>
                `);
                $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
                
                $.post('send_message.php', {receiver_id: receiverId, message: msg}, function(){
                    $('#chatInput').val('');
                    loadMessages();
                });
            });
            
            $('#uploadImageBtn').click(function() {
                $('#imageUpload').click();
            });
            
            $('#imageUpload').change(function() {
                if (!receiverId) {
                    alert('Pilih kontak terlebih dahulu');
                    return;
                }
                
                let formData = new FormData();
                formData.append('image', this.files[0]);
                formData.append('receiver_id', receiverId);
                
                $('#chat-box').append(`
                    <div class="message sent">
                        <div class="message-content">
                            Mengirim gambar...
                            <div class="message-time">Sekarang</div>
                        </div>
                    </div>
                `);
                $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
                
                $.ajax({
                    url: 'send_image.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function() {
                        loadMessages();
                    }
                });
            });
            
            $('#voiceMessageBtn').click(function() {
                if (!receiverId) {
                    alert('Pilih kontak terlebih dahulu');
                    return;
                }
                
                if (mediaRecorder && mediaRecorder.state === "recording") {
                    mediaRecorder.stop();
                    $(this).html('<i class="bx bx-microphone"></i>');
                    $(this).removeClass('btn-danger').addClass('action-btn');
                } else {
                    navigator.mediaDevices.getUserMedia({ audio: true })
                        .then(stream => {
                            $(this).html('<i class="bx bx-stop-circle"></i>');
                            $(this).removeClass('action-btn').addClass('btn-danger');
                            
                            const mimeType = getSupportedAudioMimeType();
                            mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
                            audioChunks = [];
                            
                            mediaRecorder.addEventListener("dataavailable", event => {
                                if (event.data && event.data.size > 0) {
                                    audioChunks.push(event.data);
                                }
                            });
                            
                            mediaRecorder.addEventListener("stop", () => {
                                const recordedType = mediaRecorder && mediaRecorder.mimeType ? mediaRecorder.mimeType : '';
                                const audioBlob = new Blob(audioChunks, { type: recordedType || undefined });
                                const ext = getExtensionFromMimeType(audioBlob.type || recordedType);
                                const formData = new FormData();
                                formData.append('audio', audioBlob, 'recording.' + ext);
                                formData.append('receiver_id', receiverId);
                                
                                $('#chat-box').append(`
                                    <div class="message sent">
                                        <div class="message-content">
                                            Mengirim pesan suara...
                                            <div class="message-time">Sekarang</div>
                                        </div>
                                    </div>
                                `);
                                $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
                                
                                $.ajax({
                                    url: 'send_audio.php',
                                    type: 'POST',
                                    data: formData,
                                    contentType: false,
                                    processData: false,
                                    success: function() {
                                        loadMessages();
                                    },
                                    error: function() {
                                        alert('Gagal mengirim pesan suara. Fitur ini mungkin belum tersedia.');
                                        loadMessages();
                                    }
                                });
                                
                                stream.getTracks().forEach(track => track.stop());
                            });
                            
                            mediaRecorder.start();
                            
                            setTimeout(() => {
                                if (mediaRecorder && mediaRecorder.state === "recording") {
                                    mediaRecorder.stop();
                                    $('#voiceMessageBtn').html('<i class="bx bx-microphone"></i>');
                                    $('#voiceMessageBtn').removeClass('btn-danger').addClass('action-btn');
                                }
                            }, 60000);
                        })
                        .catch(error => {
                            console.error('Error accessing microphone:', error);
                            alert('Tidak dapat mengakses mikrofon. Pastikan Anda memberikan izin.');
                        });
                }
            });
            
            // Search functionality
            $('#searchContact').on('input', function() {
                let searchTerm = $(this).val().toLowerCase();
                $('.contact-item').each(function() {
                    let contactName = $(this).find('.contact-name').text().toLowerCase();
                    if (contactName.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            
            // Poll for new messages
            setInterval(function() {
                if (receiverId) {
                    loadMessages();
                }
                checkNotifications();
            }, 3000);

            // EMOJI PICKER FUNCTIONALITY
            $(document).on('click', '#emojiBtn', function(e) {
                e.stopPropagation();
                $('#emojiPicker').toggleClass('show');
            });
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#emojiPicker, #emojiBtn').length) {
                    $('#emojiPicker').removeClass('show');
                }
            });
            $(document).on('click', '.emoji-item', function() {
                const emoji = $(this).text();
                const $input = $('#chatInput');
                const start = $input[0].selectionStart;
                const end = $input[0].selectionEnd;
                const value = $input.val();
                $input.val(value.substring(0, start) + emoji + value.substring(end));
                $input[0].selectionStart = $input[0].selectionEnd = start + emoji.length;
                $input.focus();
            });
            // Kategori emoji
            $(document).on('click', '.emoji-category', function() {
                $('.emoji-category').removeClass('active');
                $(this).addClass('active');
                const cat = $(this).data('category');
                $('.emoji-section').removeClass('active');
                $('#' + cat).addClass('active');
            });
            // Search emoji
            $('#emojiSearch').on('input', function() {
                const val = $(this).val().toLowerCase();
                $('.emoji-section.active .emoji-item').each(function() {
                    if ($(this).text().toLowerCase().includes(val)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        });
    </script>
</body>
</html>
