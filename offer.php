<?php
session_start();

// Placeholder for banker offer functionality
// This will be fully implemented by other team members

if (!isset($_SESSION['game_started']) || !$_SESSION['game_started']) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal or No Deal - Banker's Offer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="offer-page">
    <div class="container">
        <header>
            <h1>Banker's Offer</h1>
        </header>
        
        <main class="offer-content">
            <div class="offer-panel">
                <p class="offer-text">Banker offer functionality will be implemented here.</p>
                <div class="offer-buttons">
                    <a href="game.php" class="button">Back to Game</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

