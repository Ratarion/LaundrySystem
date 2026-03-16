<!-- шапка + имя пользователя + роль -->
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Стирка</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { font-family: Arial, sans-serif; background: #121212; color: #fff; margin: 0; }
        .container { display: flex; min-height: 100vh; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #333; text-align: left; }
        th { background: #1f1f1f; }
        tr:nth-child(even) { background: #1a1a1a; }
        .btn { padding: 8px 16px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-danger { background: #d32f2f; color: white; }
        .btn-primary { background: #1976d2; color: white; }
        .filter-form { background: #1f1f1f; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    </style>
</head>
<body>
<div class="container">