<?php
/**
 * EN: Shared footer partial that closes layout wrappers and loads common footer JavaScript assets.
 * AR: جزء التذييل المشترك الذي يغلق عناصر التخطيط ويحمّل ملفات JavaScript العامة في آخر الصفحة.
 */
?>
        </div> <!-- End content-wrapper -->
        
        <!-- Footer -->
        <footer class="footer">
            <div class="container">
                <div class="footer-content">
                    <?php if (defined('APP_NAME')): ?>
                    <?php endif; ?>
                </div>
            </div>
        </footer>

        <!-- EN: Core vendor scripts used across many pages.
             AR: سكربتات أساسية مشتركة عبر أغلب الصفحات. -->
        <!-- Core JavaScript -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <?php if (!empty($pageJsFooter) && is_array($pageJsFooter)): ?>
        <?php foreach ($pageJsFooter as $script): ?>
        <?php $src = is_array($script) ? ($script['src'] ?? '') : $script; ?>
        <script src="<?php echo htmlspecialchars($src); ?><?php echo (strpos($src, '?') !== false ? '&' : '?'); ?>v=<?php echo time(); ?>"></script>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Custom JavaScript -->
        <!-- navigation.js already loaded in header.php -->
        
        <!-- EN: Floating assistant widget shell (UI only; behavior loaded from JS files below).
             AR: هيكل واجهة مساعد راتب العائم (السلوك الفعلي يتم تحميله من ملفات JS بالأسفل). -->
        <!-- Ratib Assistant (floating chat) -->
        <button class="chat-widget-button" id="chatWidgetButton" aria-label="Open Chat">
            <i class="fas fa-comments"></i>
        </button>
        
        <div class="chat-widget-container" id="chatWidgetContainer">
            <div class="chat-widget-header">
                <div class="chat-widget-header-info">
                    <div class="chat-widget-header-avatar" aria-hidden="true"><i class="fas fa-wand-magic-sparkles"></i></div>
                    <div class="chat-widget-header-text">
                        <h3>Ratib Assistant</h3>
                        <p class="online">Help guides &amp; live support</p>
                    </div>
                </div>
                <div class="chat-widget-header-actions">
                    <button type="button" class="chat-widget-clear" id="chatWidgetClear" aria-label="Clear conversation" title="Clear this chat and end live support on this device.">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <button type="button" class="chat-widget-close" id="chatWidgetClose" aria-label="Close Chat">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <div class="chat-widget-messages" id="chatWidgetMessages">
                <!-- Messages will be loaded here -->
            </div>
            
            <div class="chat-widget-input-area">
                <div class="chat-widget-input-wrapper">
                    <textarea 
                        class="chat-widget-input" 
                        id="chatWidgetInput" 
                        placeholder="Type your message..."
                        rows="1"
                        data-translate="chatPlaceholder"
                    ></textarea>
                    <button class="chat-widget-send" id="chatWidgetSend" aria-label="Send Message">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <?php
        // EN: Build cache-busted versions for assistant scripts.
        // AR: توليد أرقام نسخة (cache-busting) لملفات المساعد.
        $ratibBase = getBaseUrl();
        $ratibBase = ($ratibBase !== '' && $ratibBase !== null) ? ('/' . ltrim($ratibBase, '/')) : '';
        $ratibRoot = dirname(__DIR__);
        $hcBuiltinPath = $ratibRoot . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'help-center' . DIRECTORY_SEPARATOR . 'help-center-builtin-content.js';
        $chatWidgetPath = $ratibRoot . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'chat-widget.js';
        $vHcBuiltin = is_file($hcBuiltinPath) ? filemtime($hcBuiltinPath) : time();
        $vChatWidget = is_file($chatWidgetPath) ? filemtime($chatWidgetPath) : time();
        ?>
        <script>window.RATIB_BASE_URL = window.location.origin + <?php echo json_encode($ratibBase); ?>;</script>
        <script src="<?php echo asset('js/help-center/help-center-builtin-content.js'); ?>?v=<?php echo (int) $vHcBuiltin; ?>"></script>
        <script src="<?php echo asset('js/chat-widget.js'); ?>?v=<?php echo (int) $vChatWidget; ?>"></script>
    </body>

    </html>