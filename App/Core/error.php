<?php
$requestUri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$error_code = filter_var(http_response_code());
$current_file = basename($_SERVER["PHP_SELF"]);
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
            height: 90vh;
            margin: 0;
            font-family: 'Helvetica Neue', sans-serif;
        }

        .error-container {
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 40px 30px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
            max-width: 90vw;
            min-width: 50vw;
        }

        h1 {
            font-size: clamp(4rem, 10vw, 8rem);
            font-weight: bold;
            margin: 0;
            text-shadow: 1px 1px 5px rgba(0, 0, 0, 0.3);
        }

        h2 {
            font-size: clamp(1.5rem, 5vw, 2rem);
            margin: 20px 0;
        }

        p {
            font-size: clamp(1rem, 4vw, 1.25rem);
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
    </style>
    <title><?= $title_name ?></title>
</head>

<body>
    <div class="container d-flex justify-content-center align-items-center">
        <div class="error-container p-5">
            
            <h1><?= $error_code ?></h1>
            <h2><?= $error_message ?></h2>
            <p><?= $error_sub_message ?></p>
            <?php if ($returnButton) { ?>
                <a href="<?= $url ?>" class="btn btn-custom mt-3"><?= $btnTextContent ?? 'Return' ?></a>
            <?php } ?>

        </div>
    </div>

    <script src="<?= $jsFile ?>"></script>
</body>

</html>