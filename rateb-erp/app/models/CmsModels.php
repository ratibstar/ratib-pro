<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class CmsPage extends Model
{
    protected string $table = 'rateb_cms_pages';
    protected array $fillable = [
        'slug', 'title_en', 'title_ar', 'content_en', 'content_ar', 'template',
        'status', 'published_at', 'sort_order',
    ];
}

final class CmsSection extends Model
{
    protected string $table = 'rateb_cms_sections';
    protected array $fillable = [
        'page_slug', 'section_key', 'title_en', 'title_ar', 'body_en', 'body_ar',
        'settings_json', 'sort_order', 'is_active',
    ];
}

final class CmsBlock extends Model
{
    protected string $table = 'rateb_cms_blocks';
    protected array $fillable = [
        'section_id', 'block_type', 'title_en', 'title_ar', 'content_en', 'content_ar',
        'icon', 'image_path', 'link_url', 'settings_json', 'sort_order', 'is_active',
    ];
}

final class CmsMenu extends Model
{
    protected string $table = 'rateb_cms_menus';
    protected array $fillable = ['slug', 'name_en', 'name_ar', 'location'];
}

final class CmsMenuItem extends Model
{
    protected string $table = 'rateb_cms_menu_items';
    protected array $fillable = [
        'menu_id', 'parent_id', 'label_en', 'label_ar', 'url', 'sort_order', 'is_active',
    ];
}

final class CmsFooterColumn extends Model
{
    protected string $table = 'rateb_cms_footer_columns';
    protected array $fillable = ['title_en', 'title_ar', 'links_json', 'sort_order'];
}

final class CmsAbout extends Model
{
    protected string $table = 'rateb_cms_about';
    protected array $fillable = [
        'story_en', 'story_ar', 'vision_en', 'vision_ar', 'mission_en', 'mission_ar', 'values_json',
    ];
}

final class CmsTeamMember extends Model
{
    protected string $table = 'rateb_cms_team_members';
    protected array $fillable = [
        'name_en', 'name_ar', 'position_en', 'position_ar', 'bio_en', 'bio_ar',
        'photo_path', 'sort_order', 'is_active',
    ];
}

final class CmsTimeline extends Model
{
    protected string $table = 'rateb_cms_timeline';
    protected array $fillable = ['year_label', 'title_en', 'title_ar', 'body_en', 'body_ar', 'sort_order'];
}

final class CmsServiceCategory extends Model
{
    protected string $table = 'rateb_cms_service_categories';
    protected array $fillable = ['slug', 'name_en', 'name_ar', 'icon', 'sort_order'];
}

final class CmsService extends Model
{
    protected string $table = 'rateb_cms_services';
    protected array $fillable = [
        'category_id', 'slug', 'title_en', 'title_ar', 'summary_en', 'summary_ar',
        'content_en', 'content_ar', 'icon', 'sort_order', 'status',
    ];
}

final class CmsBlogCategory extends Model
{
    protected string $table = 'rateb_cms_blog_categories';
    protected array $fillable = ['slug', 'name_en', 'name_ar'];
}

final class CmsBlogTag extends Model
{
    protected string $table = 'rateb_cms_blog_tags';
    protected array $fillable = ['slug', 'name_en', 'name_ar'];
}

final class CmsBlogAuthor extends Model
{
    protected string $table = 'rateb_cms_blog_authors';
    protected array $fillable = ['name_en', 'name_ar', 'email', 'bio_en', 'bio_ar', 'photo_path'];
}

final class CmsBlogArticle extends Model
{
    protected string $table = 'rateb_cms_blog_articles';
    protected array $fillable = [
        'category_id', 'author_id', 'slug', 'title_en', 'title_ar', 'excerpt_en', 'excerpt_ar',
        'content_en', 'content_ar', 'featured_image', 'status', 'published_at',
        'meta_title_en', 'meta_title_ar', 'meta_description_en', 'meta_description_ar',
    ];

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE slug = :s AND status = 'published' LIMIT 1"
        );
        $stmt->execute(['s' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

final class CmsFaqCategory extends Model
{
    protected string $table = 'rateb_cms_faq_categories';
    protected array $fillable = ['slug', 'name_en', 'name_ar', 'sort_order'];
}

final class CmsFaq extends Model
{
    protected string $table = 'rateb_cms_faqs';
    protected array $fillable = [
        'category_id', 'question_en', 'question_ar', 'answer_en', 'answer_ar', 'sort_order', 'is_active',
    ];
}

final class CmsTestimonial extends Model
{
    protected string $table = 'rateb_cms_testimonials';
    protected array $fillable = [
        'customer_name_en', 'customer_name_ar', 'position_en', 'position_ar',
        'company_en', 'company_ar', 'quote_en', 'quote_ar', 'rating', 'photo_path',
        'status', 'sort_order',
    ];
}

final class CmsSlide extends Model
{
    protected string $table = 'rateb_cms_slides';
    protected array $fillable = [
        'title_en', 'title_ar', 'subtitle_en', 'subtitle_ar', 'image_path', 'video_url',
        'cta_label_en', 'cta_label_ar', 'cta_url', 'sort_order', 'is_active', 'starts_at', 'ends_at',
    ];
}

final class CmsContactSettings extends Model
{
    protected string $table = 'rateb_cms_contact_settings';
    protected array $fillable = [
        'email', 'phone', 'address_en', 'address_ar', 'working_hours_en', 'working_hours_ar',
        'social_json', 'map_embed',
    ];
}

final class CmsOffice extends Model
{
    protected string $table = 'rateb_cms_offices';
    protected array $fillable = [
        'name_en', 'name_ar', 'address_en', 'address_ar', 'phone', 'map_url', 'sort_order',
    ];
}

final class CmsLead extends Model
{
    protected string $table = 'rateb_cms_leads';
    /** Platform marketing inbox — public forms have no company/branch context. */
    protected bool $tenantScoped = false;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'company_id', 'lead_type', 'name', 'email', 'phone', 'company', 'message', 'status',
        'assigned_user_id', 'source_page', 'ip_address', 'branch_id',
    ];
}

final class CmsLeadNote extends Model
{
    protected string $table = 'rateb_cms_lead_notes';
    protected array $fillable = ['lead_id', 'user_id', 'note'];
}

final class CmsNewsletterSubscriber extends Model
{
    protected string $table = 'rateb_cms_newsletter_subscribers';
    protected array $fillable = ['email', 'name', 'segment', 'status'];

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = :e LIMIT 1");
        $stmt->execute(['e' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

final class CmsNewsletterSegment extends Model
{
    protected string $table = 'rateb_cms_newsletter_segments';
    protected array $fillable = ['slug', 'name_en', 'name_ar', 'description_en', 'description_ar'];
}

final class CmsNewsletterCampaign extends Model
{
    protected string $table = 'rateb_cms_newsletter_campaigns';
    protected array $fillable = [
        'subject_en', 'subject_ar', 'body_html_en', 'body_html_ar', 'segment_slug',
        'status', 'scheduled_at', 'sent_at', 'sent_count',
    ];
}

final class CmsSeo extends Model
{
    protected string $table = 'rateb_cms_seo';
    protected array $fillable = [
        'page_slug', 'meta_title_en', 'meta_title_ar', 'meta_description_en', 'meta_description_ar',
        'og_title_en', 'og_title_ar', 'og_description_en', 'og_description_ar', 'og_image',
        'twitter_card', 'canonical_url',
    ];
}

final class CmsRedirect extends Model
{
    protected string $table = 'rateb_cms_redirects';
    protected array $fillable = ['from_path', 'to_path', 'status_code', 'is_active'];
}

final class CmsAnalytics extends Model
{
    protected string $table = 'rateb_cms_analytics';
    protected array $fillable = [
        'google_analytics_id', 'google_tag_manager_id', 'meta_pixel_id', 'tiktok_pixel_id',
        'custom_head_code', 'custom_body_code',
    ];
}

final class CmsRobots extends Model
{
    protected string $table = 'rateb_cms_robots';
    protected array $fillable = ['content'];
}

final class CmsMediaCategory extends Model
{
    protected string $table = 'rateb_cms_media_categories';
    protected array $fillable = ['slug', 'name_en', 'name_ar'];
}

final class CmsMedia extends Model
{
    protected string $table = 'rateb_cms_media';
    protected array $fillable = [
        'category_id', 'file_name', 'file_path', 'mime_type', 'file_size',
        'alt_en', 'alt_ar', 'uploaded_by',
    ];
}

final class CmsTheme extends Model
{
    protected string $table = 'rateb_cms_theme';
    protected array $fillable = [
        'primary_color', 'secondary_color', 'font_family', 'logo_path', 'favicon_path',
        'custom_css', 'custom_js',
    ];
}

final class CmsVisitor extends Model
{
    protected string $table = 'rateb_cms_visitors';
    protected array $fillable = ['visit_date', 'page_views', 'unique_visitors'];
}

final class CmsKbArticle extends Model
{
    protected string $table = 'rateb_cms_kb_articles';
    protected array $fillable = [
        'slug', 'title_en', 'title_ar', 'content_en', 'content_ar', 'category', 'sort_order', 'status',
    ];
}

final class CmsHelpArticle extends Model
{
    protected string $table = 'rateb_cms_help_articles';
    protected array $fillable = [
        'slug', 'title_en', 'title_ar', 'content_en', 'content_ar', 'sort_order', 'status',
    ];
}

final class CmsPartner extends Model
{
    protected string $table = 'rateb_cms_partners';
    protected array $fillable = ['name_en', 'name_ar', 'logo_path', 'website_url', 'sort_order', 'is_active'];
}

final class CmsCareer extends Model
{
    protected string $table = 'rateb_cms_careers';
    protected array $fillable = [
        'slug', 'title_en', 'title_ar', 'department_en', 'department_ar',
        'location_en', 'location_ar', 'description_en', 'description_ar', 'status',
    ];
}

final class CmsSystemStatus extends Model
{
    protected string $table = 'rateb_cms_system_status';
    protected array $fillable = ['component_en', 'component_ar', 'status', 'message_en', 'message_ar'];
}
