// Helper to copy text to clipboard with fallback (HTTP compatible)
function copyTextToClipboard(text, successCb, errorCb) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(successCb, function() {
            fallbackCopyTextToClipboard(text, successCb, errorCb);
        });
    } else {
        fallbackCopyTextToClipboard(text, successCb, errorCb);
    }
}

function fallbackCopyTextToClipboard(text, successCb, errorCb) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        var successful = document.execCommand('copy');
        if (successful) {
            successCb();
        } else {
            errorCb();
        }
    } catch (err) {
        errorCb();
    }
    document.body.removeChild(textArea);
}

// Copy voucher code to clipboard (global fallback)
function copyVoucherCode() {
    var uEl = document.getElementById("voucherCode");
    if (!uEl) return;
    var u = uEl.innerText.trim();
    var pEl = document.getElementById("voucherPassword");
    var p = pEl ? pEl.innerText.trim() : u;
    var codeText = (u === p) ? u : "Username: " + u + "\nPassword: " + p;
    copyTextToClipboard(codeText, function() {
        var toast = document.getElementById("copyToast");
        if (toast) {
            toast.classList.add("show");
            setTimeout(function() {
                toast.classList.remove("show");
            }, 3000);
        } else {
            alert("Voucher berhasil disalin!");
        }
    }, function() {
        alert("Username: " + u + "\nPassword: " + p + "\n(Silakan salin secara manual)");
    });
}

// Copy pending order ID to clipboard
function copyPendingOrderId() {
    var el = document.getElementById("pendingOrderId");
    if (!el) return;
    var orderId = el.innerText.replace(/\s+/g, '').trim();
    copyTextToClipboard(orderId, function() {
        var toast = document.getElementById("copyToastPending");
        if (toast) {
            toast.classList.add("show");
            setTimeout(function() {
                toast.classList.remove("show");
            }, 3000);
        } else {
            alert("Order ID berhasil disalin: " + orderId);
        }
    }, function() {
        alert("Order ID: " + orderId + "\n(Silakan salin secara manual)");
    });
}

// Clear voucher history
function clearVoucherHistory() {
    if (confirm("Apakah Anda yakin ingin menghapus seluruh riwayat pembelian voucher di HP ini?")) {
        localStorage.removeItem('mikhtrans_purchase_history');
        var container = document.getElementById("voucherHistoryContainer");
        if (container) {
            container.style.display = 'none';
        }
    }
}

// Copy voucher from history list with button visual feedback
function copyHistoryCode(username, password, btn) {
    var codeText = (username === password) ? username : "Username: " + username + ", Password: " + password;
    copyTextToClipboard(codeText, function() {
        var originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-check" style="color: #219653;"></i> Tersalin';
        btn.style.borderColor = '#219653';
        btn.style.color = '#219653';
        setTimeout(function() {
            btn.innerHTML = originalHTML;
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 2000);
    }, function() {
        alert("Gagal menyalin. Kode: " + codeText);
    });
}

// Prevent double clicks and show loading state on checkout
function handleCheckoutSubmit(form) {
    var btn = form.querySelector('.btn-buy-voucher');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
        btn.style.opacity = '0.7';
        btn.style.cursor = 'not-allowed';
    }
    return true;
}

// Tab switching logic for compliance policies
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}

// Global Polling handler for transaction verification
var checkInterval = null;
function startTransactionPolling(orderId) {
    if (checkInterval) {
        clearInterval(checkInterval);
    }
    checkInterval = setInterval(function() {
        fetch("index.php?check_order=" + orderId)
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    clearInterval(checkInterval);
                    window.location.href = "index.php?show_voucher=1&order_id=" + orderId + "&session=" + encodeURIComponent(FrontpageConfig.session) + "#paket";
                } else if (data.status === "paid_pending_generate") {
                    clearInterval(checkInterval);
                    var paymentOverlay = document.getElementById("loadingPayment");
                    if (paymentOverlay) {
                        paymentOverlay.innerHTML = `
                            <div class="card" style="width: 100%; max-width: 440px; margin: 0 auto; text-align: center; background: white; border: 1px solid var(--border-color); padding: 24px; border-radius: 16px; box-shadow: var(--shadow-primary); color: var(--text-main, #3E3E3E);">
                                <i class="fa fa-exclamation-triangle" style="color: #F59E0B; font-size: 48px; margin-bottom: 16px; display: block;"></i>
                                <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; color: #1F2937; margin-bottom: 8px; font-size: 20px;">Pembayaran Berhasil!</h2>
                                <p style="font-size: 13px; color: #4B5563; line-height: 1.6; margin-bottom: 16px;">
                                    Terima kasih, pembayaran Anda telah kami terima. Namun saat ini <strong>koneksi ke router sedang mengalami gangguan</strong>.
                                </p>
                                <p style="font-size: 13px; color: #4B5563; line-height: 1.6; margin-bottom: 24px;">
                                    Admin sedang memproses voucher Anda secara manual. Silakan hubungi admin dan tunjukkan <strong>Order ID</strong> berikut:
                                </p>
                                <div style="background: #F3F4F6; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 15px; font-weight: bold; color: #111827; margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid var(--border-color);">
                                    <span id="pendingOrderId">${orderId}</span>
                                    <i class="fa-regular fa-copy" style="cursor: pointer; color: var(--primary);" onclick="var r = document.createRange(); r.selectNode(document.getElementById('pendingOrderId')); window.getSelection().removeAllRanges(); window.getSelection().addRange(r); document.execCommand('copy'); alert('Order ID disalin!');" title="Salin Order ID"></i>
                                </div>
                                <a href="index.php?session=${encodeURIComponent(FrontpageConfig.session)}" style="display: block; width: 100%; padding: 12px; background: var(--primary); color: white; border-radius: 8px; text-decoration: none; font-weight: bold; text-align: center;">Kembali ke Beranda</a>
                            </div>
                        `;
                        localStorage.removeItem('active_order_id');
                        localStorage.removeItem('active_snap_token');
                    } else {
                        window.location.href = "index.php?show_voucher=1&order_id=" + orderId + "&session=" + encodeURIComponent(FrontpageConfig.session) + "#paket";
                    }
                }
            })
            .catch(err => console.error("Error polling order status: ", err));
    }, 3000);
}

// WebSocket client connection setup with automated fallback
function initWebSocketConnection(orderId) {
    if (!FrontpageConfig.ws.enabled || typeof Pusher === 'undefined') {
        // Fallback directly to polling if WS is not configured or pusher script not loaded
        startTransactionPolling(orderId);
        return;
    }

    var pusherConfig = {};
    if (FrontpageConfig.ws.host) {
        // Soketi (Self-Hosted) config overrides
        var portVal = FrontpageConfig.ws.port ? parseInt(FrontpageConfig.ws.port, 10) : 6001;
        var isSecure = FrontpageConfig.ws.scheme === 'https' || FrontpageConfig.ws.scheme === 'wss';
        pusherConfig = {
            wsHost: FrontpageConfig.ws.host,
            wsPort: portVal,
            wssPort: portVal,
            forceTLS: isSecure,
            disableStats: true,
            enabledTransports: ['ws', 'wss']
        };
    } else {
        // Official Pusher Cloud config
        pusherConfig = {
            cluster: FrontpageConfig.ws.cluster
        };
    }

    try {
        var pusher = new Pusher(FrontpageConfig.ws.key, pusherConfig);
        var channel = pusher.subscribe('order-' + orderId);

        // Fallback timeout of 5 seconds to guarantee customer connection if WS connection lags
        var fallbackTimeout = setTimeout(function() {
            console.warn("WebSocket connection lag. Triggering fallback HTTP Polling...");
            startTransactionPolling(orderId);
        }, 5000);

        channel.bind('paid', function(data) {
            clearTimeout(fallbackTimeout);
            window.location.href = "index.php?show_voucher=1&order_id=" + orderId + "&session=" + encodeURIComponent(FrontpageConfig.session) + "#paket";
        });
    } catch (e) {
        console.error("Failed to connect to WebSocket. Falling back to HTTP Polling...", e);
        startTransactionPolling(orderId);
    }
}

// DOM content load handlers
document.addEventListener("DOMContentLoaded", function() {
    // 1. Bottom Navigation Active highlight on Scroll
    const sections = document.querySelectorAll("section[id], div[id], div[id='home']");
    const navItems = document.querySelectorAll(".bottom-nav .nav-item");

    window.addEventListener("scroll", () => {
        let current = "";
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (pageYOffset >= (sectionTop - 160)) {
                current = section.getAttribute("id");
            }
        });

        navItems.forEach(item => {
            item.classList.remove("active");
            if (item.getAttribute("href").slice(1) === current) {
                item.classList.add("active");
            }
        });
    });

    // 2. Resume flow Midtrans telah dihapus (QRIS dinamis tidak perlu resume payment)

    // 3. Render Purchase History from localStorage and Server-side verified MAC
    var historyListEl = document.getElementById("voucherHistoryList");
    var historyContainerEl = document.getElementById("voucherHistoryContainer");
    if (historyListEl && historyContainerEl) {
        var localHistory = [];
        try {
            localHistory = JSON.parse(localStorage.getItem('mikhtrans_purchase_history') || '[]');
        } catch (e) {
            console.error('Error reading purchase history:', e);
        }
        
        // Ambil data server-side yang disuntikkan PHP
        var serverHistory = window.serverPurchaseHistory || [];
        
        // Gabungkan keduanya dan hilangkan duplikat berdasarkan order_id
        var combinedHistory = [];
        var seenOrders = {};
        
        // Prioritaskan serverHistory terlebih dahulu karena datanya valid dari server
        serverHistory.forEach(function(item) {
            if (item && item.order_id && !seenOrders[item.order_id]) {
                combinedHistory.push(item);
                seenOrders[item.order_id] = true;
            }
        });
        
        // Tambahkan localHistory jika belum ada di serverHistory
        localHistory.forEach(function(item) {
            if (item && item.order_id && !seenOrders[item.order_id]) {
                combinedHistory.push(item);
                seenOrders[item.order_id] = true;
            }
        });
        
        // Simpan hasil gabungan kembali ke localStorage (batasi 10 item terbaru)
        try {
            localStorage.setItem('mikhtrans_purchase_history', JSON.stringify(combinedHistory.slice(0, 10)));
        } catch(e) {}
        
        var isSuccessScreen = document.querySelector('.receipt-card');
        if (combinedHistory.length > 0 && !isSuccessScreen) {
            var html = '';
            combinedHistory.forEach(function(item) {
                var loginButton = '';
                if (item.login_url) {
                    loginButton = `<a href="${item.login_url}" class="history-btn-connect" style="text-decoration: none;"><i class="fa fa-wifi"></i> Hubungkan</a>`;
                }
                html += `
                <div class="history-item">
                    <div class="history-item-info">
                        <div class="history-item-title">
                            <span class="history-item-profile">Paket ${item.profile}</span>
                            <span class="history-badge-validity">${item.validity}</span>
                        </div>
                        <div class="history-item-meta">
                            ID: <span class="history-monospace">${item.order_id}</span> · ${item.date}
                        </div>
                    </div>
                    <div class="history-item-actions">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 12px; font-weight: 700; color: var(--text-muted);">Username:</span>
                            <span class="history-item-code" style="margin: 0; padding: 8px 12px; font-size: 12px; line-height: 1; font-weight: 700; border-radius: 8px; min-width: 80px; letter-spacing: 0.5px; background: #ffffff; border: 1px solid var(--border-color); color: var(--text-main);">${item.username}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px; margin-right: 4px;">
                            <span style="font-size: 12px; font-weight: 700; color: var(--text-muted);">Password:</span>
                            <span class="history-item-code" style="margin: 0; padding: 8px 12px; font-size: 12px; line-height: 1; font-weight: 700; border-radius: 8px; min-width: 80px; letter-spacing: 0.5px; background: #ffffff; border: 1px solid var(--border-color); color: var(--text-main);">${item.password || item.username}</span>
                        </div>
                        <button type="button" class="history-btn-copy" onclick="copyHistoryCode('${item.username}', '${item.password || item.username}', this)">
                            <i class="fa-regular fa-copy"></i> Salin
                        </button>
                        ${loginButton}
                    </div>
                </div>
                `;
            });
            historyListEl.innerHTML = html;
            historyContainerEl.style.display = 'block';
        }
    }

    // 4. Real-time Router Connectivity Check (15s polling interval)
    initRouterStatusChecker();
});

// Real-time Router Connectivity Checker
function initRouterStatusChecker() {
    if (typeof FrontpageConfig === 'undefined' || !FrontpageConfig.session) return;
    
    var isChecking = false;
    
    function checkRouter() {
        if (isChecking) return;
        isChecking = true;
        
        fetch("index.php?check_router=1&session=" + encodeURIComponent(FrontpageConfig.session))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                isChecking = false;
                var buyButtons = document.querySelectorAll(".btn-buy-voucher");
                
                if (data.online) {
                    buyButtons.forEach(function(btn) {
                        if (btn.getAttribute("data-blocked-by-offline") === "true") {
                            btn.removeAttribute("disabled");
                            btn.removeAttribute("data-blocked-by-offline");
                            btn.innerHTML = '<i class="fa fa-shopping-cart"></i> Beli Sekarang';
                            btn.style.backgroundColor = "";
                            btn.style.cursor = "";
                        }
                    });
                } else {
                    buyButtons.forEach(function(btn) {
                        if (!btn.hasAttribute("disabled") || btn.getAttribute("data-blocked-by-offline") === "true") {
                            btn.setAttribute("disabled", "true");
                            btn.setAttribute("data-blocked-by-offline", "true");
                            btn.innerHTML = '<i class="fa fa-ban"></i> Offline';
                            btn.style.backgroundColor = "#94a3b8";
                            btn.style.cursor = "not-allowed";
                        }
                    });
                }
            })
            .catch(function(e) {
                isChecking = false;
                console.error("Error checking router connectivity:", e);
            });
    }
    
    // Check immediately on load
    checkRouter();
    
    // Check every 15 seconds
    setInterval(checkRouter, 15000);
}
