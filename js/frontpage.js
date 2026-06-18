// Copy voucher code to clipboard
function copyVoucherCode() {
    var codeText = document.getElementById("voucherCode").innerText;
    navigator.clipboard.writeText(codeText).then(function() {
        alert("Kode voucher berhasil disalin!");
    }, function(err) {
        alert("Gagal menyalin kode. Silakan salin secara manual.");
    });
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

    // 2. Persistent pending transaction checker (Resume flow)
    var activeOrderId = localStorage.getItem('active_order_id');
    var activeSnapToken = localStorage.getItem('active_snap_token');
    
    if (activeOrderId && activeSnapToken && !window.location.search.includes('show_voucher') && !window.location.search.includes('order_id')) {
        var parts = activeOrderId.split('-');
        var timestamp = parseInt(parts[parts.length - 1], 10);
        var now = Math.floor(Date.now() / 1000);
        
        // Expire check (10 minutes)
        if (now - timestamp > 600) {
            localStorage.removeItem('active_order_id');
            localStorage.removeItem('active_snap_token');
            return;
        }
        
        // Verify payment in background
        fetch("index.php?check_order=" + activeOrderId)
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    window.location.href = "index.php?show_voucher=1&order_id=" + activeOrderId + "&session=" + encodeURIComponent(FrontpageConfig.session) + "#paket";
                } else {
                    showResumeBanner(activeOrderId, activeSnapToken);
                }
            })
            .catch(err => console.error("Error verifying background order: ", err));
    }
    
    function showResumeBanner(orderId, snapToken) {
        var banner = document.createElement('div');
        banner.id = 'resume-payment-banner';
        banner.style.cssText = `
            position: fixed;
            top: 16px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 16px 20px;
            border-radius: 16px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 16px;
            width: 90%;
            max-width: 500px;
            font-size: 13px;
            color: var(--text-main);
            animation: slideDown 0.3s ease;
        `;
        
        banner.innerHTML = `
            <div style="flex: 1;">
                <strong style="color: var(--primary); display: block; margin-bottom: 2px; text-align: left;">Pembayaran Tertunda</strong>
                <span style="color: var(--text-muted); display: block; text-align: left;">Ada transaksi yang belum diselesaikan (10 mnt limit).</span>
            </div>
            <div style="display: flex; gap: 8px;">
                <button id="btn-resume-pay" style="background: var(--primary); color: white; border: none; padding: 8px 12px; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap;">Bayar</button>
                <button id="btn-resume-dismiss" style="background: rgba(0,0,0,0.05); color: var(--text-muted); border: none; padding: 8px 12px; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap;">Batal</button>
            </div>
        `;
        
        document.body.appendChild(banner);
        
        if (!document.getElementById('slideDown-style')) {
            var style = document.createElement('style');
            style.id = 'slideDown-style';
            style.innerHTML = `
                @keyframes slideDown {
                    from { transform: translate(-50%, -40px); opacity: 0; }
                    to { transform: translate(-50%, 0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }
        
        // Handle checkout resume click
        document.getElementById('btn-resume-pay').addEventListener('click', function() {
            var overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(255,255,255,0.9);
                z-index: 2000;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
            `;
            overlay.innerHTML = `
                <div class="spinner"></div>
                <h2 style="margin-top: 20px; font-family: 'Plus Jakarta Sans', sans-serif;">Menghubungkan ke Midtrans...</h2>
            `;
            document.body.appendChild(overlay);
            
            snap.pay(snapToken, {
                onSuccess: function(result) {
                    initWebSocketConnection(orderId);
                },
                onPending: function(result) {
                    initWebSocketConnection(orderId);
                },
                onError: function(result) {
                    overlay.remove();
                    alert("Pembayaran dibatalkan.");
                },
                onClose: function() {
                    overlay.remove();
                }
            });
        });
        
        document.getElementById('btn-resume-dismiss').addEventListener('click', function() {
            localStorage.removeItem('active_order_id');
            localStorage.removeItem('active_snap_token');
            banner.remove();
        });
    }
});
