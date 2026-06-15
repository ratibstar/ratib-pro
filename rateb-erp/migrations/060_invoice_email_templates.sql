-- RATEB ERP — Invoice email templates
SET NAMES utf8mb4;

INSERT INTO rateb_email_templates (slug, subject, body_html, body_text, is_active) VALUES
('invoice_sent', 'فاتورة {invoice_no} — {company}', '<p>مرحباً {company}،</p><p>تم إصدار الفاتورة <strong>{invoice_no}</strong> بمبلغ <strong>{total} {currency}</strong>.</p><p>تاريخ الاستحقاق: {due_date}</p><p><a href="{preview_url}">عرض الفاتورة</a></p>', 'فاتورة {invoice_no} — المبلغ {total} {currency} — الاستحقاق {due_date}', 1),
('invoice_due_reminder', 'تذكير: فاتورة {invoice_no} تستحق قريباً', '<p>تذكير بفاتورة <strong>{invoice_no}</strong> بمبلغ <strong>{total} {currency}</strong>.</p><p>تاريخ الاستحقاق: {due_date}</p>', 'تذكير فاتورة {invoice_no} — {due_date}', 1),
('invoice_overdue_notice', 'فاتورة متأخرة: {invoice_no}', '<p>الفاتورة <strong>{invoice_no}</strong> متأخرة عن تاريخ الاستحقاق ({due_date}).</p><p>المبلغ المستحق: <strong>{total} {currency}</strong></p>', 'فاتورة متأخرة {invoice_no}', 1)
ON DUPLICATE KEY UPDATE subject = VALUES(subject), body_html = VALUES(body_html), body_text = VALUES(body_text), is_active = 1;
