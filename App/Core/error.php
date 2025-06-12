<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style nonce="<?= $_nonce ?>">
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

        .container {
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            padding: 3rem 0;
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
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-custom:hover {
            background-color: #666;
            transform: translateY(2px);
        }

        .trace {
            max-height: 300px;
            overflow: auto;
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

        /* Form styles */
        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .mb-3 {
            margin-bottom: 1rem;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .w-75 {
            width: 75%;
        }

        .input-group {
            display: flex;
            align-items: stretch;
            width: 100%;
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            background-clip: padding-box;
            border: 1px solid rgba(255, 255, 255, 0.2);
            appearance: none;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .rounded-start {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .input-group-text {
            display: flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-left: none;
        }

        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            border-radius: 0 0.25rem 0.25rem 0;
            cursor: pointer;
            border: 1px solid transparent;
            border-left: none;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
        }

        .card-subtitle {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }

        /* Flex utilities */
        .d-flex {
            display: flex;
        }

        .flex-column {
            flex-direction: column;
        }

        .justify-content-center {
            justify-content: center;
        }

        .align-items-center {
            align-items: center;
        }

        .gap-3 {
            gap: 1rem;
        }

        .py-5 {
            padding-top: 3rem;
            padding-bottom: 3rem;
        }

        .p-3 {
            padding: 1rem;
        }

        .p-4 {
            padding: 1.5rem;
        }

        .p-5 {
            padding: 3rem;
        }
    </style>
    <title><?= $title_name ?></title>
</head>

<body>
    <div class="container">
        <div class="error-container text-center">
            <h1 class="error_code"><?= http_response_code() ?></h1>
            <h2 class="error_message"><?= $error_message ?></h2>
            <p class="error_sub_message"><?= $error_sub_message ?></p>
            <?php if ($returnButton) { ?>
                <a href="<?= $url ?>" class="btn-custom mt-3"><?= $btnTextContent ?? 'Return' ?></a>
            <?php } ?>
        </div>
        <?php if ($add_new_class) { ?>
            <div class="error-container text-center">
                <form action="/AUTO-LOAD/REGISTER" method="POST" style="display: flex; flex-direction: column; justify-content: center; align-items: center;">
                    <h1 class="error_message mb-3">Register new class</h1>
                    <div class="input-group mb-3 w-75">
                        <input type="hidden" name="class-name" value="<?= $error_message ?>" id="class_name">
                        <input type="text" name="class-path" class="form-control rounded-start" placeholder="Class path..." aria-describedby="basic-addon1" required>
                        <span class="input-group-text" id="basic-addon1">.php</span>
                        <button class="btn-secondary" type="submit" id="button-addon2">Add</button>
                    </div>
                </form>
            </div>
        <?php } ?>

        <?php if (!empty($trace)) { ?>
            <div class="error-container text-left">
                <p class="card-subtitle">Trace:</p>
                <pre class="trace"><?= $trace ?></pre>
            </div>
        <?php } ?>
    </div>

</body>

</html>