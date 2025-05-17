<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Title?</title>
</head>

<body>
    <div>
        <h1>testing validations</h1>
        <form action="/form-endpoint" method="POST">
            @csrf
            @method('')
            <label for="name">Name:</label>
            <input type="text" name="name" id="name">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password">
            <button>Test server's validation</button>
        </form>
        <div>
            <h3>Validations</h3>
            <ul>
                <li>Name: required, min 3 chars</li>
                <li>PAssword: required, min 8 chars</li>
            </ul>
        </div>
    </div>
</body>

</html>