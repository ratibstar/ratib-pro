<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars((string) ($title ?? catalog__('app_name')), ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <?php require $contentView; ?>
</body>
</html>
