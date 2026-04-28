<?php
/**
 * EN: Handles API endpoint/business logic in `api/help-center/TranslationService.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/help-center/TranslationService.php`.
 */
/**
 * Translation Service for Help Center
 * Provides live translation using free translation API
 * Automatically translates and caches translations in database
 */

class TranslationService {
    private $conn;
    
    // Map our language codes to MyMemory API language codes
    private $languageCodeMap = [
        'en' => 'en',
        'ar' => 'ar',
        'hi' => 'hi',
        'bn' => 'bn',
        'tl' => 'fil',  // Filipino/Tagalog
        'id' => 'id',
        'ne' => 'ne',
        'si' => 'si',
        'am' => 'am',
        'om' => 'om',  // May not be supported
        'ti' => 'ti',  // May not be supported
        'sw' => 'sw',
        'so' => 'so'
    ];
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Translate entire tutorial content
     * Checks cache first, then translates and saves if needed
     */
    public function translateTutorial($tutorialId, $languageCode, $englishContent) {
        // Check if translation already exists
        $cached = $this->getCachedTranslation($tutorialId, $languageCode);
        if ($cached) {
            return $cached;
        }
        
        // Translate all fields
        $translated = [
            'title' => $this->translateText($englishContent['title'], $languageCode),
            'overview' => $this->translateText($englishContent['overview'], $languageCode),
            'content' => $this->translateText($englishContent['content'], $languageCode),
            'overview_text' => $this->translateText($englishContent['overview_text'] ?? '', $languageCode),
            'content_text' => $this->translateText($englishContent['content_text'] ?? '', $languageCode)
        ];
        
        // Cache translation
        $this->saveTranslation($tutorialId, $languageCode, $translated);
        
        return $translated;
    }
    
    /**
     * Get cached translation from database
     */
    private function getCachedTranslation($tutorialId, $languageCode) {
        try {
            $sql = "SELECT title, overview, content, overview_text, content_text 
                   FROM tutorial_languages 
                   WHERE tutorial_id = ? AND language_code = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tutorialId, $languageCode]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && !empty($result['title']) ? $result : null;
        } catch (Exception $e) {
            error_log("Translation cache error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Save translation to database
     */
    private function saveTranslation($tutorialId, $languageCode, $translated) {
        try {
            $sql = "INSERT INTO tutorial_languages 
                   (tutorial_id, language_code, title, overview, content, overview_text, content_text) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE 
                   title = VALUES(title),
                   overview = VALUES(overview),
                   content = VALUES(content),
                   overview_text = VALUES(overview_text),
                   content_text = VALUES(content_text),
                   updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $tutorialId,
                $languageCode,
                $translated['title'],
                $translated['overview'],
                $translated['content'],
                $translated['overview_text'],
                $translated['content_text']
            ]);
        } catch (Exception $e) {
            error_log("Translation save error: " . $e->getMessage());
        }
    }
    
    /**
     * Translate text using free translation API
     * MyMemory API has 500 char limit, so we split long content
     */
    public function translateText($text, $targetLang) {
        if (empty($text)) {
            return $text;
        }
        
        // Validate language code
        if (empty($targetLang) || $targetLang === 'en') {
            return $text; // No translation needed for English or empty
        }
        
        // Map language code to API code
        $apiLangCode = $this->languageCodeMap[$targetLang] ?? $targetLang;
        
        // MyMemory API has 500 character limit per request
        $maxLength = 450; // Use 450 to be safe
        $plainText = strip_tags($text);
        
        // If text is short enough, translate directly
        if (mb_strlen($plainText) <= $maxLength) {
            return $this->translateChunk($plainText, $apiLangCode);
        }
        
        // Split long text into sentences/paragraphs and translate in chunks
        $sentences = preg_split('/([.!?]\s+)/', $plainText, -1, PREG_SPLIT_DELIM_CAPTURE);
        $chunks = [];
        $currentChunk = '';
        
        foreach ($sentences as $sentence) {
            if (mb_strlen($currentChunk . $sentence) <= $maxLength) {
                $currentChunk .= $sentence;
            } else {
                if (!empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                }
                $currentChunk = $sentence;
            }
        }
        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }
        
        // Translate each chunk
        $translatedChunks = [];
        foreach ($chunks as $chunk) {
            $translated = $this->translateChunk($chunk, $apiLangCode);
            $translatedChunks[] = $translated;
            // Small delay to avoid rate limiting
            usleep(100000); // 0.1 second
        }
        
        return implode('', $translatedChunks);
    }
    
    /**
     * Translate a single chunk of text (max 500 chars)
     */
    private function translateChunk($text, $targetLang) {
        if (empty($text) || mb_strlen($text) > 500) {
            return $text; // Return original if too long
        }
        
        try {
            // Use MyMemory Translation API (free, no API key needed)
            $url = 'https://api.mymemory.translated.net/get?' . http_build_query([
                'q' => $text,
                'langpair' => 'en|' . $targetLang
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $result = json_decode($response, true);
                if (isset($result['responseData']['translatedText'])) {
                    $translated = $result['responseData']['translatedText'];
                    // Check for API error messages
                    if (stripos($translated, 'QUERY LENGTH LIMIT') !== false ||
                        stripos($translated, 'INVALID TARGET LANGUAGE') !== false ||
                        stripos($translated, 'UNDEFINED') !== false ||
                        stripos($translated, 'EXAMPLE: LANGPAIR') !== false) {
                        error_log("Translation API error detected: " . substr($translated, 0, 100));
                        return $text; // Return original if API rejects it
                    }
                    return $translated;
                }
                // Check for error response
                if (isset($result['responseStatus']) && $result['responseStatus'] != 200) {
                    error_log("Translation API error status: " . ($result['responseStatus'] ?? 'unknown'));
                    return $text;
                }
            }
        } catch (Exception $e) {
            error_log("Translation API error: " . $e->getMessage());
        }
        
        // Return original text if translation fails
        return $text;
    }
}
