<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div class="lwt-checkbox-container">
    <div class="checkbox-loading-spinner"></div>
    <label>
        <input type="checkbox"
            class="lesson-complete-checkbox">
        <?php _e('Mark this lesson as complete', 'lessons-watch-time'); ?>
    </label>
</div>