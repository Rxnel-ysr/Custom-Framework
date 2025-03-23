<?php

use App\Utils\Http\Request;
use App\Utils\Manager\ClassManager;

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$error_code = filter_var(http_response_code());
$current_file = basename($_SERVER['PHP_SELF']);
$cssFile = asset('css/main.css');
$jsFile = asset('js/main.js');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?= $cssFile ?>" rel="stylesheet">
    <style>
        body {
            background-color: #1c1c1e;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 90dvh;
            max-height: 110dvh;
            margin: 2.5rem 0;
            font-family: 'Helvetica Neue', sans-serif;
        }

        .error-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 40px 30px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
            max-width: 90vw;
            min-width: 90vw;
        }

        .error_code {
            font-size: clamp(3rem, 10vw, 6rem);
            font-weight: bold;
            margin: -0.5rem 0;
            text-shadow: 1px 1px 5px rgba(0, 0, 0, 0.3);
        }

        .error_message {
            font-size: clamp(1.5rem, 5vw, 2rem);
            margin: 5px 0;
        }

        .error_sub_message {
            font-size: clamp(1.2rem, 4vw, 1.5rem);
            margin: 20px 0;
            opacity: 0.8;
        }

        .btn-custom {
            background-color: #444;
            color: #ffffff;
            border-radius: 30px;
            padding: 10px 20px;
            transition: background-color 0.3s, transform 0.3s;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }

        .btn-custom:hover {
            background-color: #666;
            transform: translateY(2px);
        }

        .trace {
            max-height: 300px;
            overflow: auto;
            /* white-space: pre-wrap;
            word-wrap: break-word; */
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 10px;
            font-size: 0.95rem;
        }

        /* Custom scrollbar */
        .trace::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .trace::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
        }

        .trace::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .trace::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
        }
    </style>
    <title><?= $title_name ?></title>
</head>

<body>
    <div class="container d-flex flex-column justify-content-center align-items-center gap-3 py-5">
        <div class="error-container p-5 text-center">

            <h1 class="error_code"><?= $error_code ?></h1>
            <h2 class="error_message"><?= $error_message ?></h2>
            <p class="error_sub_message"><?= $error_sub_message ?></p>
            <?php if ($returnButton) { ?>
                <a href="<?= $url ?>" class="btn btn-custom mt-3"><?= $btnTextContent ?? 'Return' ?></a>
            <?php } ?>

        </div>
        <?php if ($add_new_class === true) { ?>
            <div class="error-container p-3 text-center">
                <form action="/DEBUG/ADD_CLASS" method="post" class="d-flex flex-column justify-content-center align-items-center" enctype="multipart/form-data">
                    <h1 class="error_message mb-3">Register new class</h1>
                    <div class="input-group mb-3 w-75">
                        <input type="hidden" name="class-name" value="<?= $error_message ?>" id="class_name">
                        <input type="text" name="class-path" class="form-control rounded-start" placeholder="Class path..." aria-describedby="basic-addon1" required>
                        <span class="input-group-text" id="basic-addon1">.php</span>
                        <button class="btn btn-secondary" type="submit" id="button-addon2">Add</button>
                    </div>
                </form>
            </div>
        <?php } ?>

        <?php if (!empty($trace)) { ?>
            <div class="error-container p-4 text-left">
                <p class="card-subtitle">Trace:</p>
                <pre class="trace"><?= $trace ?></pre>
            </div>
        <?php } ?>
    </div>

    <script src="<?= $jsFile ?>"></script>
</body>

</html>