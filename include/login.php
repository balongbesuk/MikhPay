<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
include_once('./include/env_config.php');

$is_midtrans_configured = !empty($midtrans_server_key) && $midtrans_server_key !== 'YOUR_MIDTRANS_SERVER_KEY_HERE';
$midtrans_mode = $midtrans_is_production ? 'Production' : 'Sandbox';

$dbSessions = new \App\Models\RouterSession();
$sessionsCount = count($dbSessions->getAll());
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

/* Apply resets and custom font */
body.login-page {
    margin: 0;
    padding: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #090d16; /* Dark space background */
    min-height: 100vh;
    display: flex;
    align-items: stretch;
    justify-content: stretch;
    overflow: hidden;
}

.login-page-wrapper {
    display: flex;
    width: 100vw;
    height: 100vh;
    box-sizing: border-box;
}

/* LEFT PANEL */
.login-left-panel {
    flex: 1.2;
    background: #0f172a;
    background-image: 
        radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
        radial-gradient(at 100% 0%, hsla(225,39%,20%,0.4) 0, transparent 50%),
        radial-gradient(at 100% 100%, hsla(339,49%,20%,0.2) 0, transparent 50%),
        radial-gradient(at 0% 100%, hsla(225,39%,15%,0.3) 0, transparent 50%);
    background-size: cover;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 60px;
    box-sizing: border-box;
    border-right: 1px solid rgba(255, 255, 255, 0.05);
    overflow: hidden;
}

.login-left-content {
    max-width: 480px;
    z-index: 10;
    position: relative;
    animation: leftContentFadeIn 1s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes leftContentFadeIn {
    0% { opacity: 0; transform: translateX(-30px); }
    100% { opacity: 1; transform: translateX(0); }
}

.login-left-logo {
    font-size: 40px;
    color: #6366f1;
    margin-bottom: 24px;
    display: inline-flex;
    background: rgba(99, 102, 241, 0.1);
    width: 72px;
    height: 72px;
    border-radius: 20px;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.login-left-title {
    font-size: 42px;
    font-weight: 800;
    margin: 0 0 16px 0;
    background: linear-gradient(135deg, #ffffff 40%, #818cf8 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: -1px;
}

.login-left-desc {
    font-size: 16px;
    line-height: 1.6;
    color: #94a3b8;
    margin: 0 0 40px 0;
}

.login-left-stats {
    display: flex;
    gap: 20px;
}

.stat-card {
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 16px 20px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
}

.stat-icon {
    font-size: 20px;
    color: #38bdf8;
    background: rgba(56, 189, 248, 0.1);
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-text {
    display: flex;
    flex-direction: column;
    text-align: left;
}

.stat-text strong {
    font-size: 13px;
    color: #cbd5e1;
    font-weight: 600;
}

.stat-text span {
    font-size: 12px;
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.text-green { color: #4dbd74; }
.text-indigo { color: #818cf8; }

.text-green i { font-size: 8px; }

/* Left Panel Blobs */
.left-panel-bg-blobs {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    pointer-events: none;
}

.left-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.15;
    animation: leftFloat 20s infinite alternate ease-in-out;
}

.left-blob-1 {
    width: 300px;
    height: 300px;
    background: #6366f1;
    top: -50px;
    left: -50px;
}

.left-blob-2 {
    width: 400px;
    height: 400px;
    background: #db2777;
    bottom: -100px;
    right: -100px;
    animation-delay: -7s;
}

@keyframes leftFloat {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(60px, -40px) scale(1.1); }
}


/* RIGHT PANEL */
.login-right-panel {
    flex: 1;
    background: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    box-sizing: border-box;
    position: relative;
}

.login-form-container {
    width: 100%;
    max-width: 400px;
    animation: rightContentFadeIn 1s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 10;
}

@keyframes rightContentFadeIn {
    0% { opacity: 0; transform: translateX(30px); }
    100% { opacity: 1; transform: translateX(0); }
}

.login-logo-mobile {
    margin-bottom: 32px;
    text-align: left;
}

.login-logo-mobile img {
    width: 64px;
    height: 64px;
    background: rgba(255, 255, 255, 0.03);
    padding: 10px;
    border-radius: 18px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-sizing: border-box;
}

.login-form-title {
    font-size: 32px;
    font-weight: 800;
    color: #ffffff !important;
    margin: 0 0 8px 0;
    letter-spacing: -0.5px;
    text-align: left;
}

.login-form-subtitle {
    font-size: 15px;
    color: #94a3b8 !important;
    margin: 0 0 40px 0;
    text-align: left;
}

/* Inputs */
.login-form-group {
    position: relative;
    margin-bottom: 24px;
}

.login-form-group i:not(.password-toggle-icon) {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #475569;
    font-size: 18px;
    transition: color 0.3s ease;
}

body.login-page input.login-input {
    width: 100% !important;
    height: 54px !important;
    padding: 0 16px 0 52px !important;
    box-sizing: border-box !important;
    border-radius: 16px !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    background: rgba(30, 41, 59, 0.3) !important;
    color: #f8fafc !important;
    font-size: 15px !important;
    font-family: inherit !important;
    outline: none !important;
    transition: all 0.3s ease !important;
}

.login-input::placeholder {
    color: #475569;
}

.login-input:focus {
    border-color: #6366f1 !important;
    background: rgba(15, 23, 42, 0.6) !important;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15) !important;
}

.login-form-group:focus-within i {
    color: #6366f1;
}

.password-toggle-icon {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    left: auto !important;
    cursor: pointer;
    color: #475569;
    font-size: 16px;
    transition: color 0.3s ease;
}

.password-toggle-icon:hover {
    color: #94a3b8;
}

/* Button */
.login-submit-btn {
    width: 100% !important;
    height: 54px !important;
    border-radius: 16px !important;
    border: none !important;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
    color: #ffffff !important;
    font-size: 16px !important;
    font-weight: 700 !important;
    font-family: inherit !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.2) !important;
    margin-top: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.login-submit-btn:hover {
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%) !important;
    box-shadow: 0 12px 28px rgba(79, 70, 229, 0.35) !important;
    transform: translateY(-2px);
}

.login-submit-btn:active {
    transform: translateY(0);
}

/* Error Alert */
.bg-danger {
    margin-top: 24px;
    padding: 14px 18px !important;
    background: rgba(239, 68, 68, 0.12) !important;
    border: 1px solid rgba(239, 68, 68, 0.22) !important;
    border-radius: 16px !important;
    color: #fca5a5 !important;
    font-size: 13.5px !important;
    text-align: left !important;
    animation: shakeAlert 0.4s ease;
    width: 100% !important;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    gap: 12px;
}

@keyframes shakeAlert {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-6px); }
    75% { transform: translateX(6px); }
}

.bg-danger i {
    font-size: 16px;
    color: #f87171;
}

.login-right-footer {
    margin-top: 48px;
    font-size: 12px;
    color: #475569;
    text-align: left;
}

.login-right-footer a {
    color: #64748b;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s ease;
}

.login-right-footer a:hover {
    color: #6366f1;
}

/* Hide standard Mikhmon wrapper background */
.wrapper {
    background: transparent !important;
    box-shadow: none !important;
}

/* RESPONSIVE LAYOUT */
@media (max-width: 1024px) {
    .login-left-panel {
        padding: 40px;
    }
    .login-left-title {
        font-size: 36px;
    }
}

@media (max-width: 768px) {
    .login-left-panel {
        display: none; /* Hide visual panel on mobile/tablet */
    }
    .login-right-panel {
        flex: 1;
        background: #0f172a;
        background-image: 
            radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
            radial-gradient(at 100% 100%, hsla(225,39%,15%,0.3) 0, transparent 50%);
    }
    .login-form-container {
        max-width: 360px;
    }
}
</style>

<div class="login-page-wrapper">
    <!-- Left Panel (Visuals) -->
    <div class="login-left-panel">
        <div class="login-left-content">
            <div class="login-left-logo">
                <i class="fa fa-wifi"></i>
            </div>
            <h2 class="login-left-title">MikhPay v1.3</h2>
            <p class="login-left-desc">Billing Hotspot & Router Manager dengan integrasi pembayaran otomatis Midtrans. Manajemen terstruktur, cepat, dan aman.</p>
            
            <div class="login-left-stats">
                <div class="stat-card">
                    <span class="stat-icon"><i class="fa fa-server"></i></span>
                    <div class="stat-text">
                        <strong>Router Active</strong>
                        <span class="text-green"><i class="fa fa-circle"></i> <?= $sessionsCount ?> Session<?= $sessionsCount > 1 ? 's' : '' ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon"><i class="fa fa-credit-card"></i></span>
                    <div class="stat-text">
                        <strong>Payment Gateway</strong>
                        <?php if ($is_midtrans_configured): ?>
                            <span class="text-indigo">Midtrans <?= $midtrans_mode ?></span>
                        <?php else: ?>
                            <span class="text-muted" style="color: #64748b;">Not Configured</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="left-panel-bg-blobs">
            <div class="left-blob left-blob-1"></div>
            <div class="left-blob left-blob-2"></div>
        </div>
    </div>
    
    <!-- Right Panel (Form) -->
    <div class="login-right-panel">
        <div class="login-form-container">
            <div class="login-logo-mobile">
                <img src="img/favicon.png" alt="Logo">
            </div>
            
            <h1 class="login-form-title">Selamat Datang</h1>
            <p class="login-form-subtitle">Silakan masukkan akun administratif Anda</p>
            
            <form autocomplete="off" action="" method="post">
                <?= csrf_field() ?>
                <div class="login-form-group">
                    <i class="fa fa-user"></i>
                    <input class="login-input" type="text" name="user" id="_username" placeholder="Username" required autofocus>
                </div>
                
                <div class="login-form-group">
                    <i class="fa fa-lock"></i>
                    <input class="login-input" type="password" name="pass" id="_password" placeholder="Password" required>
                    <i class="fa fa-eye password-toggle-icon" id="togglePassword"></i>
                </div>
                
                <button type="submit" name="login" class="login-submit-btn">
                    <span>Masuk</span> <i class="fa fa-arrow-right"></i>
                </button>
            </form>

            <?php if (!empty($error)): ?>
                <?= $error; ?>
            <?php endif; ?>
            
            <div class="login-right-footer">
                <span>&copy; <?= date("Y") ?> <a href="https://github.com/balongbesuk/MikhPay" target="_blank">MikhPay</a>. All rights reserved.</span>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Add page class for body styling
    $('body').addClass('login-page');
    
    // Toggle password view
    $('#togglePassword').on('click', function() {
        const passwordField = $('#_password');
        const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
        passwordField.attr('type', type);
        
        $(this).toggleClass('fa-eye fa-eye-slash');
    });
});
</script>
