(function () {
  var root = document.documentElement;
  var saved = localStorage.getItem('rateb-theme') || 'light';
  root.dataset.theme = saved === 'dark' ? 'dark' : 'light';

  function setTheme() {
    var dark = root.dataset.theme === 'dark';
    root.dataset.theme = dark ? 'light' : 'dark';
    localStorage.setItem('rateb-theme', root.dataset.theme);
    var button = document.getElementById('theme');
    if (button) button.textContent = dark ? '☾' : '☀';
  }

  var themeButton = document.getElementById('theme');
  if (themeButton) {
    themeButton.textContent = saved === 'dark' ? '☀' : '☾';
    themeButton.onclick = setTheme;
  }

  var dict = {
    en: {
      dashboard: 'Dashboard',
      new_campaign: 'New Campaign',
      new_campaign_button: '+ New Campaign',
      logout: 'Logout',
      campaign_details: 'CAMPAIGN DETAILS',
      product: 'Product / Service',
      price: 'Price',
      target: 'Target customer',
      description: 'Description',
      generate: 'Generate AI Campaign',
      results: 'AI Results',
      no_results: 'No AI results yet. Generate the campaign above.',
      login: 'Login',
      create_account: 'Create Account',
      hero_title: 'AI Campaign Generator',
      hero_lead: 'Create marketing campaigns, social media content, WhatsApp messages and creative ideas using AI.',
      start_creating: 'Start Creating',
      feature_campaigns_title: 'AI Campaigns',
      feature_campaigns_body: 'Generate complete marketing campaign ideas from your product or service.',
      feature_social_title: 'Social Media',
      feature_social_body: 'Create ready-to-use posts and promotional content for social platforms.',
      feature_whatsapp_title: 'WhatsApp',
      feature_whatsapp_body: 'Generate promotional WhatsApp messages designed for your target customers.',
      welcome_back: 'Welcome back',
      login_lead: 'Login to your RATEB AI Campaign workspace.',
      email: 'Email',
      password: 'Password',
      login_button: 'Login',
      no_account: "Don't have an account?",
      create_one: 'Create one',
      create_title: 'Create your account',
      register_lead: 'Start creating AI-powered marketing campaigns.',
      name: 'Name',
      create_button: 'Create Account',
      have_account: 'Already have an account?',
      invalid_login: 'Invalid email or password.',
      register_invalid: 'Please enter valid information. Password must be at least 8 characters.',
      email_taken: 'Email already registered.',
      workspace: 'AI CAMPAIGN WORKSPACE',
      welcome: 'Welcome',
      manage_lead: 'Create and manage your marketing campaigns.',
      your_campaigns: 'Your campaigns',
      no_campaigns: 'No campaigns yet',
      no_campaigns_lead: 'Create your first campaign to get started.',
      create_campaign: 'Create Campaign',
      col_title: 'Title',
      col_product: 'Product',
      col_status: 'Status',
      col_created: 'Created',
      new_campaign_eyebrow: 'NEW CAMPAIGN',
      campaign_form_title: 'Campaign details',
      campaign_title: 'Campaign title *',
      product_required: 'Product / Service *',
      description_required: 'Description *',
      target_required: 'Target customer *',
      save_campaign: 'Save Campaign',
      generating: 'Generating campaign...',
      type_strategy: 'Campaign Strategy',
      type_ad_copy: 'Ad Copy',
      type_social_posts: 'Social Media Posts',
      type_whatsapp: 'WhatsApp Message',
      type_product_description: 'Product Description',
      type_video_ideas: 'Video Ideas',
      type_voiceover: 'Voice-over Script',
      type_content_plan_7_days: '7-Day Content Plan',
      media_gallery: 'Media gallery',
      provider_required: 'Provider required',
      text_available: 'Text: available',
      image_available: 'Image: available',
      voice_available: 'Voice: available',
      generate_image: 'Generate image',
      generating_image: 'Generating image...',
      image_queued: 'Image generation is still queued on the free provider. Try again.',
      video_provider_required: 'AI video generation requires a video provider. No free video API is available, and the current Groq credential has no video model.',
      upload_image: 'Upload image',
      upload_video: 'Upload video',
      upload_audio: 'Upload audio',
      upload: 'Upload',
      generate_voice: 'Generate voice',
      generating_voice: 'Generating voice...',
      no_media: 'No media yet.',
      download: 'Download',
      delete: 'Delete',
      media_error_size: 'That file is too large.',
      media_error_type: 'That file type is not allowed.',
      media_error_upload: 'The file could not be saved.',
      brand_kit: 'Brand kit',
      brand_kit_title: 'Brand kit',
      brand_lead: 'These details are optional context for future campaign copy.',
      brand_saved: 'Brand kit saved.',
      brand_name: 'Brand name',
      brand_tone: 'Tone of voice',
      brand_contact: 'Contact information',
      brand_language: 'Preferred language',
      brand_primary: 'Primary color',
      brand_secondary: 'Secondary color',
      brand_logo: 'Logo',
      save_brand: 'Save brand kit',
      objective: 'Objective',
      budget: 'Budget',
      start_date: 'Start date',
      end_date: 'End date',
      dates: 'Dates',
      progress: 'Progress',
      campaign_workspace: 'Campaign workspace',
      save_changes: 'Save changes',
      strategy_heading: 'AI-generated strategy',
      no_strategy: 'No strategy yet. Generate the campaign below.',
      status_draft: 'Draft',
      status_in_progress: 'In Progress',
      status_ready: 'Ready',
      status_completed: 'Completed',
      content_planner: 'Content planner',
      item_title: 'Content title',
      item_body: 'Content',
      planner_status: 'Planner status',
      approval_status: 'Approval',
      add_content: 'Add content',
      no_items: 'No content items yet.',
      assign_date: 'Assign to date',
      add_to_calendar: 'Add to calendar',
      planner_draft: 'Draft',
      planner_review: 'Review',
      planner_approved: 'Approved',
      planner_scheduled: 'Scheduled',
      planner_published: 'Published',
      approval_draft: 'Draft',
      approval_approved: 'Approved',
      approval_rejected: 'Rejected',
      save_variation: 'Save current copy as a variation',
      generate_variation: 'Generate another variation',
      generating_variation: 'Generating variation...',
      variations: 'Content variations',
      no_variations: 'No saved variations yet.',
      tab_overview: 'Overview',
      tab_ai: 'AI Content',
      tab_media: 'Media',
      tab_planner: 'Planner',
      tab_variations: 'Variations',
      tab_brand: 'Brand',
      open_ai: 'Open AI content',
      kpi_outputs: 'AI outputs',
      kpi_media: 'Media files',
      kpi_items: 'Planner items',
      kpi_variations: 'Variations',
      not_set: 'Not set',
      section_basics: 'Basics',
      section_offer: 'Audience and offer',
      section_schedule: 'Schedule',
      form_lead: 'Add the campaign details. You can generate content after saving.',
      brand_panel_lead: 'Brand name, logo, colors, language, and tone are saved in the brand kit and used as optional context for later copy.',
      open_brand: 'Open brand kit',
      studio_headline: 'Turn your idea into a complete marketing campaign with AI',
      studio_lead: 'Strategy, ad copy, social posts, images, Saudi Arabic voice, and a content plan — in one creative studio.',
      studio_cta: 'Start a new campaign',
      studio_flow_label: 'How a campaign comes together',
      flow_idea: 'Idea',
      flow_copy: 'Copy',
      flow_images: 'Images',
      flow_voice: 'Voice',
      flow_video: 'Video',
      flow_ready: 'Ready campaign',
      showcase_label: 'What you can create',
      show_strategy_title: 'AI campaign strategy',
      show_strategy_body: 'A clear plan for the offer, audience, and message.',
      show_copy_title: 'Ad copy',
      show_copy_body: 'Ready lines for ads, landing pages, and offers.',
      show_social_title: 'Social posts',
      show_social_body: 'Posts shaped for the platforms your customers use.',
      show_images_title: 'AI images',
      show_images_body: 'Campaign visuals generated for this brand.',
      show_voice_title: 'Saudi Arabic voice',
      show_voice_body: 'A natural Saudi Arabic voice-over from your script.',
      show_video_title: 'Video ideas',
      show_video_body: 'Shot ideas and scripts. Video rendering stays unavailable.',
      show_planner_title: 'Content planner',
      show_planner_body: 'Dates, drafts, and approvals for what goes live next.',
      home_how: 'See how it works',
      gallery_title: 'See what RATEB AI can make',
      gallery_note: 'Visual examples only. Video generation is not available yet.',
      play_example: 'Play example',
      demo_close: 'Close',
      demo_example: 'Example',
      flow_strategy: 'Strategy',
      workspace_title: 'The real campaign workspace',
      formats_title: 'Formats your campaign can speak in',
      home_create_title: 'What can RATEB AI create?',
      sample_ad: 'A quiet evening fragrance, with a soft oud note.',
      sample_social: 'Hosting is easier now. Order today, delivery in Riyadh.',
      sample_wa: 'Hello. This weekend’s hosting bundle is on offer. Shall I send the details?',
      sample_story: 'Today’s offer',
      sample_product: 'Hospitality set',
      sample_card: 'Campaign brief',
      format_instagram: 'Instagram',
      format_tiktok: 'TikTok',
      format_whatsapp: 'WhatsApp',
      format_stories: 'Stories',
      format_feed: 'Feed ad',
      format_product: 'Product ad',
      format_short: 'Short video'
    },
    ar: {
      dashboard: 'لوحة التحكم',
      new_campaign: 'حملة جديدة',
      new_campaign_button: '+ حملة جديدة',
      logout: 'تسجيل الخروج',
      campaign_details: 'تفاصيل الحملة',
      product: 'المنتج / الخدمة',
      price: 'السعر',
      target: 'العميل المستهدف',
      description: 'الوصف',
      generate: 'توليد الحملة بالذكاء الاصطناعي',
      results: 'نتائج الذكاء الاصطناعي',
      no_results: 'لا توجد نتائج بعد. اضغط توليد الحملة أعلاه.',
      login: 'تسجيل الدخول',
      create_account: 'إنشاء حساب',
      hero_title: 'مولد الحملات بالذكاء الاصطناعي',
      hero_lead: 'أنشئ حملات تسويقية ومحتوى لوسائل التواصل ورسائل واتساب وأفكاراً إبداعية باستخدام الذكاء الاصطناعي.',
      start_creating: 'ابدأ الإنشاء',
      feature_campaigns_title: 'حملات الذكاء الاصطناعي',
      feature_campaigns_body: 'ولّد أفكار حملات تسويقية كاملة من منتجك أو خدمتك.',
      feature_social_title: 'وسائل التواصل',
      feature_social_body: 'أنشئ منشورات جاهزة ومحتوى ترويجياً للمنصات الاجتماعية.',
      feature_whatsapp_title: 'واتساب',
      feature_whatsapp_body: 'ولّد رسائل واتساب ترويجية موجهة لعملائك.',
      welcome_back: 'مرحباً بعودتك',
      login_lead: 'سجّل الدخول إلى مساحة حملات رتب للذكاء الاصطناعي.',
      email: 'البريد الإلكتروني',
      password: 'كلمة المرور',
      login_button: 'تسجيل الدخول',
      no_account: 'ليس لديك حساب؟',
      create_one: 'أنشئ حساباً',
      create_title: 'أنشئ حسابك',
      register_lead: 'ابدأ بإنشاء حملات تسويقية بالذكاء الاصطناعي.',
      name: 'الاسم',
      create_button: 'إنشاء الحساب',
      have_account: 'لديك حساب بالفعل؟',
      invalid_login: 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
      register_invalid: 'أدخل بيانات صحيحة. يجب أن تكون كلمة المرور 8 أحرف على الأقل.',
      email_taken: 'هذا البريد مسجّل مسبقاً.',
      workspace: 'مساحة الحملات',
      welcome: 'مرحباً',
      manage_lead: 'أنشئ حملاتك التسويقية وأدرها.',
      your_campaigns: 'حملاتك',
      no_campaigns: 'لا توجد حملات بعد',
      no_campaigns_lead: 'أنشئ حملتك الأولى للبدء.',
      create_campaign: 'إنشاء حملة',
      col_title: 'العنوان',
      col_product: 'المنتج',
      col_status: 'الحالة',
      col_created: 'تاريخ الإنشاء',
      new_campaign_eyebrow: 'حملة جديدة',
      campaign_form_title: 'تفاصيل الحملة',
      campaign_title: 'عنوان الحملة *',
      product_required: 'المنتج / الخدمة *',
      description_required: 'الوصف *',
      target_required: 'العميل المستهدف *',
      save_campaign: 'حفظ الحملة',
      generating: 'جاري توليد الحملة...',
      type_strategy: 'استراتيجية الحملة',
      type_ad_copy: 'نص الإعلان',
      type_social_posts: 'منشورات التواصل',
      type_whatsapp: 'رسالة واتساب',
      type_product_description: 'وصف المنتج',
      type_video_ideas: 'أفكار الفيديو',
      type_voiceover: 'نص التعليق الصوتي',
      type_content_plan_7_days: 'خطة محتوى لسبعة أيام',
      media_gallery: 'معرض الوسائط',
      provider_required: 'مزود مطلوب',
      text_available: 'النص: متاح',
      image_available: 'الصورة: متاحة',
      voice_available: 'الصوت: متاح',
      generate_image: 'توليد صورة',
      generating_image: 'جاري توليد الصورة...',
      image_queued: 'توليد الصورة ما زال في الانتظار لدى المزود المجاني. حاول مرة أخرى.',
      video_provider_required: 'توليد الفيديو بالذكاء الاصطناعي يحتاج مزود فيديو. لا توجد واجهة فيديو مجانية، وبيانات Groq الحالية لا تتضمن نموذج فيديو.',
      upload_image: 'رفع صورة',
      upload_video: 'رفع فيديو',
      upload_audio: 'رفع صوت',
      upload: 'رفع',
      generate_voice: 'توليد التعليق الصوتي',
      generating_voice: 'جاري توليد الصوت...',
      no_media: 'لا توجد وسائط بعد.',
      download: 'تنزيل',
      delete: 'حذف',
      media_error_size: 'حجم الملف أكبر من المسموح.',
      media_error_type: 'نوع الملف غير مسموح.',
      media_error_upload: 'تعذر حفظ الملف.',
      brand_kit: 'هوية العلامة',
      brand_kit_title: 'هوية العلامة',
      brand_lead: 'هذه البيانات سياق اختياري لتوليد محتوى الحملات لاحقاً.',
      brand_saved: 'تم حفظ هوية العلامة.',
      brand_name: 'اسم العلامة',
      brand_tone: 'نبرة الخطاب',
      brand_contact: 'بيانات التواصل',
      brand_language: 'اللغة المفضلة',
      brand_primary: 'اللون الأساسي',
      brand_secondary: 'اللون الثانوي',
      brand_logo: 'الشعار',
      save_brand: 'حفظ هوية العلامة',
      objective: 'الهدف',
      budget: 'الميزانية',
      start_date: 'تاريخ البداية',
      end_date: 'تاريخ النهاية',
      dates: 'التواريخ',
      progress: 'التقدم',
      campaign_workspace: 'مساحة الحملة',
      save_changes: 'حفظ التغييرات',
      strategy_heading: 'الاستراتيجية المولدة',
      no_strategy: 'لا توجد استراتيجية بعد. ولّد الحملة بالأسفل.',
      status_draft: 'مسودة',
      status_in_progress: 'قيد التنفيذ',
      status_ready: 'جاهزة',
      status_completed: 'مكتملة',
      content_planner: 'مخطط المحتوى',
      item_title: 'عنوان المحتوى',
      item_body: 'المحتوى',
      planner_status: 'حالة المخطط',
      approval_status: 'الاعتماد',
      add_content: 'إضافة محتوى',
      no_items: 'لا توجد عناصر محتوى بعد.',
      assign_date: 'تعيين لتاريخ',
      add_to_calendar: 'إضافة إلى التقويم',
      planner_draft: 'مسودة',
      planner_review: 'مراجعة',
      planner_approved: 'معتمد',
      planner_scheduled: 'مجدول',
      planner_published: 'منشور',
      approval_draft: 'مسودة',
      approval_approved: 'معتمد',
      approval_rejected: 'مرفوض',
      save_variation: 'حفظ النسخة الحالية كتنويع',
      generate_variation: 'توليد تنويع آخر',
      generating_variation: 'جاري توليد التنويع...',
      variations: 'تنويعات المحتوى',
      no_variations: 'لا توجد تنويعات محفوظة بعد.',
      tab_overview: 'نظرة عامة',
      tab_ai: 'محتوى الذكاء الاصطناعي',
      tab_media: 'الوسائط',
      tab_planner: 'المخطط',
      tab_variations: 'التنويعات',
      tab_brand: 'العلامة',
      open_ai: 'فتح محتوى الذكاء الاصطناعي',
      kpi_outputs: 'مخرجات الذكاء الاصطناعي',
      kpi_media: 'ملفات الوسائط',
      kpi_items: 'عناصر المخطط',
      kpi_variations: 'التنويعات',
      not_set: 'غير محدد',
      section_basics: 'الأساسيات',
      section_offer: 'الجمهور والعرض',
      section_schedule: 'الجدول',
      form_lead: 'أضف تفاصيل الحملة. يمكن توليد المحتوى بعد الحفظ.',
      brand_panel_lead: 'اسم العلامة والشعار والألوان واللغة والنبرة محفوظة في هوية العلامة وتُستخدم كسياق اختياري للمحتوى لاحقاً.',
      open_brand: 'فتح هوية العلامة',
      studio_headline: 'حوّل فكرتك إلى حملة تسويقية كاملة بالذكاء الاصطناعي',
      studio_lead: 'استراتيجية، نص إعلان، منشورات، صور، صوت عربي سعودي، وخطة محتوى — في استوديو واحد.',
      studio_cta: 'ابدأ حملة جديدة',
      studio_flow_label: 'كيف تكتمل الحملة',
      flow_idea: 'فكرة',
      flow_copy: 'محتوى',
      flow_images: 'صور',
      flow_voice: 'صوت',
      flow_video: 'فيديو',
      flow_ready: 'حملة جاهزة',
      showcase_label: 'ما يمكنك إنشاؤه',
      show_strategy_title: 'استراتيجية الحملة',
      show_strategy_body: 'خطة واضحة للعرض والجمهور والرسالة.',
      show_copy_title: 'نص الإعلان',
      show_copy_body: 'عبارات جاهزة للإعلانات والصفحات والعروض.',
      show_social_title: 'منشورات التواصل',
      show_social_body: 'منشورات مناسبة للمنصات التي يستخدمها عملاؤك.',
      show_images_title: 'صور بالذكاء الاصطناعي',
      show_images_body: 'صور حملة تُولَّد لهذه العلامة.',
      show_voice_title: 'صوت عربي سعودي',
      show_voice_body: 'تعليق صوتي بلهجة سعودية طبيعية من النص.',
      show_video_title: 'أفكار الفيديو',
      show_video_body: 'أفكار لقطات ونصوص. توليد الفيديو غير متاح حالياً.',
      show_planner_title: 'مخطط المحتوى',
      show_planner_body: 'تواريخ ومسودات واعتماد لما يُنشر لاحقاً.',
      home_how: 'شاهد كيف يعمل',
      gallery_title: 'شاهد ماذا يمكن لـ RATEB AI أن يصنع',
      gallery_note: 'أمثلة بصرية فقط. توليد الفيديو غير متاح حالياً.',
      play_example: 'تشغيل المثال',
      demo_close: 'إغلاق',
      demo_example: 'مثال',
      flow_strategy: 'استراتيجية',
      workspace_title: 'مساحة الحملة كما تعمل فعلاً',
      formats_title: 'قوالب تتحدث بها الحملة',
      home_create_title: 'ماذا يستطيع RATEB AI أن يصنع؟',
      sample_ad: 'عطر سهرة بلمسة عود هادئة.',
      sample_social: 'تجهيزات الضيافة صارت أسهل. اطلب اليوم والتوصيل داخل الرياض.',
      sample_wa: 'السلام عليكم، عرض نهاية الأسبوع على بكج الضيافة جاهز. أرسل لك التفاصيل؟',
      sample_story: 'عرض اليوم',
      sample_product: 'طقم ضيافة',
      sample_card: 'ملخص الحملة',
      format_instagram: 'إنستغرام',
      format_tiktok: 'تيك توك',
      format_whatsapp: 'واتساب',
      format_stories: 'ستوري',
      format_feed: 'إعلان الخلاصة',
      format_product: 'إعلان منتج',
      format_short: 'فيديو قصير'
    }
  };

  var langButton = document.getElementById('lang');
  var language = localStorage.getItem('rateb-lang') === 'ar' ? 'ar' : 'en';

  function apply() {
    var labels = dict[language];
    document.querySelectorAll('[data-i18n]').forEach(function (node) {
      var key = node.dataset.i18n;
      if (labels[key]) node.textContent = labels[key];
    });
    root.lang = language;
    root.dir = language === 'ar' ? 'rtl' : 'ltr';
    if (langButton) langButton.textContent = language === 'ar' ? 'English' : 'عربي';
    localStorage.setItem('rateb-lang', language);
    document.querySelectorAll('input[name="lang"]').forEach(function (node) {
      node.value = language;
    });
  }

  if (langButton) {
    langButton.onclick = function () {
      language = language === 'en' ? 'ar' : 'en';
      apply();
    };
  }
  apply();
  initTabs();

  function initTabs() {
    var tabs = document.querySelectorAll('[data-tab]');
    if (!tabs.length) return;
    var names = ['overview', 'ai', 'media', 'planner', 'variations', 'brand'];
    function openTab(name) {
      if (names.indexOf(name) < 0) name = 'overview';
      document.body.classList.add('tabs-ready');
      document.querySelectorAll('[data-panel]').forEach(function (panel) {
        panel.classList.toggle('is-active', panel.getAttribute('data-panel') === name);
      });
      tabs.forEach(function (tab) {
        var on = tab.getAttribute('data-tab') === name;
        tab.classList.toggle('is-active', on);
        tab.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    }
    tabs.forEach(function (tab) {
      tab.onclick = function () {
        var name = tab.getAttribute('data-tab');
        openTab(name);
        history.replaceState(null, '', '#' + name);
      };
    });
    document.querySelectorAll('[data-open]').forEach(function (node) {
      node.onclick = function () {
        var name = node.getAttribute('data-open');
        openTab(name);
        history.replaceState(null, '', '#' + name);
      };
    });
    var hash = (location.hash || '').replace('#', '');
    if (hash === 'details') hash = 'overview';
    if (hash === 'strategy' || hash === 'copy') hash = 'ai';
    if (new URLSearchParams(location.search).get('media_error')) hash = 'media';
    openTab(names.indexOf(hash) >= 0 ? hash : 'overview');
  }

  var form = document.getElementById('generateForm');
  if (form) {
    form.onsubmit = async function (event) {
      event.preventDefault();
      var status = document.getElementById('status');
      var button = document.getElementById('generate');
      var langInput = document.getElementById('gen-lang');
      if (langInput) langInput.value = language;
      button.disabled = true;
      button.classList.add('is-busy');
      status.className = 'notice';
      status.textContent = dict[language].generating;
      try {
        var response = await fetch('/app/ai/generate.php', { method: 'POST', body: new FormData(form) });
        var payload = await response.json();
        if (!response.ok) throw new Error(payload.error || 'Generation failed');
        location.href = payload.redirect;
      } catch (error) {
        status.className = 'notice is-error';
        status.textContent = error.message;
        button.disabled = false;
        button.classList.remove('is-busy');
      }
    };
  }

  var imageForm = document.getElementById('imageForm');
  if (imageForm) {
    imageForm.onsubmit = async function (event) {
      event.preventDefault();
      var status = document.getElementById('image-status');
      var button = document.getElementById('generate-image');
      button.disabled = true;
      button.classList.add('is-busy');
      status.className = 'notice';
      status.textContent = dict[language].generating_image;
      try {
        var payload = await requestImage(imageForm, '');
        var waits = 0;
        while (payload.pending) {
          if (waits >= 240) throw new Error(dict[language].image_queued);
          await new Promise(function (resolve) { setTimeout(resolve, 3000); });
          payload = await requestImage(imageForm, payload.job_id);
          waits += 1;
        }
        if (!payload.ok) throw new Error(payload.error || 'Image generation failed');
        location.href = payload.redirect;
      } catch (error) {
        status.className = 'notice is-error';
        status.textContent = error.message;
        button.disabled = false;
        button.classList.remove('is-busy');
      }
    };
  }

  async function requestImage(form, jobId) {
    var body = new FormData(form);
    if (jobId) body.set('job_id', jobId);
    var response = await fetch('/app/ai/image.php', { method: 'POST', body: body });
    var payload = await response.json();
    if (!response.ok) throw new Error(payload.error || 'Image generation failed');
    return payload;
  }

  var voiceForm = document.getElementById('voiceForm');
  if (voiceForm) {
    voiceForm.onsubmit = async function (event) {
      event.preventDefault();
      var status = document.getElementById('voice-status');
      var button = document.getElementById('voice');
      document.querySelectorAll('input[name="lang"]').forEach(function (node) {
        node.value = language;
      });
      button.disabled = true;
      button.classList.add('is-busy');
      status.className = 'notice';
      status.textContent = dict[language].generating_voice;
      try {
        var response = await fetch('/app/ai/voice.php', { method: 'POST', body: new FormData(voiceForm) });
        var payload = await response.json();
        if (!response.ok) throw new Error(payload.error || 'Voice generation failed');
        location.href = payload.redirect;
      } catch (error) {
        status.className = 'notice is-error';
        status.textContent = error.message;
        button.disabled = false;
        button.classList.remove('is-busy');
      }
    };
  }
  var variationForm = document.getElementById('variationForm');
  if (variationForm) {
    variationForm.onsubmit = async function (event) {
      event.preventDefault();
      var status = document.getElementById('variation-status');
      var button = document.getElementById('generate-variation');
      document.querySelectorAll('input[name="lang"]').forEach(function (node) {
        node.value = language;
      });
      button.disabled = true;
      status.className = 'notice';
      button.classList.add('is-busy');
      status.textContent = dict[language].generating_variation;
      try {
        var response = await fetch('/app/ai/variation.php', { method: 'POST', body: new FormData(variationForm) });
        var payload = await response.json();
        if (!response.ok) throw new Error(payload.error || 'Variation failed');
        location.href = payload.redirect;
      } catch (error) {
        status.className = 'notice is-error';
        status.textContent = error.message;
        button.disabled = false;
        button.classList.remove('is-busy');
      }
    };
  }

  var demoDialog = document.getElementById('demo-viewer');
  var demoStage = document.getElementById('demo-stage');

  function openDemo(node) {
    if (!demoDialog || !demoStage) return;
    var kind = node.getAttribute('data-kind');
    var src = node.getAttribute('data-src');
    if (!kind || !src) return;
    demoStage.replaceChildren();
    var media;
    if (kind === 'video') {
      media = document.createElement('video');
      media.controls = true;
      media.autoplay = true;
      media.playsInline = true;
    } else if (kind === 'audio') {
      media = document.createElement('audio');
      media.controls = true;
      media.autoplay = true;
    } else {
      media = document.createElement('img');
      media.alt = '';
    }
    media.src = src;
    demoStage.appendChild(media);
    if (kind !== 'image' && media.play) {
      var pending = media.play();
      if (pending && pending.catch) pending.catch(function () {});
    }
    if (typeof demoDialog.showModal === 'function') demoDialog.showModal();
    else demoDialog.setAttribute('open', '');
  }

  document.querySelectorAll('[data-kind]').forEach(function (node) {
    node.addEventListener('click', function () { openDemo(node); });
    node.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openDemo(node);
      }
    });
  });
  if (demoDialog && demoStage) {
    demoDialog.addEventListener('close', function () { demoStage.replaceChildren(); });
    demoDialog.addEventListener('click', function (event) {
      if (event.target === demoDialog) demoDialog.close();
    });
  }
})();
