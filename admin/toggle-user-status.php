<?php
// Deprecated: toggle logic now lives inline in users.php (users.php?toggle_id=X).
// Kept only for backward compatibility with old links - no separate logic here.
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
header('Location: users.php' . ($id > 0 ? '?toggle_id=' . $id : ''));
exit;
