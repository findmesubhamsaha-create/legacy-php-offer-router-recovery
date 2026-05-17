

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
      * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.page404 {
	display: flex;
	justify-content: center;
	align-items: center;
	height: 100vh;
	font-family: Arial, sans-serif;
	background-color: #f4f4f4;
}

.container_404 {
    text-align: center;
    padding: 20px;
    background: #fff;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
}

.error h1 {
    font-size: 6em;
    color: #000;
}

.error p {
    font-size: 1.5em;
    color: #333;
    margin: 20px 0;
}

.home-button {
    display: inline-block;
    margin-top: 20px;
    padding: 10px 20px;
    color: #fff;
    background-color: #000;
    text-decoration: none;
    border-radius: 5px;
    transition: background-color 0.3s;
}

.home-button:hover {
    background-color: #000;
}

.home-button i {
    margin-right: 10px;
}

    </style>
</head>
<body>

   <div class="page404">
    <div class="container_404">
        <div class="error">
            <h1><i class="fas fa-exclamation-triangle"></i> 404</h1>
            <p>Oops! The page you're looking for doesn't exist.</p>
            <!-- <a href="/" class="home-button"><i class="fas fa-home"></i> Go to Homepage</a> -->
        </div>
    </div>
    </div>
</body>
</html>
