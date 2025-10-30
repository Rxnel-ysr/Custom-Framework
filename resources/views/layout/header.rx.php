p<?php

/**
 * DRY (Don't Repeat Yourself)
 * KISS (Keep It Simple, Stupid)
 * 
 */

// $user = $_SESSION['user'];
$cssFile = asset("css/main.css");
$jsFile = asset("js/main.js");
$exclude_navigation = $exclude_nav ?? false;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?= $cssFile ?>" rel="stylesheet">
    <title><?= $title_name ?? 'Native-PHP' ?></title>
</head>

<body>

    <script>
        console.log(<?= $jsFile
                        ? '"JavaScript file loaded at:\n' . addslashes($jsFile) . '"'
                        : '"JavaScript file not found"' ?>);
        console.log(<?= $cssFile
                        ? '"CSS file loaded at:\n' . addslashes($cssFile) . '"'
                        : '"CSS file not found"' ?>);
    </script>

    <?php if (!$exclude_navigation) { ?>
        <!-- navbar daur ulang -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
            <div class="container">
                <a class="navbar-brand" href="/">Native-PHP</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-double-down" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M1.646 6.646a.5.5 0 0 1 .708 0L8 12.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708" />
                        <path fill-rule="evenodd" d="M1.646 2.646a.5.5 0 0 1 .708 0L8 8.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708" />
                    </svg>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="mailto:myusronelyadi@gmail.com">Contact</a>
                        </li>
                    </ul>
                    <!-- User Info -->
                    <?php if (isset($user)): ?>
                        <div class="d-flex align-items-center ms-auto">
                            <div class="my-2" style="width: 40px; height: 40px; background-color: #f8f9fa; color: #333; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold;">
                                <?= strtoupper($user['name'][0]); ?>
                            </div>
                            <span style="color: #f8f9fa; margin-left: 10px;"><?= htmlspecialchars($user['name']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <style>
            .navbar-dark.bg-dark {
                background-color: #1e1e1e;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            }

            .navbar-nav .nav-link {
                font-size: 1rem;
                font-weight: 500;
                color: #d9d9d9;
                padding: 0.75rem 1.25rem;
                text-transform: uppercase;
                transition: color 0.3s ease, transform 0.2s ease;
            }

            .navbar-nav .nav-link:hover {
                color: #24D855;
                transform: translateY(-3px);
            }

            .navbar-nav .nav-link.active {
                color: #ffffff;
                font-weight: 600;
                border-bottom: 2px solid #24D855;
            }


            .navbar-toggler-icon {
                background-color: #ffffff;
            }

            @media (max-width: 992px) {
                .navbar-dark.bg-dark {
                    background-color: #121212;
                }
            }

            .key-container {
                perspective: 800px;
                user-select: none;
                align-self: end;
                justify-self: end;
            }

            .key-container>.keyboard-btn {
                padding: 20px 30px;
                font-size: 20px;
                font-weight: bold;
                text-align: center;
                color: #f0f0f0;
                background: linear-gradient(145deg, #2d2d2f, #1e1e20);
                border: none;
                border-radius: 10px;
                box-shadow:
                    0 8px 0 #161616,
                    0 -2px 5px rgba(255, 255, 255, 0.1) inset,
                    0 3px 8px rgba(0, 0, 0, 0.6) inset;
                transform: translateZ(10px);
                transition: all 0.2s ease;
                cursor: pointer;
            }

            .key-container>.keyboard-btn:hover {
                background: linear-gradient(145deg, #3a3a3c, #2c2c2e);
            }

            .key-container>.keyboard-btn:active {
                box-shadow:
                    0 4px 0 #161616,
                    0 -2px 4px rgba(255, 255, 255, 0.05) inset,
                    0 1px 4px rgba(0, 0, 0, 0.8) inset;
                transform: translateY(4.5px) translateZ(6px);
                background: linear-gradient(145deg, #262628, #1a1a1c);
            }

            .key-container>a {
                text-decoration: none;
                color: #f0f0f0;
            }
        </style>

    <?php } ?>