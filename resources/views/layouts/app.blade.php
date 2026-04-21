<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Kroger Clone', ENT_QUOTES, 'UTF-8') ?></title>
    <script type="module" src="/public/dist/main.js"></script>
    <link rel="stylesheet" href="/public/dist/styles.css">
</head>
<body class="bg-slate-50 text-slate-900">
<div id="app"><?= $content ?? '' ?></div>
</body>
</html>
