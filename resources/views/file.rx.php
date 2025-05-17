<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a file</title>
</head>

<body>
    <form action="{{ route('fileEnd') }}" method="post" enctype="multipart/form-data">
        <label for="file">Input a file</label>
        <input type="file" name="someFile" id="file">
        <button type="submit">send</button>
    </form>
</body>

</html>