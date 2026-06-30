<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Hub</title>
    <!-- Bootstrap CSS -->
    <link href="{{ asset('css/main.css') }}" rel="stylesheet">
    <style>
        body {
            background: #121212;
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            padding-top: 80px;
            padding-bottom: 80px;
        }

        .download-btn {
            border-radius: 12px;
            padding: 15px 30px;
            font-size: 1.1rem;
            margin: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .download-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
        }

        h1 {
            text-align: center;
            margin-bottom: 50px;
            font-weight: 700;
            color: #f5f5f5;
        }
    </style>
</head>

<body>
    <div class="text-center container">
        <h1>Download</h1>
        <div class="d-flex flex-wrap justify-content-center">
            <a href="{{ $laragon }}" class="btn btn-primary download-btn">Download Laragon Ver. 6</a>
            <a href="{{ $xampp }}" class="btn btn-success download-btn">Download Xampp (8.2.12 / PHP 8.2.12)</a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <!-- <script src="{{ asset('js/main.js') }}"></script> -->
</body>

</html>