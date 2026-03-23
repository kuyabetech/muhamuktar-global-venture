<?php
// maintenance.php - Maintenance Mode Page

// Set maintenance mode header
header('HTTP/1.1 503 Service Unavailable');
header('Retry-After: 3600'); // Retry after 1 hour

// Get client IP
$client_ip = $_SERVER['REMOTE_ADDR'];

// Define allowed IPs (admin IPs that can bypass maintenance)
$allowed_ips = [
    '127.0.0.1',      // localhost
    '::1',            // localhost IPv6
    // Add your office/home IPs here
    // '123.456.789.000',
];

// Check if current IP is allowed
$is_allowed = in_array($client_ip, $allowed_ips);

// If allowed, show admin notice and continue
if ($is_allowed) {
    $show_admin_notice = true;
}

// Get estimated completion time from settings if available
$estimated_completion = '30 minutes';
$completion_timestamp = time() + 1800; // 30 minutes from now

// Try to get settings from database if available
try {
    if (file_exists(__DIR__ . '/includes/config.php')) {
        require_once __DIR__ . '/includes/config.php';
        
        // Check if we have a connection
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'maintenance_message'");
            $stmt->execute();
            $custom_message = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'maintenance_completion'");
            $stmt->execute();
            $completion_time = $stmt->fetchColumn();
            if ($completion_time) {
                $estimated_completion = $completion_time;
                $completion_timestamp = time() + (int)$completion_time * 60;
            }
            
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_facebook'");
            $stmt->execute();
            $social_facebook = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_twitter'");
            $stmt->execute();
            $social_twitter = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
            $stmt->execute();
            $contact_email = $stmt->fetchColumn() ?: 'support@muhamuktar.com';
            
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_phone'");
            $stmt->execute();
            $contact_phone = $stmt->fetchColumn() ?: '+234 123 456 7890';
            
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'site_name'");
            $stmt->execute();
            $site_name = $stmt->fetchColumn() ?: 'Muhamuktar Global Venture';
        }
    }
} catch (Exception $e) {
    // Silently fail, use defaults
    $site_name = 'Muhamuktar Global Venture';
    $contact_email = 'support@muhamuktar.com';
    $contact_phone = '+234 123 456 7890';
    $custom_message = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Maintenance | <?= htmlspecialchars($site_name ?? 'Muhamuktar Global Venture') ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 20px;
        }
        
        .maintenance-container {
            text-align: center;
            padding: 3rem;
            max-width: 800px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
        }
        
        .maintenance-icon {
            font-size: 6rem;
            margin-bottom: 2rem;
            animation: spin 10s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .maintenance-message {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            line-height: 1.6;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .custom-message {
            background: rgba(255, 255, 255, 0.15);
            padding: 1.5rem;
            border-radius: 15px;
            margin: 2rem 0;
            font-size: 1.1rem;
            border-left: 4px solid #ffd700;
            text-align: left;
        }
        
        .timer-container {
            background: rgba(0, 0, 0, 0.3);
            padding: 2rem;
            border-radius: 20px;
            margin: 2rem 0;
        }
        
        .timer-label {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }
        
        .timer {
            font-size: 3.5rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .timer-unit {
            font-size: 1rem;
            opacity: 0.7;
            margin-left: 0.25rem;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            margin: 2rem 0 1rem;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ffd700, #ffa500);
            border-radius: 10px;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .admin-notice {
            background: rgba(255, 215, 0, 0.15);
            border: 2px solid #ffd700;
            padding: 1.5rem;
            border-radius: 15px;
            margin: 2rem 0;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .admin-notice i {
            font-size: 2rem;
            color: #ffd700;
        }
        
        .admin-notice h3 {
            margin-bottom: 0.5rem;
            color: #ffd700;
        }
        
        .admin-notice p {
            opacity: 0.9;
            margin: 0;
        }
        
        .admin-bypass {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            display: inline-block;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .admin-bypass i {
            color: #10b981;
            margin-right: 0.5rem;
        }
        
        .contact-info {
            margin: 2rem 0;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
        }
        
        .contact-info h3 {
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .contact-links {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .contact-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50px;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .contact-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .social-link {
            color: white;
            font-size: 1.5rem;
            transition: all 0.3s;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .social-link:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .footer {
            margin-top: 2rem;
            font-size: 0.9rem;
            opacity: 0.7;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .maintenance-container {
                padding: 2rem;
            }
            
            h1 {
                font-size: 2.5rem;
            }
            
            .maintenance-message {
                font-size: 1.1rem;
            }
            
            .timer {
                font-size: 2.5rem;
            }
            
            .contact-links {
                flex-direction: column;
                align-items: center;
            }
            
            .contact-link {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            h1 {
                font-size: 2rem;
            }
            
            .maintenance-icon {
                font-size: 4rem;
            }
            
            .timer {
                font-size: 2rem;
            }
        }
        
        /* Loading Animation */
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <!-- Maintenance Icon -->
        <div class="maintenance-icon">
            <i class="fas fa-cogs"></i>
        </div>
        
        <!-- Title -->
        <h1><?= htmlspecialchars($site_name ?? 'Muhamuktar Global Venture') ?></h1>
        
        <!-- Main Message -->
        <div class="maintenance-message">
            <i class="fas fa-tools"></i>
            We're currently performing scheduled maintenance to improve your experience.
        </div>
        
        <!-- Custom Message from Database -->
        <?php if (!empty($custom_message)): ?>
            <div class="custom-message">
                <i class="fas fa-info-circle"></i>
                <?= nl2br(htmlspecialchars($custom_message)) ?>
            </div>
        <?php endif; ?>
        
        <!-- Timer Container -->
        <div class="timer-container">
            <div class="timer-label">Estimated Time Remaining</div>
            <div class="timer" id="timer">
                <span id="hours">00</span><span class="timer-unit">h</span>
                <span id="minutes">30</span><span class="timer-unit">m</span>
                <span id="seconds">00</span><span class="timer-unit">s</span>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            
            <div class="pulse">
                <i class="fas fa-spinner fa-pulse"></i> Working on it...
            </div>
        </div>
        
        <!-- Admin Notice (Only visible to allowed IPs) -->
        <?php if (isset($show_admin_notice) && $show_admin_notice): ?>
            <div class="admin-notice">
                <i class="fas fa-shield-alt"></i>
                <div>
                    <h3>Administrator Access</h3>
                    <p>You are seeing this notice because your IP (<?= htmlspecialchars($client_ip) ?>) is whitelisted. The site is in maintenance mode for regular users.</p>
                    <div class="admin-bypass">
                        <i class="fas fa-check-circle"></i> You have bypassed maintenance mode
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Contact Information -->
        <div class="contact-info">
            <h3><i class="fas fa-headset"></i> Need Immediate Assistance?</h3>
            <div class="contact-links">
                <?php if (!empty($contact_email)): ?>
                    <a href="mailto:<?= htmlspecialchars($contact_email) ?>" class="contact-link">
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($contact_email) ?>
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($contact_phone)): ?>
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contact_phone) ?>" class="contact-link">
                        <i class="fas fa-phone-alt"></i>
                        <?= htmlspecialchars($contact_phone) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Social Media Links -->
        <?php if (!empty($social_facebook) || !empty($social_twitter)): ?>
            <div class="social-links">
                <?php if (!empty($social_facebook)): ?>
                    <a href="<?= htmlspecialchars($social_facebook) ?>" target="_blank" class="social-link" title="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($social_twitter)): ?>
                    <a href="<?= htmlspecialchars($social_twitter) ?>" target="_blank" class="social-link" title="Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($social_instagram)): ?>
                    <a href="<?= htmlspecialchars($social_instagram) ?>" target="_blank" class="social-link" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name ?? 'Muhamuktar Global Venture') ?>. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">
                <small>We'll be back shortly. Thank you for your patience.</small>
            </p>
        </div>
    </div>
    
    <script>
        // Countdown Timer
        (function() {
            // Set the date we're counting down to
            const completionTime = <?= json_encode($completion_timestamp) ?> * 1000;
            const totalDuration = 30 * 60 * 1000; // 30 minutes in milliseconds
            
            function updateTimer() {
                const now = new Date().getTime();
                const distance = completionTime - now;
                
                // Time calculations
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                // Update timer display
                document.getElementById('hours').textContent = String(hours).padStart(2, '0');
                document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
                document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
                
                // Update progress bar
                const elapsed = totalDuration - distance;
                const progress = (elapsed / totalDuration) * 100;
                document.getElementById('progressFill').style.width = Math.min(100, Math.max(0, progress)) + '%';
                
                // If the countdown is finished
                if (distance < 0) {
                    clearInterval(timerInterval);
                    document.getElementById('timer').innerHTML = '<span style="color: #10b981;">Coming back online...</span>';
                    document.getElementById('progressFill').style.width = '100%';
                    
                    // Refresh page after 5 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 5000);
                }
            }
            
            // Update timer immediately
            updateTimer();
            
            // Update timer every second
            const timerInterval = setInterval(updateTimer, 1000);
        })();
        
        // Add particle effect (optional)
        function createParticle() {
            const particle = document.createElement('div');
            particle.style.position = 'fixed';
            particle.style.width = '2px';
            particle.style.height = '2px';
            particle.style.background = 'rgba(255, 255, 255, 0.5)';
            particle.style.borderRadius = '50%';
            particle.style.pointerEvents = 'none';
            particle.style.zIndex = '9999';
            
            const startX = Math.random() * window.innerWidth;
            const startY = Math.random() * window.innerHeight;
            
            particle.style.left = startX + 'px';
            particle.style.top = startY + 'px';
            
            document.body.appendChild(particle);
            
            const duration = Math.random() * 3 + 2;
            const endX = startX + (Math.random() - 0.5) * 200;
            const endY = startY - Math.random() * 200;
            
            particle.animate([
                { transform: 'translate(0, 0)', opacity: 1 },
                { transform: `translate(${endX - startX}px, ${endY - startY}px)`, opacity: 0 }
            ], {
                duration: duration * 1000,
                easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
            }).onfinish = function() {
                particle.remove();
            };
        }
        
        // Create particles occasionally (uncomment if you want particle effect)
        // setInterval(createParticle, 500);
        
        // Handle visibility change (if user switches tabs)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                // Refresh timer when user comes back
                location.reload();
            }
        });
        
        // Check server status periodically
        function checkServerStatus() {
            fetch(window.location.href, { method: 'HEAD' })
                .then(response => {
                    if (response.status === 200) {
                        location.reload();
                    }
                })
                .catch(() => {
                    // Server still in maintenance, ignore
                });
        }
        
        // Check every 30 seconds if server is back
        setInterval(checkServerStatus, 30000);
    </script>
</body>
</html>