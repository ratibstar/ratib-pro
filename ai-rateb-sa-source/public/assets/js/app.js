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
      campaign_language: 'Campaign language',
      lang_auto: 'Let RATEB choose',
      lang_ar: 'Arabic',
      lang_en: 'English',
      lang_bilingual: 'Arabic and English',
      idea_title: 'What do you want to market today?',
      idea_lead: 'Describe the idea in your own words.',
      idea_placeholder: 'Example: I have a new fragrance and I want to launch it in Riyadh and increase WhatsApp sales.',
      idea_submit: '✨ Build my campaign',
      idea_attach: '＋ Add photos or a video',
      idea_optional: 'Attachments are optional. You can continue without files.',
      understood_title: 'I understood your idea',
      confirm_start: '🚀 Approve and start',
      reading_title: 'RATEB is understanding your idea...',
      read_product: 'Understanding the product',
      read_objective: 'Understanding the objective',
      read_audience: 'Understanding the audience',
      read_media: 'Processing attached materials',
      read_direction: 'Preparing the campaign direction',
      materials_ready: 'Your uploaded materials are ready to use.',
      no_attachments: 'No files were attached.',
      direction_later: 'This comes later.',
      ask_intro: 'I understood your idea. One question is still open.',
      ask_objective: 'What should this campaign achieve?',
      ask_audience: 'Who do you want to reach?',
      edit_campaign: 'Edit campaign',
      campaign_name: 'Campaign name',
      stage_idea: 'Idea',
      stage_strategy: 'Strategy',
      stage_copy: 'Copy',
      stage_images: 'Images',
      stage_voice: 'Voice',
      stage_video: 'Video',
      stage_ready: 'Ready campaign',
      revise_idea: 'Edit',
      ask_product: 'What is the product or service?',
      ask_lead: 'Only this detail is still missing.',
      ask_continue: 'Continue',
      audience_unknown: 'Not specified',
      brief_product: 'Product',
      brief_location: 'Location',
      brief_objective: 'Goal',
      brief_audience: 'Audience',
      brief_channel: 'Channel',
      brief_channels: 'Channels',
      brief_language: 'Language',
      brief_tone: 'Brand tone',
      brief_files: 'Attached files',
      files_photos: 'photos',
      files_video: 'a video',
      files_and: 'and',
      images_not_analyzed: 'The photos were not analyzed.',
      video_not_analyzed: 'The video was not analyzed.',
      language_note: 'Campaign language is separate from the page language.',
      remove_file: 'Remove',
      idea_required: 'Write your idea first.',
      idea_long: 'The idea is too long.',
      file_type: 'This file type is not supported.',
      file_size: 'The file is larger than the limit.',
      video_one: 'You can attach one video.',
      video_long: 'The video is longer than your plan allows.',
      video_unknown: 'The video duration could not be checked.',
      plan_images: 'The number of photos is above your plan limit.',
      plan_videos: 'Your plan does not include video.',
      ask_required: 'Write the product or service.',
      save_failed: 'The campaign could not be saved.',
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
      forgot_password: 'Forgot password?',
      forgot_title: 'Forgot password',
      forgot_lead: 'Enter the email on your account.',
      send_reset: 'Continue',
      reset_requested: 'If an account exists for that email, reset instructions will be sent. Email delivery is not configured yet, so no message was sent.',
      reset_title: 'Choose a new password',
      reset_lead: 'Use at least 8 characters.',
      reset_invalid: 'This reset link is invalid or has expired.',
      save_password: 'Save password',
      reset_success: 'Your password was changed. You can log in now.',
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
      type_headline: 'Headline',
      type_ad_copy: 'Main advertisement',
      type_short_ad: 'Short advertisement',
      type_social_posts: 'Social post',
      type_whatsapp: 'WhatsApp message',
      type_call_to_action: 'Call to action',
      type_product_description: 'Product description',
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
      no_strategy: 'No strategy yet. Build it from the campaign brief.',
      brief_title: 'Campaign brief',
      brief_idea: 'Idea',
      brief_tone: 'Tone',
      edit_lead: 'Edit the idea in your own words',
      edit_placeholder: 'Example: change the location to Jeddah',
      edit_example: 'Example: also target small and medium businesses, or make the ad more enthusiastic.',
      update_idea: 'Update the idea',
      updating_idea: 'Updating the idea...',
      strategy_title: 'Campaign strategy',
      strategy_positioning: 'Positioning',
      strategy_core_message: 'Core message',
      strategy_audience: 'Audience',
      strategy_channels: 'Suggested channels',
      strategy_tone: 'Tone',
      strategy_creative_direction: 'Creative direction',
      strategy_call_to_action: 'Call to action',
      strategy_approved: 'Approved',
      start_strategy: 'Start Strategy',
      build_strategy: 'Start Strategy',
      approve_strategy: 'Approve Strategy',
      regenerate: 'Regenerate',
      start_copy: 'Generate Copy',
      regenerate_copy: 'Regenerate',
      generating_strategy: 'RATEB is writing the strategy...',
      generating_copy: 'RATEB is writing the copy...',
      write_slow: 'Writing took too long. Try again.',
      regen_confirm: 'This replaces the approved result. Continue?',
      copy_title: 'Campaign copy',
      copy_locked: 'Approve the strategy first, then RATEB writes the copy.',
      no_copy: 'Copy is ready to write from the approved strategy.',
      write_copy: 'Generate Copy',
      copy_button: 'Copy',
      copy_draft: 'Draft',
      copy_approved: 'Approved',
      approve_output: 'Approve',
      copied: 'Copied',
      copy_failed: 'Could not copy',
      state_done: 'Done',
      state_progress: 'In progress',
      state_open: 'Ready to start',
      state_next: 'Later',
      state_locked: 'Not available',
      stage_images_next: 'The image stage comes later. No generated campaign images are shown here.',
      stage_voice_next: 'The voice stage comes later.',
      stage_video_locked: 'Video generation is not available in this stage.',
      stage_ready_next: 'The ready campaign comes after the earlier stages are complete.',
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
      sample_title: 'Examples you can view and hear',
      sample_photos: 'Pictures',
      sample_videos: 'Videos',
      sample_voices: 'Voices',
      voice_calm: 'Calm voice',
      voice_warm: 'Warm voice',
      voice_clear: 'Clear voice',
      voice_deep: 'Deep voice',
      video_product: 'Product film',
      video_social: 'Hospitality film',
      video_story: 'Vertical story',
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
      format_short: 'Short video',
      delete_campaign: 'Delete campaign',
      delete_warning: 'This permanently removes the campaign, its text, planner items, variations, and media files.',
      delete_confirm_button: 'Delete permanently',
      campaign_deleted: 'Campaign deleted.',
      delete_failed: 'The campaign could not be deleted.',
      delete_files_failed: 'The campaign record was removed, but some of its files could not be deleted.'
    },
    ar: {
      dashboard: 'لوحة التحكم',
      new_campaign: 'حملة جديدة',
      new_campaign_button: '+ حملة جديدة',
      logout: 'تسجيل الخروج',
      campaign_details: 'تفاصيل الحملة',
      product: 'المنتج / الخدمة',
      price: 'السعر',
      campaign_language: 'لغة الحملة',
      lang_auto: 'خل RATEB يختار',
      lang_ar: 'العربية',
      lang_en: 'الإنجليزية',
      lang_bilingual: 'العربية والإنجليزية',
      idea_title: 'وش تبي تسوّق اليوم؟',
      idea_lead: 'اكتب فكرتك بطريقتك...',
      idea_placeholder: 'مثال: عندي عطر جديد وأبغى أطلقه في الرياض وأزيد مبيعات الواتساب.',
      idea_submit: '✨ ابنِ حملتي',
      idea_attach: '＋ أضف صورًا أو فيديو',
      idea_optional: 'المرفقات اختيارية. تقدر تكمل بدون ملفات.',
      understood_title: 'فهمت فكرتك 👌',
      confirm_start: '🚀 اعتمد وابدأ',
      reading_title: 'RATEB يفهم فكرتك...',
      read_product: 'فهم المنتج',
      read_objective: 'فهم الهدف',
      read_audience: 'فهم الجمهور',
      read_media: 'تجهيز المواد المرفقة',
      read_direction: 'تجهيز اتجاه الحملة',
      materials_ready: 'موادك جاهزة للاستخدام.',
      no_attachments: 'ما فيه مرفقات.',
      direction_later: 'هذا يجهز لاحقًا.',
      ask_intro: 'فهمت فكرتك. باقي سؤال واحد.',
      ask_objective: 'وش تبي تحقق من هالحملة؟',
      ask_audience: 'مين تبي يوصل له هذا المنتج؟',
      edit_campaign: 'تعديل الحملة',
      campaign_name: 'اسم الحملة',
      stage_idea: 'الفكرة',
      stage_strategy: 'الاستراتيجية',
      stage_copy: 'النص',
      stage_images: 'الصور',
      stage_voice: 'الصوت',
      stage_video: 'الفيديو',
      stage_ready: 'الحملة الجاهزة',
      revise_idea: 'تعديل',
      ask_product: 'وش المنتج أو الخدمة؟',
      ask_lead: 'باقي هالمعلومة فقط.',
      ask_continue: 'متابعة',
      audience_unknown: 'غير محدد',
      brief_product: 'المنتج',
      brief_location: 'الموقع',
      brief_objective: 'الهدف',
      brief_audience: 'الجمهور',
      brief_channel: 'القناة',
      brief_channels: 'القنوات',
      brief_language: 'اللغة',
      brief_tone: 'نبرة العلامة',
      brief_files: 'المواد المرفقة',
      files_photos: 'صور',
      files_video: 'فيديو',
      files_and: 'و',
      images_not_analyzed: 'ما تم تحليل الصور.',
      video_not_analyzed: 'ما تم تحليل الفيديو.',
      language_note: 'لغة الحملة مستقلة عن لغة الصفحة.',
      remove_file: 'حذف',
      idea_required: 'اكتب فكرتك أولًا.',
      idea_long: 'الفكرة طويلة.',
      file_type: 'نوع الملف غير مدعوم.',
      file_size: 'حجم الملف أكبر من الحد.',
      video_one: 'يمكن إرفاق فيديو واحد.',
      video_long: 'مدة الفيديو أطول من حد خطتك.',
      video_unknown: 'تعذر التحقق من مدة الفيديو.',
      plan_images: 'عدد الصور يتجاوز حد خطتك.',
      plan_videos: 'خطتك لا تسمح بفيديو.',
      ask_required: 'اكتب المنتج أو الخدمة.',
      save_failed: 'تعذر حفظ الحملة.',
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
      forgot_password: 'نسيت كلمة المرور؟',
      forgot_title: 'نسيت كلمة المرور',
      forgot_lead: 'أدخل البريد المسجل في حسابك.',
      send_reset: 'متابعة',
      reset_requested: 'إذا كان هناك حساب لهذا البريد فستُرسل تعليمات الاستعادة. إرسال البريد غير مجهز حالياً، لذلك لم تُرسل أي رسالة.',
      reset_title: 'اختر كلمة مرور جديدة',
      reset_lead: 'استخدم 8 أحرف على الأقل.',
      reset_invalid: 'رابط الاستعادة غير صالح أو انتهت صلاحيته.',
      save_password: 'حفظ كلمة المرور',
      reset_success: 'تم تغيير كلمة المرور. يمكنك تسجيل الدخول الآن.',
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
      type_headline: 'العنوان',
      type_ad_copy: 'الإعلان الرئيسي',
      type_short_ad: 'الإعلان القصير',
      type_social_posts: 'منشور التواصل',
      type_whatsapp: 'رسالة واتساب',
      type_call_to_action: 'الدعوة إلى الإجراء',
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
      no_strategy: 'ما فيه استراتيجية بعد. ابنِها من ملخص الحملة.',
      brief_title: 'ملخص الحملة',
      brief_idea: 'الفكرة',
      brief_tone: 'النبرة',
      edit_lead: 'عدّل الفكرة بكلامك',
      edit_placeholder: 'مثال: غيّر الموقع إلى الرياض وجدة',
      edit_example: 'مثال: أبغى أستهدف الشركات الصغيرة والمتوسطة أيضاً، أو خل الإعلان أكثر حماساً.',
      update_idea: 'حدّث الفكرة',
      updating_idea: 'RATEB يحدّث الفكرة...',
      strategy_title: 'استراتيجية الحملة',
      strategy_positioning: 'الفكرة الرئيسية',
      strategy_core_message: 'الرسالة الأساسية',
      strategy_audience: 'الجمهور',
      strategy_channels: 'القنوات المقترحة',
      strategy_tone: 'النبرة',
      strategy_creative_direction: 'الاتجاه الإبداعي',
      strategy_call_to_action: 'الدعوة إلى الإجراء',
      strategy_approved: 'معتمدة',
      start_strategy: 'ابدأ الاستراتيجية',
      build_strategy: 'ابدأ الاستراتيجية',
      approve_strategy: 'اعتماد الاستراتيجية',
      regenerate: 'إعادة توليد',
      start_copy: 'ابدأ كتابة الإعلان',
      regenerate_copy: 'إعادة توليد',
      generating_strategy: 'RATEB يكتب الاستراتيجية...',
      generating_copy: 'RATEB يكتب النصوص...',
      write_slow: 'الكتابة تأخرت. حاول مرة أخرى.',
      regen_confirm: 'هذا يستبدل النتيجة المعتمدة. تبي تكمل؟',
      copy_title: 'نصوص الحملة',
      copy_locked: 'اعتمد الاستراتيجية أولًا، ثم يكتب RATEB النصوص.',
      no_copy: 'النصوص جاهزة للكتابة من الاستراتيجية المعتمدة.',
      write_copy: 'ابدأ كتابة الإعلان',
      copy_button: 'نسخ',
      copy_draft: 'مسودة',
      copy_approved: 'معتمد',
      approve_output: 'اعتماد',
      copied: 'تم النسخ',
      copy_failed: 'تعذر النسخ',
      state_done: 'مكتملة',
      state_progress: 'قيد الإعداد',
      state_open: 'جاهزة للبدء',
      state_next: 'لاحقًا',
      state_locked: 'غير متاحة',
      stage_images_next: 'مرحلة الصور لاحقًا. لا توجد صور حملة مولَّدة هنا.',
      stage_voice_next: 'مرحلة الصوت لاحقًا.',
      stage_video_locked: 'توليد الفيديو غير متاح في هذه المرحلة.',
      stage_ready_next: 'الحملة الجاهزة تأتي بعد اكتمال المراحل السابقة.',
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
      sample_title: 'أمثلة للمشاهدة والاستماع',
      sample_photos: 'الصور',
      sample_videos: 'الفيديو',
      sample_voices: 'الأصوات',
      voice_calm: 'صوت هادئ',
      voice_warm: 'صوت دافئ',
      voice_clear: 'صوت واضح',
      voice_deep: 'صوت عميق',
      video_product: 'فيلم المنتج',
      video_social: 'فيلم الضيافة',
      video_story: 'قصة عمودية',
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
      format_short: 'فيديو قصير',
      delete_campaign: 'حذف الحملة',
      delete_warning: 'هذا يحذف الحملة ونصها وعناصر المخطط والنسخ والملفات نهائياً.',
      delete_confirm_button: 'احذف نهائياً',
      campaign_deleted: 'تم حذف الحملة.',
      delete_failed: 'تعذر حذف الحملة.',
      delete_files_failed: 'حُذف سجل الحملة، لكن تعذر حذف بعض ملفاتها.'
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
    document.querySelectorAll('[data-i18n-placeholder]').forEach(function (node) {
      var key = node.dataset.i18nPlaceholder;
      if (labels[key]) node.setAttribute('placeholder', labels[key]);
    });
    document.querySelectorAll('[data-ask="audience"]').forEach(function (node) {
      var product = node.getAttribute('data-product') || '';
      node.textContent = language === 'ar'
        ? 'مين تبي يوصل له ' + (product || 'هذا المنتج') + '؟'
        : 'Who do you want to reach' + (product ? ' with ' + product : '') + '?';
    });
    root.lang = language;
    root.dir = language === 'ar' ? 'rtl' : 'ltr';
    document.cookie = 'rateb_ui_lang=' + language + ';path=/;SameSite=Lax';
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
    var names = ['idea', 'strategy', 'copy', 'images', 'voice', 'video', 'ready'];
    function openTab(name) {
      if (name === 'overview' || name === 'details' || name === 'planner' || name === 'variations' || name === 'brand') name = 'idea';
      if (name === 'ai') name = 'strategy';
      if (name === 'media') name = 'images';
      if (names.indexOf(name) < 0) name = 'idea';
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
    if (new URLSearchParams(location.search).get('media_error')) hash = 'images';
    openTab(hash || 'idea');
  }

  document.querySelectorAll('form[data-ai]').forEach(function (form) {
    form.onsubmit = async function (event) {
      event.preventDefault();
      if (form.getAttribute('data-locked') === '1' && !window.confirm(dict[language].regen_confirm)) {
        return;
      }
      if (form.getAttribute('data-locked') === '1') {
        var confirmInput = form.querySelector('input[name="confirm"]');
        if (confirmInput) confirmInput.value = '1';
      }
      var status = document.getElementById(form.getAttribute('data-status') || '');
      var button = form.querySelector('button[type="submit"]');
      var busyKey = form.getAttribute('data-busy') || 'generating';
      if (button) {
        button.disabled = true;
        button.classList.add('is-busy');
      }
      if (status) {
        status.className = 'notice stage-status';
        status.textContent = dict[language][busyKey] || dict[language].generating;
      }
      var controller = new AbortController();
      var timer = setTimeout(function () { controller.abort(); }, 45000);
      try {
        var response = await fetch('/app/ai/generate.php', { method: 'POST', body: new FormData(form), signal: controller.signal });
        clearTimeout(timer);
        var payload = await response.json();
        if (!response.ok) throw new Error(payload.error || dict[language].ai_failed || 'Request failed');
        location.href = payload.redirect || location.href;
      } catch (error) {
        clearTimeout(timer);
        if (status) {
          status.className = 'notice is-error stage-status';
          status.textContent = error.name === 'AbortError' ? dict[language].write_slow : error.message;
        }
        if (button) {
          button.disabled = false;
          button.classList.remove('is-busy');
        }
      }
    };
  });

  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.onclick = async function () {
      var node = document.getElementById(button.getAttribute('data-copy'));
      if (!node) return;
      try {
        await navigator.clipboard.writeText(node.innerText);
        button.textContent = dict[language].copied;
      } catch (error) {
        button.textContent = dict[language].copy_failed;
      }
    };
  });

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

  var readingFlow = document.getElementById('reading-flow');
  if (readingFlow) {
    var readingRows = readingFlow.querySelectorAll('[data-state]');
    var readingIndex = 0;
    var readingButton = document.getElementById('reading-continue');
    function showReadingStep() {
      if (readingIndex >= readingRows.length) {
        if (readingButton) readingButton.click();
        return;
      }
      readingRows[readingIndex].classList.add('is-on');
      readingIndex += 1;
      window.setTimeout(showReadingStep, 420);
    }
    window.setTimeout(showReadingStep, 280);
  }

  var ideaForm = document.getElementById('idea-form');
  var ideaFiles = document.getElementById('idea-files');
  var ideaPicks = document.getElementById('idea-picks');
  if (ideaForm && ideaFiles && ideaPicks) {
    var picked = [];
    var imageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    var videoTypes = ['video/mp4', 'video/webm'];
    function ideaNotice(key) {
      var old = document.getElementById('idea-client-error');
      if (old) old.remove();
      var labels = dict[language] || dict.en;
      if (!labels[key]) return;
      var note = document.createElement('p');
      note.id = 'idea-client-error';
      note.className = 'idea-error';
      note.textContent = labels[key];
      ideaForm.insertBefore(note, ideaForm.firstChild);
    }
    function renderPicks() {
      ideaPicks.replaceChildren();
      picked.forEach(function (file, index) {
        var item = document.createElement('figure');
        item.className = 'pick';
        var media;
        if (file.type.indexOf('image/') === 0) {
          media = document.createElement('img');
          media.alt = '';
        } else {
          media = document.createElement('video');
          media.muted = true;
          media.playsInline = true;
        }
        media.src = URL.createObjectURL(file);
        item.appendChild(media);
        var caption = document.createElement('figcaption');
        var sizeLabel = file.size >= 1048576
          ? (Math.round(file.size / 104857.6) / 10) + (language === 'ar' ? ' م.ب' : ' MB')
          : Math.max(1, Math.round(file.size / 1024)) + (language === 'ar' ? ' ك.ب' : ' KB');
        caption.textContent = file.name + ' · ' + sizeLabel;
        item.appendChild(caption);
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'quiet';
        remove.textContent = (dict[language] && dict[language].remove_file) || 'Remove';
        remove.addEventListener('click', function () {
          URL.revokeObjectURL(media.src);
          picked.splice(index, 1);
          renderPicks();
        });
        item.appendChild(remove);
        ideaPicks.appendChild(item);
      });
    }
    ideaFiles.addEventListener('change', function () {
      var hasVideo = ideaForm.getAttribute('data-has-video') === '1' || picked.some(function (file) { return videoTypes.indexOf(file.type) >= 0; });
      Array.prototype.forEach.call(ideaFiles.files, function (file) {
        var image = imageTypes.indexOf(file.type) >= 0;
        var video = videoTypes.indexOf(file.type) >= 0;
        if (!image && !video) {
          ideaNotice('file_type');
          return;
        }
        if ((image && file.size > 8 * 1024 * 1024) || (video && file.size > 40 * 1024 * 1024)) {
          ideaNotice('file_size');
          return;
        }
        if (video && hasVideo) {
          ideaNotice('video_one');
          return;
        }
        if (video) hasVideo = true;
        picked.push(file);
      });
      ideaFiles.value = '';
      renderPicks();
    });
    ideaForm.addEventListener('submit', function (event) {
      if (!window.FormData || !window.XMLHttpRequest) return;
      event.preventDefault();
      var data = new FormData(ideaForm);
      data.delete('files[]');
      picked.forEach(function (file) { data.append('files[]', file); });
      var bar = document.getElementById('upload-bar');
      var progress = document.getElementById('upload-progress');
      if (progress) progress.hidden = false;
      var xhr = new XMLHttpRequest();
      xhr.open('POST', ideaForm.action);
      xhr.upload.onprogress = function (e) {
        if (bar && e.lengthComputable) bar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
      };
      xhr.onload = function () {
        var finalUrl = xhr.responseURL || '';
        if (finalUrl.indexOf('/campaign.php') !== -1 || finalUrl.indexOf('view=') !== -1) {
          window.location.href = finalUrl;
          return;
        }
        document.open();
        document.write(xhr.responseText);
        document.close();
      };
      xhr.onerror = function () {
        if (progress) progress.hidden = true;
        ideaNotice('save_failed');
      };
      xhr.send(data);
    });
  }
})();
