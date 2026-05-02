<?php
$pageJsList = isset($pageJs) && is_array($pageJs) ? $pageJs : [];
?>
</div>
<?php foreach ($pageJsList as $src): ?>
<script src="<?php echo htmlspecialchars((string) $src, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endforeach; ?>
</body>
</html>
