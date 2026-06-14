<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo json_encode(['success' => true, 'message' => 'Cache opcache vidé']);
} else {
    echo json_encode(['success' => false, 'message' => 'opcache non disponible']);
}
