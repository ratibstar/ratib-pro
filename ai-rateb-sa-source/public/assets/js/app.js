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
      media_error_upload: 'The file could not be saved.'
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
      media_error_upload: 'تعذر حفظ الملف.'
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

  var form = document.getElementById('generateForm');
  if (form) {
    form.onsubmit = async function (event) {
      event.preventDefault();
      var status = document.getElementById('status');
      var button = document.getElementById('generate');
      var langInput = document.getElementById('gen-lang');
      if (langInput) langInput.value = language;
      button.disabled = true;
      status.textContent = dict[language].generating;
      try {
        var response = await fetch('/app/ai/generate.php', { method: 'POST', body: new FormData(form) });
        var payload = await response.json();
        if (!response.ok) throw new Error(payload.error || 'Generation failed');
        location.href = payload.redirect;
      } catch (error) {
        status.textContent = error.message;
        button.disabled = false;
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
        status.textContent = error.message;
        button.disabled = false;
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
      status.textContent = dict[language].generating_voice;
      try {
        var response = await fetch('/app/ai/voice.php', { method: 'POST', body: new FormData(voiceForm) });
        var payload = await response.json();
        if (!response.ok) throw new Error(payload.error || 'Voice generation failed');
        location.href = payload.redirect;
      } catch (error) {
        status.textContent = error.message;
        button.disabled = false;
      }
    };
  }
})();
