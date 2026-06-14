<!DOCTYPE html>
<html lang="<?php echo rateb_locale(); ?>" dir="<?php echo rateb_locale() === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Rateb\App\Core\View::escape($title ?? 'Print'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 1.5rem; background: #fff; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
        .rateb-po-print-header { border-bottom: 3px solid #1e3a5f; margin-bottom: 1rem; padding-bottom: .5rem; }
        .rateb-po-print-table th { background: #1e3a5f; color: #fff; }
        .rateb-po-print-total { background: #1e3a5f; color: #fff; font-weight: bold; }
    </style>
</head>
<body>
    <div class="no-print mb-3">
        <button type="button" class="btn btn-primary" onclick="window.print()"><?php echo __('print'); ?></button>
        <button type="button" class="btn btn-outline-secondary" onclick="window.close()"><?php echo __('close'); ?></button>
    </div>
    <?php echo $pageContent; ?>
</body>
</html>
