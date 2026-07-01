<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="format-detection" content="telephone=no">
    <title><?= $page_title ?? 'MINVEN PRO' ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <meta name="minven-base" content="<?= htmlspecialchars(BASE_PATH) ?>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Boxicons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS (load before DataTables) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    
    <!-- Menu Access Control JS -->
    <script src="<?= htmlspecialchars(url_for('asset/js/menu-access-control.js')) ?>"></script>
    
    <!-- Elegant Animations CSS -->
    <link href="<?= htmlspecialchars(url_for('asset/css/elegant-animations.css')) ?>" rel="stylesheet">
    
    <!-- Elegant Effects JS -->
    <script src="<?= htmlspecialchars(url_for('asset/js/elegant-effects.js')) ?>"></script>
    
    <!-- Custom CSS for Tablet Optimization -->
    
    <style id="minven-page-loader-style">
        #minven-page-loader{
            position:fixed;inset:0;z-index:20000;display:flex;align-items:center;justify-content:center;
            background:#004AAD;
            opacity:1;visibility:visible;pointer-events:auto;
            transition:opacity .18s ease,visibility .18s ease
        }
        #minven-page-loader.minven-hidden{opacity:0;visibility:hidden;pointer-events:none}
        #minven-page-loader .minven-inner{position:relative;display:flex;align-items:center;justify-content:center}
        #minven-page-loader .minven-logo{display:block;width:min(70vw,260px);height:auto;object-fit:contain;filter:drop-shadow(0 14px 26px rgba(0,0,0,.35))}
    </style>

    <style>
        body {
            background: #f6f8fc;
            color: #111827;
            font-family: 'Nunito Sans', 'Open Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        @supports (-webkit-touch-callout: none) {
            input, select, textarea, .form-control, .form-select {
                font-size: 16px;
            }
        }
    </style>

    <script>
        (function minvenPageLoaderInit() {
            const LOADER_ID = 'minven-page-loader';
            const LOGO_SRC = '<?= addslashes(url_for('asset/LOGO1.png')) ?>';

            const ensureLoader = () => {
                let el = document.getElementById(LOADER_ID);
                if (el) return el;
                el = document.createElement('div');
                el.id = LOADER_ID;
                el.setAttribute('aria-hidden', 'true');
                el.innerHTML = `<div class="minven-inner"><img class="minven-logo" src="${LOGO_SRC}" alt=""></div>`;
                document.body.appendChild(el);
                return el;
            };

            const show = () => {
                if (!document.body) return;
                const el = ensureLoader();
                el.classList.remove('minven-hidden');
            };

            const hide = () => {
                const el = document.getElementById(LOADER_ID);
                if (!el) return;
                el.classList.add('minven-hidden');
            };

            window.MinvenPageLoader = window.MinvenPageLoader || { show, hide };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', show, { once: true });
            } else {
                show();
            }
            window.addEventListener('load', () => setTimeout(hide, 120));

            document.addEventListener(
                'click',
                (e) => {
                    if (e.defaultPrevented) return;
                    if (e.button !== 0) return;
                    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

                    const a = e.target && e.target.closest ? e.target.closest('a') : null;
                    if (!a) return;
                    if (a.hasAttribute('download')) return;
                    if ((a.getAttribute('target') || '').toLowerCase() === '_blank') return;
                    if (a.getAttribute('data-no-loader') !== null) return;
                    if (a.getAttribute('data-bs-toggle') !== null) return;

                    const href = (a.getAttribute('href') || '').trim();
                    if (!href || href === '#' || href.startsWith('#')) return;
                    if (href.toLowerCase().startsWith('javascript:')) return;

                    let url;
                    try {
                        url = new URL(href, window.location.href);
                    } catch {
                        return;
                    }
                    if (url.origin !== window.location.origin) return;

                    show();
                },
                true
            );

            document.addEventListener(
                'submit',
                (e) => {
                    if (e.defaultPrevented) return;
                    show();
                },
                true
            );

            window.addEventListener('beforeunload', show);
        })();
    </script>
</head>
<body>
    <div id="minven-page-loader" aria-hidden="true">
        <div class="minven-inner">
            <img class="minven-logo" src="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>" alt="">
        </div>
    </div> 
